<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Role;
use App\Models\School;
use App\Models\Student;
use App\Models\Subscription;
use App\Models\User;
use App\Services\SubscriptionPlanService;
use App\Services\TenantRoleService;
use App\Support\TenantSubscriptionPermissions;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function __construct(
        private readonly SubscriptionPlanService $subscriptionPlans,
        private readonly TenantRoleService $tenantRoleService,
    )
    {
    }

    public function update(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'displayName' => ['sometimes', 'string', 'max:255'],
            'profile' => ['sometimes', 'array'],
            'profileSetupComplete' => ['sometimes', 'boolean'],
            'role' => ['sometimes', Rule::in(['student', 'school', 'company', 'guest'])],
            'subscription' => ['sometimes', 'array'],
            'subscription.plan' => ['sometimes', 'string', 'max:255'],
            'subscription.status' => ['sometimes', Rule::in(['active', 'pending', 'inactive'])],
            'subscription.billingCycle' => ['sometimes', 'string', 'max:255'],
            'subscription.pendingChange' => ['nullable', 'array'],
            'subscription.pendingChange.requestId' => ['sometimes', 'string', 'max:255'],
            'subscription.pendingChange.targetPlan' => ['sometimes', 'string', 'max:255'],
            'subscription.pendingChange.amount' => ['sometimes', 'numeric'],
            'subscription.pendingChange.currency' => ['sometimes', 'string', 'max:8'],
            'subscription.pendingPayment' => ['nullable', 'array'],
            'subscription.pendingPayment.requestId' => ['sometimes', 'string', 'max:255'],
            'subscription.pendingPayment.method' => ['sometimes', 'string', 'max:50'],
            'subscription.pendingPayment.receiptReference' => ['sometimes', 'string', 'max:255'],
            'subscription.pendingPayment.paidAtIso' => ['sometimes', 'string', 'max:255'],
        ]);

        if (isset($data['displayName'])) {
            $user->name = $data['displayName'];
        }
        if (isset($data['name'])) {
            $user->name = $data['name'];
        }
        if (isset($data['profile'])) {
            $user->profile = array_merge($user->profile ?? [], $data['profile']);
        }
        if (isset($data['profileSetupComplete'])) {
            $user->profile_setup_complete = $data['profileSetupComplete'];
        }

        if (isset($data['role']) && $user->role === 'guest') {
            $this->promoteGuestRole($user, $data['role']);
        }

        if (isset($data['profile'])) {
            $this->syncRoleSpecificProfile($user, $data['profile']);
        }

        if (isset($data['subscription'])) {
            if (! TenantSubscriptionPermissions::userMayManageOrganizationSubscription($user)) {
                return response()->json([
                    'message' => 'You do not have permission to update organization subscription settings.',
                ], Response::HTTP_FORBIDDEN);
            }

            $user->loadMissing('organizationMemberships.organization.subscription');

            $activeOrganization = $user->primaryOrganizationMembership()?->organization;
            $subscriptionData = $data['subscription'];

            if ($activeOrganization) {
                $subscription = $activeOrganization->subscription;

                if (! $subscription) {
                    $subscription = Subscription::query()->create([
                        'plan' => 'free',
                        'status' => 'active',
                        'billing_cycle' => 'monthly',
                    ]);
                    $activeOrganization->subscription_id = $subscription->id;
                    $activeOrganization->save();
                }

                $subscriptionUpdates = [];
                if (array_key_exists('plan', $subscriptionData)) {
                    $subscriptionUpdates['plan'] = $subscriptionData['plan'];
                }
                if (array_key_exists('status', $subscriptionData)) {
                    $subscriptionUpdates['status'] = $subscriptionData['status'];
                }
                if (array_key_exists('billingCycle', $subscriptionData)) {
                    $subscriptionUpdates['billing_cycle'] = $subscriptionData['billingCycle'];
                }
                if ($subscriptionUpdates !== []) {
                    $subscription->fill($subscriptionUpdates);
                    $subscription->save();
                }

                $settings = $activeOrganization->settings ?? [];
                $settingsChanged = false;

                if (array_key_exists('pendingChange', $subscriptionData)) {
                    if ($subscriptionData['pendingChange'] === null) {
                        unset($settings['pending_plan_change']);
                    } else {
                        $settings['pending_plan_change'] = $subscriptionData['pendingChange'];
                    }
                    $settingsChanged = true;
                }

                if (array_key_exists('pendingPayment', $subscriptionData)) {
                    $existingPending = $settings['pending_plan_change'] ?? [];
                    if ($subscriptionData['pendingPayment'] === null) {
                        unset($existingPending['payment']);
                    } else {
                        $existingPending['payment'] = $subscriptionData['pendingPayment'];
                    }
                    if ($existingPending === []) {
                        unset($settings['pending_plan_change']);
                    } else {
                        $settings['pending_plan_change'] = $existingPending;
                    }
                    $settingsChanged = true;
                }

                if ($settingsChanged) {
                    $activeOrganization->settings = $settings;
                    $activeOrganization->save();
                }

                if ($subscriptionUpdates !== [] || $settingsChanged) {
                    $this->subscriptionPlans->syncOrganizationCompliance($activeOrganization->fresh(['subscription', 'owner']));
                }
            }
        }

        $user->save();

        $freshUser = $user->fresh([
            'student',
            'company',
            'school',
            'platformRole.permissions',
            'organizationMemberships.role.permissions',
            'organizationMemberships.organization.subscription',
        ]);

        $auth = app(AuthController::class);

        return response()->json($auth->formatUserProfile($freshUser));
    }

    /**
     * @param array<string, mixed> $profile
     */
    private function syncRoleSpecificProfile(User $user, array $profile): void
    {
        $organization = $user->primaryOrganizationMembership()?->organization;

        if ($user->role === 'company' && $user->company) {
            $user->company->fill([
                'company_name' => $profile['companyName'] ?? $user->company->company_name,
                'company_type' => $profile['companyType'] ?? $user->company->company_type,
                'industry_type' => $profile['industryType'] ?? $user->company->industry_type,
                'company_address' => $profile['companyAddress'] ?? $user->company->company_address,
                'company_email' => $profile['companyEmail'] ?? $user->company->company_email,
                'contact_person_name' => $profile['contactPersonName'] ?? $user->company->contact_person_name,
                'contact_person_title' => $profile['contactPersonTitle'] ?? $user->company->contact_person_title,
                'contact_person_email' => $profile['contactPersonEmail'] ?? $user->company->contact_person_email,
                'company_contact_number' => $profile['companyContactNumber'] ?? $profile['contactNumber'] ?? $user->company->company_contact_number,
            ]);
            $user->company->save();

            if ($organization && ! empty($profile['companyName'])) {
                $organization->name = (string) $profile['companyName'];
                $organization->save();
            }
        }

        if ($user->role === 'school' && $user->school) {
            $user->school->fill([
                'institution_name' => $profile['institutionName'] ?? $user->school->institution_name,
                'institution_type' => $profile['institutionType'] ?? $user->school->institution_type,
                'department' => $profile['department'] ?? $user->school->department,
                'position' => $profile['position'] ?? $user->school->position,
                'official_school_email' => $profile['officialSchoolEmail'] ?? $user->school->official_school_email,
                'school_contact_number' => $profile['schoolContactNumber'] ?? $profile['contactNumber'] ?? $user->school->school_contact_number,
                'school_address' => $profile['schoolAddress'] ?? $user->school->school_address,
            ]);
            $user->school->save();

            if ($organization && ! empty($profile['institutionName'])) {
                $organization->name = (string) $profile['institutionName'];
                $organization->save();
            }
        }

        if ($user->role === 'student' && $user->student) {
            $resolvedSchool = $this->resolveStudentSchoolFromProfile($profile, $user->student);
            $user->student->fill([
                'school_id' => $resolvedSchool?->id ?? $user->student->school_id,
                'organization_id' => $resolvedSchool?->organization_id ?? $user->student->organization_id,
                'student_id_number' => $profile['studentNumber'] ?? $profile['studentId'] ?? $user->student->student_id_number,
                'school_name' => $resolvedSchool?->institution_name ?? $profile['schoolName'] ?? $user->student->school_name,
                'school_subscription_code' => $resolvedSchool?->subscription_code ?? $profile['schoolSubscriptionCode'] ?? $user->student->school_subscription_code,
                'course' => $profile['course'] ?? $user->student->course,
                'year_level' => $profile['yearLevel'] ?? $user->student->year_level,
                'preferred_field' => $profile['preferredField'] ?? $user->student->preferred_field,
                'contact_number' => $profile['contactNumber'] ?? $user->student->contact_number,
            ]);
            $user->student->save();
        }
    }

    /**
     * @param array<string, mixed> $profile
     */
    private function resolveStudentSchoolFromProfile(array $profile, Student $student): ?School
    {
        $schoolId = isset($profile['schoolId']) ? (int) $profile['schoolId'] : (int) ($student->school_id ?? 0);
        if ($schoolId > 0) {
            $school = School::query()->find($schoolId);
            if ($school) {
                return $school;
            }
        }

        $organizationId = isset($profile['organizationId']) ? (int) $profile['organizationId'] : (int) ($student->organization_id ?? 0);
        if ($organizationId > 0) {
            $school = School::query()->where('organization_id', $organizationId)->first();
            if ($school) {
                return $school;
            }
        }

        $subscriptionCode = trim((string) ($profile['schoolSubscriptionCode'] ?? $student->school_subscription_code ?? ''));
        if ($subscriptionCode !== '') {
            $school = School::query()->where('subscription_code', $subscriptionCode)->first();
            if ($school) {
                return $school;
            }
        }

        $schoolName = trim((string) ($profile['schoolName'] ?? $student->school_name ?? ''));
        if ($schoolName !== '') {
            return School::query()->where('institution_name', $schoolName)->first();
        }

        return null;
    }

    private function promoteGuestRole(User $user, string $newRole): void
    {
        $profile = $user->profile ?? [];

        if ($newRole === 'student') {
            if (! $user->student) {
                Student::query()->create([
                    'user_id' => $user->id,
                    'school_name' => $profile['schoolName'] ?? null,
                    'course' => $profile['course'] ?? null,
                    'year_level' => $profile['yearLevel'] ?? null,
                ]);
            }
            $user->role = 'student';
            $user->profile_setup_complete = true;

            return;
        }

        if ($newRole === 'company') {
            if (! $user->company) {
                $sub = Subscription::query()->create([
                    'plan' => 'free',
                    'status' => 'active',
                    'billing_cycle' => 'monthly',
                ]);
                $organization = Organization::query()->create([
                    'name' => (string) ($profile['companyName'] ?? $user->name),
                    'type' => 'company',
                    'subscription_id' => $sub->id,
                    'owner_user_id' => $user->id,
                    'settings' => [
                        'purchase_confirmation_required' => true,
                        'delegated_admin_enabled' => true,
                    ],
                ]);
                $this->tenantRoleService->ensureDefaultRoles($organization);
                Company::query()->create([
                    'user_id' => $user->id,
                    'subscription_id' => $sub->id,
                    'organization_id' => $organization->id,
                    'company_name' => (string) ($profile['companyName'] ?? $user->name),
                ]);
                $this->attachOrganizationMembership($user, $organization, 'company_admin', (string) ($profile['contactPersonTitle'] ?? 'Primary Admin'));
            }
            $user->role = 'company';

            return;
        }

        if ($newRole === 'school') {
            if (! $user->school) {
                $sub = Subscription::query()->create([
                    'plan' => 'free',
                    'status' => 'active',
                    'billing_cycle' => 'monthly',
                ]);
                $organization = Organization::query()->create([
                    'name' => (string) ($profile['institutionName'] ?? $user->name),
                    'type' => 'school',
                    'subscription_id' => $sub->id,
                    'owner_user_id' => $user->id,
                    'settings' => [
                        'purchase_confirmation_required' => true,
                        'delegated_admin_enabled' => true,
                    ],
                ]);
                $this->tenantRoleService->ensureDefaultRoles($organization);
                School::query()->create([
                    'user_id' => $user->id,
                    'subscription_id' => $sub->id,
                    'organization_id' => $organization->id,
                    'institution_name' => (string) ($profile['institutionName'] ?? $user->name),
                    'subscription_code' => strtoupper(substr(bin2hex(random_bytes(3)), 0, 6).'-'.substr((string) time(), -4)),
                ]);
                $this->attachOrganizationMembership($user, $organization, 'school_admin', (string) ($profile['position'] ?? 'Primary Admin'));
            }
            $user->role = 'school';
        }
    }

    private function attachOrganizationMembership(User $user, Organization $organization, string $roleSlug, string $title = ''): void
    {
        $this->tenantRoleService->ensureDefaultRoles($organization);

        $role = Role::query()
            ->where('tenant_id', $organization->id)
            ->where('slug', $roleSlug)
            ->first();

        if (! $role) {
            $role = Role::query()->whereNull('tenant_id')->where('slug', $roleSlug)->first();
        }

        if (! $role) {
            return;
        }

        OrganizationMembership::query()->updateOrCreate(
            [
                'organization_id' => $organization->id,
                'user_id' => $user->id,
            ],
            [
                'role_id' => $role->id,
                'status' => 'active',
                'title' => $title ?: $role->name,
            ]
        );

        $user->forceFill([
            'tenant_id' => $organization->id,
            'role_id' => $role->id,
        ])->save();
    }
}
