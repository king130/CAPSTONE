<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Contract;
use App\Models\Internship;
use App\Models\School;
use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class InternshipEligibilityService
{
    /**
     * Resolve the student's school using the same precedence as the student eligible listing.
     */
    public function resolveStudentSchool(Student $student): ?School
    {
        if ($student->school_id) {
            $school = School::query()->find($student->school_id);
            if ($school) {
                return $school;
            }
        }

        if ($student->organization_id) {
            $school = School::query()->where('organization_id', $student->organization_id)->first();
            if ($school) {
                return $school;
            }
        }

        if ($student->school_subscription_code) {
            $school = School::query()->where('subscription_code', $student->school_subscription_code)->first();
            if ($school) {
                return $school;
            }
        }

        if ($student->school_name) {
            return School::query()->where('institution_name', $student->school_name)->first();
        }

        return null;
    }

    public function resolveHostType(Internship $internship): string
    {
        if (filled($internship->host_type)) {
            return (string) $internship->host_type;
        }

        return $internship->school_id ? 'school' : 'company';
    }

    /**
     * @return array{allowed: bool, message: string, code: string}
     */
    public function evaluate(Student $student, Internship $internship): array
    {
        if (strtolower(trim((string) $internship->status)) !== 'active') {
            return [
                'allowed' => false,
                'message' => 'Only active internships can receive applications.',
                'code' => 'internship_not_active',
            ];
        }

        $school = $this->resolveStudentSchool($student);
        if (! $school) {
            return [
                'allowed' => false,
                'message' => 'Your student account is not linked to a school, so this internship is unavailable.',
                'code' => 'student_school_required',
            ];
        }

        $hostType = $this->resolveHostType($internship);

        if ($hostType === 'school') {
            if ((int) $internship->school_id !== (int) $school->id) {
                return [
                    'allowed' => false,
                    'message' => 'This school-hosted internship is not available to your school.',
                    'code' => 'school_internship_mismatch',
                ];
            }

            return [
                'allowed' => true,
                'message' => '',
                'code' => 'eligible',
            ];
        }

        $company = $internship->relationLoaded('company')
            ? $internship->company
            : $internship->company()->first();

        if (! $company || ! $company->user_id || ! $school->user_id) {
            return [
                'allowed' => false,
                'message' => 'This internship is not available to your school.',
                'code' => 'internship_unavailable',
            ];
        }

        $hasActiveAgreement = Contract::query()
            ->where('school_user_id', $school->user_id)
            ->where('company_user_id', $company->user_id)
            ->where('status', 'active')
            ->exists();

        if (! $hasActiveAgreement) {
            return [
                'allowed' => false,
                'message' => 'This internship is not available because your school does not have an active agreement with the company.',
                'code' => 'agreement_required',
            ];
        }

        return [
            'allowed' => true,
            'message' => '',
            'code' => 'eligible',
        ];
    }

    public function isEligible(Student $student, Internship $internship): bool
    {
        return $this->evaluate($student, $internship)['allowed'] === true;
    }

    /**
     * Apply the same eligibility rules used by application creation to a listing query.
     *
     * @return Builder<Internship>|null Null when the student has no resolvable school (empty result expected).
     */
    public function applyEligibleListingConstraints(Builder $query, Student $student): ?Builder
    {
        $school = $this->resolveStudentSchool($student);
        if (! $school) {
            return null;
        }

        $activeCompanyUserIds = $school->user_id
            ? Contract::query()
                ->where('school_user_id', $school->user_id)
                ->where('status', 'active')
                ->pluck('company_user_id')
                ->map(fn ($value) => (int) $value)
                ->filter()
                ->values()
            : collect();

        $query->where('status', 'active')
            ->where(function ($builder) use ($school, $activeCompanyUserIds) {
                $builder->where(function ($schoolBuilder) use ($school) {
                    $schoolBuilder
                        ->where(function ($typedSchool) use ($school) {
                            $typedSchool
                                ->where('host_type', 'school')
                                ->where('school_id', $school->id);
                        })
                        ->orWhere(function ($fallbackSchool) use ($school) {
                            $fallbackSchool
                                ->whereNull('host_type')
                                ->where('school_id', $school->id);
                        });
                });

                if ($activeCompanyUserIds->isNotEmpty()) {
                    $companyIds = Company::query()
                        ->whereIn('user_id', $activeCompanyUserIds)
                        ->pluck('id');

                    $builder->orWhere(function ($companyBuilder) use ($companyIds) {
                        $companyBuilder->where(function ($companyHostBuilder) {
                            $companyHostBuilder
                                ->where('host_type', 'company')
                                ->orWhere(function ($fallbackCompanyBuilder) {
                                    $fallbackCompanyBuilder
                                        ->whereNull('host_type')
                                        ->whereNotNull('company_id');
                                });
                        });

                        if ($companyIds->isNotEmpty()) {
                            $companyBuilder->whereIn('company_id', $companyIds);
                        } else {
                            $companyBuilder->whereRaw('1 = 0');
                        }
                    });
                }
            });

        return $query;
    }

    /**
     * @return Collection<int, int>
     */
    public function activePartnerCompanyUserIds(School $school): Collection
    {
        if (! $school->user_id) {
            return collect();
        }

        return Contract::query()
            ->where('school_user_id', $school->user_id)
            ->where('status', 'active')
            ->pluck('company_user_id')
            ->map(fn ($value) => (int) $value)
            ->filter()
            ->values();
    }
}
