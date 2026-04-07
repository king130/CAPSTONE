<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\School;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AdminController extends Controller
{
    public function users(Request $request)
    {
        if (! $this->canManageUsers($request->user())) {
            return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $rows = User::query()
            ->with([
                'platformRole.permissions',
                'organizationMemberships.role',
                'organizationMemberships.organization',
                'student',
                'school',
                'company',
            ])
            ->orderByDesc('created_at')
            ->get()
            ->map(function (User $u) {
                $primaryMembership = $u->activeMemberships()->first();
                $student = $u->student;
                $school = $u->school;
                $company = $u->company;

                return [
                    'uid' => (string) $u->id,
                    'email' => $u->email,
                    'displayName' => $u->name,
                    'role' => $u->effectiveAppRole(),
                    'legacyRole' => $u->role,
                    'accessScope' => $u->isPlatformAdmin() ? 'platform' : ($primaryMembership ? 'organization' : 'personal'),
                    'platformRole' => $u->platformRole ? [
                        'slug' => $u->platformRole->slug,
                        'name' => $u->platformRole->name,
                    ] : null,
                    'organization' => $primaryMembership ? [
                        'id' => (string) $primaryMembership->organization->id,
                        'name' => $primaryMembership->organization->name,
                        'type' => $primaryMembership->organization->type,
                        'memberRole' => $primaryMembership->role->slug,
                        'memberRoleName' => $primaryMembership->role->name,
                    ] : null,
                    'student' => $student ? [
                        'id' => (string) $student->id,
                        'schoolName' => $student->school_name,
                        'course' => $student->course,
                        'yearLevel' => $student->year_level,
                    ] : null,
                    'school' => $school ? [
                        'id' => (string) $school->id,
                        'name' => $school->institution_name,
                        'address' => $school->school_address,
                        'contactPerson' => $school->position,
                        'verificationStatus' => $school->verification_status,
                    ] : null,
                    'company' => $company ? [
                        'id' => (string) $company->id,
                        'name' => $company->company_name,
                        'industry' => $company->industry_type,
                        'address' => $company->company_address,
                        'verificationStatus' => $company->verification_status,
                    ] : null,
                    'isTemporary' => $u->is_temporary,
                    'isActive' => $u->is_active,
                    'profile' => $u->profile ?? [],
                    'createdAt' => $u->created_at?->toIso8601String(),
                ];
            });

        return response()->json(['data' => $rows]);
    }

    public function updateUser(Request $request, User $user)
    {
        if (! $this->canManageUsers($request->user())) {
            return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $data = $request->validate([
            'role' => ['sometimes', 'string', 'max:32'],
            'isActive' => ['sometimes', 'boolean'],
            'profileSetupComplete' => ['sometimes', 'boolean'],
            'verificationStatus' => ['sometimes', 'string', 'in:pending,approved,rejected'],
        ]);

        if (array_key_exists('role', $data)) {
            $user->role = $data['role'];
        }
        if (array_key_exists('isActive', $data)) {
            $user->is_active = $data['isActive'];
        }
        if (array_key_exists('profileSetupComplete', $data)) {
            $user->profile_setup_complete = $data['profileSetupComplete'];
        }

        $user->save();

        if (array_key_exists('verificationStatus', $data)) {
            if ($user->school) {
                $user->school()->update(['verification_status' => $data['verificationStatus']]);
            }

            if ($user->company) {
                $user->company()->update(['verification_status' => $data['verificationStatus']]);
            }

            if ($data['verificationStatus'] === 'approved') {
                $user->is_active = true;
            }

            if ($data['verificationStatus'] === 'rejected') {
                $user->is_active = false;
            }

            $user->save();
        }

        $auth = app(AuthController::class);

        $fresh = $user->fresh([
            'student',
            'company',
            'school',
            'platformRole.permissions',
            'organizationMemberships.role.permissions',
            'organizationMemberships.organization.subscription',
        ]);

        return response()->json($auth->formatUserProfile($fresh));
    }

    private function canManageUsers(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->role === 'admin') {
            return true;
        }

        return in_array('platform.manage_users', $user->platformPermissionKeys(), true);
    }
}
