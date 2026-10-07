<?php

namespace App\Services;

use App\Models\Application;
use App\Models\Company;
use App\Models\Contract;
use App\Models\OrganizationMembership;
use App\Models\School;
use App\Models\Student;
use App\Models\User;

/**
 * Determines whether two users may open a direct chat based on
 * existing domain relationships (not directory visibility).
 */
class ChatRelationshipGate
{
    /**
     * Application statuses that establish a student ↔ company messaging link.
     *
     * @var array<int, string>
     */
    public const QUALIFYING_APPLICATION_STATUSES = ['endorsed', 'accepted'];

    /**
     * Agreement statuses that establish a school ↔ company messaging link.
     *
     * @var array<int, string>
     */
    public const QUALIFYING_AGREEMENT_STATUSES = ['pending', 'active', 'pending_amendment'];

    public function canCommunicate(User $userA, User $userB): bool
    {
        if ((int) $userA->id === (int) $userB->id) {
            return false;
        }

        if ($this->studentSchoolLinked($userA, $userB) || $this->studentSchoolLinked($userB, $userA)) {
            return true;
        }

        if ($this->studentCompanyLinked($userA, $userB) || $this->studentCompanyLinked($userB, $userA)) {
            return true;
        }

        if ($this->schoolCompanyLinked($userA, $userB)) {
            return true;
        }

        return false;
    }

    private function studentSchoolLinked(User $studentUser, User $schoolSideUser): bool
    {
        $student = $this->studentRecord($studentUser);
        if (! $student) {
            return false;
        }

        $school = null;
        if ($student->school_id) {
            $school = School::query()->find($student->school_id);
        } elseif ($student->organization_id) {
            $school = School::query()->where('organization_id', $student->organization_id)->first();
        }

        if (! $school) {
            return false;
        }

        return $this->isSchoolOrganizationMember($schoolSideUser, $school);
    }

    private function studentCompanyLinked(User $studentUser, User $companySideUser): bool
    {
        $student = $this->studentRecord($studentUser);
        if (! $student) {
            return false;
        }

        $company = $this->companyRecord($companySideUser);
        if (! $company) {
            // Peer may be company staff; resolve company via their org membership.
            $company = $this->companyForOrganizationMember($companySideUser);
        }

        if (! $company) {
            return false;
        }

        if (! $this->isCompanyOrganizationMember($companySideUser, $company)) {
            return false;
        }

        return Application::query()
            ->where('student_id', $student->id)
            ->where('company_id', $company->id)
            ->whereIn('status', self::QUALIFYING_APPLICATION_STATUSES)
            ->exists();
    }

    private function schoolCompanyLinked(User $userA, User $userB): bool
    {
        $schoolA = $this->schoolForOrganizationMember($userA);
        $companyB = $this->companyForOrganizationMember($userB);
        if ($schoolA && $companyB && $this->agreementExistsBetween($schoolA, $companyB)) {
            return true;
        }

        $schoolB = $this->schoolForOrganizationMember($userB);
        $companyA = $this->companyForOrganizationMember($userA);
        if ($schoolB && $companyA && $this->agreementExistsBetween($schoolB, $companyA)) {
            return true;
        }

        return false;
    }

    private function agreementExistsBetween(School $school, Company $company): bool
    {
        return Contract::query()
            ->whereIn('status', self::QUALIFYING_AGREEMENT_STATUSES)
            ->where(function ($query) use ($school, $company) {
                $query->where(function ($pair) use ($school, $company) {
                    $pair
                        ->where('school_user_id', $school->user_id)
                        ->where('company_user_id', $company->user_id);
                });

                if ($school->organization_id && $company->organization_id) {
                    $query->orWhere(function ($orgs) use ($school, $company) {
                        $orgs
                            ->where('requester_organization_id', $school->organization_id)
                            ->where('partner_organization_id', $company->organization_id);
                    })->orWhere(function ($orgs) use ($school, $company) {
                        $orgs
                            ->where('requester_organization_id', $company->organization_id)
                            ->where('partner_organization_id', $school->organization_id);
                    });
                }
            })
            ->exists();
    }

    private function studentRecord(User $user): ?Student
    {
        if ($user->relationLoaded('student')) {
            return $user->student;
        }

        return Student::query()->where('user_id', $user->id)->first();
    }

    private function companyRecord(User $user): ?Company
    {
        if ($user->relationLoaded('company')) {
            return $user->company;
        }

        return Company::query()->where('user_id', $user->id)->first();
    }

    private function isSchoolOrganizationMember(User $user, School $school): bool
    {
        if ((int) $school->user_id === (int) $user->id) {
            return true;
        }

        if (! $school->organization_id) {
            return false;
        }

        return $this->hasActiveStaffMembership($user->id, (int) $school->organization_id);
    }

    private function isCompanyOrganizationMember(User $user, Company $company): bool
    {
        if ((int) $company->user_id === (int) $user->id) {
            return true;
        }

        if (! $company->organization_id) {
            return false;
        }

        return $this->hasActiveStaffMembership($user->id, (int) $company->organization_id);
    }

    private function schoolForOrganizationMember(User $user): ?School
    {
        $owned = School::query()->where('user_id', $user->id)->first();
        if ($owned) {
            return $owned;
        }

        $membership = $this->activeMembership($user->id);
        if (! $membership || $membership->organization?->type !== 'school') {
            return null;
        }

        if ($this->isStudentLikeRole($membership)) {
            return null;
        }

        return School::query()->where('organization_id', $membership->organization_id)->first();
    }

    private function companyForOrganizationMember(User $user): ?Company
    {
        $owned = Company::query()->where('user_id', $user->id)->first();
        if ($owned) {
            return $owned;
        }

        $membership = $this->activeMembership($user->id);
        if (! $membership || $membership->organization?->type !== 'company') {
            return null;
        }

        if ($this->isStudentLikeRole($membership)) {
            return null;
        }

        return Company::query()->where('organization_id', $membership->organization_id)->first();
    }

    private function hasActiveStaffMembership(int $userId, int $organizationId): bool
    {
        $membership = OrganizationMembership::query()
            ->with(['role:id,slug', 'organization:id,type'])
            ->where('user_id', $userId)
            ->where('organization_id', $organizationId)
            ->where('status', 'active')
            ->first();

        if (! $membership) {
            return false;
        }

        return ! $this->isStudentLikeRole($membership);
    }

    private function activeMembership(int $userId): ?OrganizationMembership
    {
        return OrganizationMembership::query()
            ->with(['role:id,slug', 'organization:id,type'])
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->first();
    }

    private function isStudentLikeRole(OrganizationMembership $membership): bool
    {
        $slug = $membership->role?->slug;

        return in_array($slug, ['intern', 'student_member'], true);
    }
}
