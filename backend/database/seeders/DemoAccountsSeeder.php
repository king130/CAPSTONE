<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\ContractType;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Role;
use App\Models\School;
use App\Models\Student;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $env = app()->environment();
        if (! in_array($env, ['local', 'testing'], true)) {
            $this->command?->warn(
                'DemoAccountsSeeder skipped: APP_ENV must be local or testing (current: '.$env.').'
            );

            return;
        }

        $platformAdminRole = Role::query()->where('slug', 'platform_super_admin')->first();
        $companyAdminRole = Role::query()->where('slug', 'company_admin')->first();
        $schoolAdminRole = Role::query()->where('slug', 'school_admin')->first();
        $schoolDepartmentHeadRole = Role::query()->where('slug', 'school_department_head')->first();
        $studentRole = Role::query()->where('slug', 'student_member')->first();

        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@ojtpath.com'],
            [
                'name' => 'System Administrator',
                'password' => 'Admin@2026!',
                'role' => 'admin',
                'platform_role_id' => $platformAdminRole?->id,
                'profile' => [
                    'title' => 'Administrator',
                ],
                'is_active' => true,
                'is_temporary' => false,
                'must_change_password' => false,
                'profile_setup_complete' => true,
            ]
        );

        $companySubscription = $this->ensureDemoPaidSubscription('pro');

        $companyUser = User::query()->updateOrCreate(
            ['email' => 'company@techcorp.com'],
            [
                'name' => 'TechCorp HR',
                'password' => 'Company@2026!',
                'role' => 'company',
                'profile' => [
                    'companyName' => 'TechCorp Solutions',
                    'companyEmail' => 'company@techcorp.com',
                    'contactPersonName' => 'TechCorp HR',
                    'contactPersonEmail' => 'company@techcorp.com',
                    'courses' => ['BS Computer Science', 'BS Information Technology'],
                ],
                'is_active' => true,
                'is_temporary' => false,
                'must_change_password' => false,
                'profile_setup_complete' => true,
            ]
        );

        $companyOrg = Organization::query()->updateOrCreate(
            ['owner_user_id' => $companyUser->id],
            [
                'name' => 'TechCorp Solutions',
                'type' => 'company',
                'subscription_id' => $companySubscription->id,
                'is_active' => true,
                'settings' => [
                    'purchase_confirmation_required' => true,
                    'delegated_admin_enabled' => true,
                ],
            ]
        );

        Company::query()->updateOrCreate(
            ['user_id' => $companyUser->id],
            [
                'subscription_id' => $companySubscription->id,
                'organization_id' => $companyOrg->id,
                'company_name' => 'TechCorp Solutions',
                'company_email' => 'company@techcorp.com',
                'contact_person_name' => 'TechCorp HR',
                'contact_person_email' => 'company@techcorp.com',
                'verification_status' => 'approved',
            ]
        );

        if ($companyAdminRole) {
            OrganizationMembership::query()->updateOrCreate(
                ['organization_id' => $companyOrg->id, 'user_id' => $companyUser->id],
                ['role_id' => $companyAdminRole->id, 'status' => 'active', 'title' => 'Primary Admin']
            );
        }

        $schoolSubscription = $this->ensureDemoPaidSubscription('school-pro');

        $schoolUser = User::query()->updateOrCreate(
            ['email' => 'school@cvsu.edu.ph'],
            [
                'name' => 'CvSU OJT Office',
                'password' => 'School@2026!',
                'role' => 'school',
                'profile' => [
                    'institutionName' => 'Cavite State University',
                    'officialSchoolEmail' => 'school@cvsu.edu.ph',
                    'courses' => ['BS Computer Science', 'BS Information Technology'],
                ],
                'is_active' => true,
                'is_temporary' => false,
                'must_change_password' => false,
                'profile_setup_complete' => true,
            ]
        );

        $schoolOrg = Organization::query()->updateOrCreate(
            ['owner_user_id' => $schoolUser->id],
            [
                'name' => 'Cavite State University',
                'type' => 'school',
                'subscription_id' => $schoolSubscription->id,
                'is_active' => true,
                'settings' => [
                    'purchase_confirmation_required' => true,
                    'delegated_admin_enabled' => true,
                ],
            ]
        );

        $school = School::query()->updateOrCreate(
            ['user_id' => $schoolUser->id],
            [
                'subscription_id' => $schoolSubscription->id,
                'organization_id' => $schoolOrg->id,
                'institution_name' => 'Cavite State University',
                'official_school_email' => 'school@cvsu.edu.ph',
                'subscription_code' => 'CVSU-2026',
                'verification_status' => 'approved',
            ]
        );

        if ($schoolAdminRole) {
            OrganizationMembership::query()->updateOrCreate(
                ['organization_id' => $schoolOrg->id, 'user_id' => $schoolUser->id],
                ['role_id' => $schoolAdminRole->id, 'status' => 'active', 'title' => 'Primary Admin']
            );
        }

        $departmentHead = User::query()->updateOrCreate(
            ['email' => 'depthead@cvsu.edu.ph'],
            [
                'name' => 'CvSU Department Head',
                'password' => 'DeptHead@2026!',
                'role' => 'school',
                'profile' => [
                    'institutionName' => 'Cavite State University',
                    'officialSchoolEmail' => 'depthead@cvsu.edu.ph',
                    'title' => 'Department Head',
                    'courses' => ['BS Computer Science', 'BS Information Technology'],
                ],
                'is_active' => true,
                'is_temporary' => false,
                'must_change_password' => false,
                'profile_setup_complete' => true,
            ]
        );

        if ($schoolDepartmentHeadRole) {
            OrganizationMembership::query()->updateOrCreate(
                ['organization_id' => $schoolOrg->id, 'user_id' => $departmentHead->id],
                ['role_id' => $schoolDepartmentHeadRole->id, 'status' => 'active', 'title' => 'Department Head']
            );
        }

        User::query()->updateOrCreate(
            ['email' => 'student@cvsu.edu.ph'],
            [
                'name' => 'CvSU Student',
                'password' => 'Student@2026!',
                'role' => 'student',
                'profile' => [
                    'schoolName' => 'Cavite State University',
                    'course' => 'BS Computer Science',
                    'yearLevel' => '4th Year',
                    'schoolId' => (string) $school->id,
                ],
                'is_active' => true,
                'is_temporary' => false,
                'must_change_password' => false,
                'profile_setup_complete' => true,
            ]
        );

        $studentUser = User::query()->where('email', 'student@cvsu.edu.ph')->firstOrFail();

        Student::query()->updateOrCreate(
            ['user_id' => $studentUser->id],
            [
                'school_id' => $school->id,
                'organization_id' => $schoolOrg->id,
                'student_id_number' => '2026-0001',
                'school_name' => 'Cavite State University',
                'school_subscription_code' => 'CVSU-2026',
                'course' => 'BS Computer Science',
                'year_level' => '4th Year',
            ]
        );

        if ($studentRole) {
            OrganizationMembership::query()->updateOrCreate(
                ['organization_id' => $schoolOrg->id, 'user_id' => $studentUser->id],
                ['role_id' => $studentRole->id, 'status' => 'active', 'title' => 'Student']
            );
        }

        // Phase B: demo student course must be configured or apply is blocked.
        \App\Models\SchoolCourseRequirement::query()->updateOrCreate(
            [
                'school_id' => $school->id,
                'course_name_key' => \App\Models\SchoolCourseRequirement::courseNameKey('BS Computer Science'),
            ],
            [
                'course_name' => 'BS Computer Science',
                'required_hours' => 486,
                'eligible_year_levels' => ['3rd Year', '4th Year'],
            ]
        );
        \App\Models\SchoolCourseRequirement::query()->updateOrCreate(
            [
                'school_id' => $school->id,
                'course_name_key' => \App\Models\SchoolCourseRequirement::courseNameKey('BS Information Technology'),
            ],
            [
                'course_name' => 'BS Information Technology',
                'required_hours' => 500,
                'eligible_year_levels' => ['3rd Year', '4th Year'],
            ]
        );

        $this->seedDemoAgreementTemplates($schoolUser, $schoolOrg, $companyUser, $companyOrg);
        $this->seedMunicipalHallDemo($companySubscription);
    }

    /**
     * Local-only Cavite Municipal Hall demo host (reuses company workspace / host profile).
     */
    private function seedMunicipalHallDemo(Subscription $companySubscription): void
    {
        $municipalUser = User::query()->updateOrCreate(
            ['email' => 'municipal@naic.gov.ph'],
            [
                'name' => 'Naic Municipal Hall Admin',
                'password' => 'Municipal@2026!',
                'role' => 'municipal_hall',
                'profile' => [
                    'officialName' => 'Municipality of Naic — Municipal Hall',
                    'lguType' => 'municipality',
                    'representativeName' => 'Naic Municipal Hall Admin',
                    'representativePosition' => 'Municipal Administrator',
                    'locationArea' => 'Naic',
                    'cityMunicipality' => 'Naic',
                    'companyEmail' => 'municipal@naic.gov.ph',
                    'contactNumber' => '09170001111',
                ],
                'is_active' => true,
                'is_temporary' => false,
                'must_change_password' => false,
                'profile_setup_complete' => true,
            ]
        );

        $municipalOrg = Organization::query()->updateOrCreate(
            ['owner_user_id' => $municipalUser->id],
            [
                'name' => 'Municipality of Naic — Municipal Hall',
                'type' => 'municipal_hall',
                'subscription_id' => $companySubscription->id,
                'is_active' => true,
                'settings' => [
                    'purchase_confirmation_required' => true,
                    'delegated_admin_enabled' => true,
                    'city' => 'Naic',
                    'address' => 'Municipal Hall, Naic, Cavite, Philippines',
                ],
            ]
        );

        app(\App\Services\TenantRoleService::class)->ensureDefaultRoles($municipalOrg);
        $municipalAdminRole = Role::query()
            ->where('tenant_id', $municipalOrg->id)
            ->where('slug', 'company_admin')
            ->first();

        Company::query()->updateOrCreate(
            ['user_id' => $municipalUser->id],
            [
                'subscription_id' => $companySubscription->id,
                'organization_id' => $municipalOrg->id,
                'company_name' => 'Municipality of Naic — Municipal Hall',
                'company_type' => 'municipal_hall',
                'lgu_type' => 'municipality',
                'company_address' => 'Municipal Hall, Naic, Cavite, Philippines',
                'city_municipality' => 'Naic',
                'company_email' => 'municipal@naic.gov.ph',
                'industry_type' => 'Local Government',
                'contact_person_name' => 'Naic Municipal Hall Admin',
                'contact_person_title' => 'Municipal Administrator',
                'contact_person_email' => 'municipal@naic.gov.ph',
                'company_contact_number' => '09170001111',
                'verification_status' => 'approved',
            ]
        );

        if ($municipalAdminRole) {
            OrganizationMembership::query()->updateOrCreate(
                ['organization_id' => $municipalOrg->id, 'user_id' => $municipalUser->id],
                ['role_id' => $municipalAdminRole->id, 'status' => 'active', 'title' => 'Primary Admin']
            );
            $municipalUser->forceFill([
                'tenant_id' => $municipalOrg->id,
                'role_id' => $municipalAdminRole->id,
            ])->save();
        }
    }

    /**
     * Ready-made organization custom agreement templates for demo school/company accounts.
     */
    private function seedDemoAgreementTemplates(
        User $schoolUser,
        Organization $schoolOrg,
        User $companyUser,
        Organization $companyOrg
    ): void {
        $ojtMoa = ContractType::query()->where('slug', 'ojt-moa')->whereNull('organization_id')->first();

        $schoolTemplates = [
            [
                'name' => 'CvSU Department OJT Endorsement',
                'slug' => 'cvsu-department-ojt-endorsement',
                'description' => 'Department-level endorsement template for CvSU OJT placements with host partners.',
                'fields_schema' => [
                    ['key' => 'department', 'label' => 'Department', 'type' => 'select', 'required' => true, 'options' => ['Computer Studies', 'Information Technology', 'Education', 'Business', 'Engineering']],
                    ['key' => 'programs', 'label' => 'Programs Covered', 'type' => 'multiselect', 'required' => true, 'options' => ['BS Computer Science', 'BS Information Technology', 'BS Information Systems']],
                    ['key' => 'student_count', 'label' => 'Students Endorsed', 'type' => 'number', 'required' => true],
                    ['key' => 'required_hours', 'label' => 'Required Hours', 'type' => 'number', 'required' => true],
                    ['key' => 'host_city', 'label' => 'Preferred Host City (Cavite)', 'type' => 'select', 'required' => false, 'options' => ['Imus City', 'Dasmarinas City', 'Bacoor City', 'General Trias City', 'Trece Martires City', 'Silang', 'Any in Cavite']],
                    ['key' => 'coordinator_name', 'label' => 'OJT Coordinator', 'type' => 'text', 'required' => true],
                    ['key' => 'coordinator_email', 'label' => 'Coordinator Email', 'type' => 'email', 'required' => true],
                    ['key' => 'special_conditions', 'label' => 'Special Conditions', 'type' => 'textarea', 'required' => false],
                ],
                'default_values' => ['required_hours' => 486],
                'sort_order' => 10,
            ],
            [
                'name' => 'CvSU Partner Monitoring Checklist',
                'slug' => 'cvsu-partner-monitoring-checklist',
                'description' => 'Lightweight monitoring agreement covering visit schedule, log review, and midterm evaluation.',
                'fields_schema' => [
                    ['key' => 'monitoring_mode', 'label' => 'Monitoring Mode', 'type' => 'select', 'required' => true, 'options' => ['On-site visit', 'Online check-in', 'Hybrid']],
                    ['key' => 'visit_frequency', 'label' => 'Visit / Check-in Frequency', 'type' => 'text', 'required' => true, 'placeholder' => 'e.g. every 2 weeks'],
                    ['key' => 'log_review_day', 'label' => 'Preferred Log Review Day', 'type' => 'text', 'required' => false],
                    ['key' => 'midterm_evaluation_on', 'label' => 'Midterm Evaluation Date', 'type' => 'date', 'required' => false],
                    ['key' => 'school_monitor', 'label' => 'School Monitor', 'type' => 'text', 'required' => true],
                    ['key' => 'host_liaison', 'label' => 'Host Liaison', 'type' => 'text', 'required' => true],
                ],
                'default_values' => [],
                'sort_order' => 20,
            ],
        ];

        foreach ($schoolTemplates as $template) {
            ContractType::query()->updateOrCreate(
                [
                    'slug' => $template['slug'],
                    'organization_id' => $schoolOrg->id,
                ],
                [
                    'name' => $template['name'],
                    'scope' => 'organization',
                    'organization_type' => 'school',
                    'created_by_user_id' => $schoolUser->id,
                    'base_contract_type_id' => $ojtMoa?->id,
                    'description' => $template['description'],
                    'fields_schema' => $template['fields_schema'],
                    'default_values' => $template['default_values'],
                    'settings' => ['allow_templates' => true, 'demo' => true],
                    'is_active' => true,
                    'sort_order' => $template['sort_order'],
                ]
            );
        }

        $companyTemplates = [
            [
                'name' => 'TechCorp Intern Host Agreement',
                'slug' => 'techcorp-intern-host-agreement',
                'description' => 'Company-ready host template for accepting school-endorsed interns at TechCorp.',
                'fields_schema' => [
                    ['key' => 'accepted_programs', 'label' => 'Accepted Programs', 'type' => 'multiselect', 'required' => true, 'options' => ['BS Computer Science', 'BS Information Technology', 'BS Information Systems']],
                    ['key' => 'slots_offered', 'label' => 'Slots Offered', 'type' => 'number', 'required' => true],
                    ['key' => 'work_setup', 'label' => 'Work Setup', 'type' => 'select', 'required' => true, 'options' => ['On-site', 'Hybrid', 'Remote']],
                    ['key' => 'office_city', 'label' => 'Office City (Cavite)', 'type' => 'select', 'required' => true, 'options' => ['Imus City', 'Dasmarinas City', 'Bacoor City', 'General Trias City', 'Trece Martires City']],
                    ['key' => 'mentor_name', 'label' => 'Assigned Mentor', 'type' => 'text', 'required' => true],
                    ['key' => 'mentor_email', 'label' => 'Mentor Email', 'type' => 'email', 'required' => true],
                    ['key' => 'start_on', 'label' => 'Preferred Start Date', 'type' => 'date', 'required' => false],
                    ['key' => 'onboarding_notes', 'label' => 'Onboarding Notes', 'type' => 'textarea', 'required' => false],
                ],
                'default_values' => ['slots_offered' => 5, 'work_setup' => 'Hybrid'],
                'sort_order' => 10,
            ],
            [
                'name' => 'TechCorp Short Immersion Agreement',
                'slug' => 'techcorp-short-immersion-agreement',
                'description' => 'Short exposure / immersion template for 1–4 week partner school visits.',
                'fields_schema' => [
                    ['key' => 'immersion_length', 'label' => 'Immersion Length', 'type' => 'select', 'required' => true, 'options' => ['1 week', '2 weeks', '3 weeks', '4 weeks']],
                    ['key' => 'focus_area', 'label' => 'Focus Area', 'type' => 'select', 'required' => true, 'options' => ['Software development', 'QA / testing', 'IT support', 'Data / analytics', 'General IT']],
                    ['key' => 'max_participants', 'label' => 'Max Participants', 'type' => 'number', 'required' => true],
                    ['key' => 'immersion_start', 'label' => 'Immersion Start', 'type' => 'date', 'required' => true],
                    ['key' => 'host_contact', 'label' => 'Host Contact', 'type' => 'text', 'required' => true],
                    ['key' => 'host_email', 'label' => 'Host Email', 'type' => 'email', 'required' => true],
                ],
                'default_values' => ['max_participants' => 10],
                'sort_order' => 20,
            ],
        ];

        foreach ($companyTemplates as $template) {
            ContractType::query()->updateOrCreate(
                [
                    'slug' => $template['slug'],
                    'organization_id' => $companyOrg->id,
                ],
                [
                    'name' => $template['name'],
                    'scope' => 'organization',
                    'organization_type' => 'company',
                    'created_by_user_id' => $companyUser->id,
                    'base_contract_type_id' => $ojtMoa?->id,
                    'description' => $template['description'],
                    'fields_schema' => $template['fields_schema'],
                    'default_values' => $template['default_values'],
                    'settings' => ['allow_templates' => true, 'demo' => true],
                    'is_active' => true,
                    'sort_order' => $template['sort_order'],
                ]
            );
        }
    }

    /**
     * Demo orgs stay fully writable: paid Standard with a long period end (local/testing only).
     */
    private function ensureDemoPaidSubscription(string $legacyKey): Subscription
    {
        $subscription = Subscription::query()
            ->where('plan', $legacyKey)
            ->where('status', 'active')
            ->first();

        if (! $subscription) {
            $subscription = Subscription::query()->create([
                'plan' => 'standard',
                'status' => 'active',
                'billing_cycle' => 'monthly',
                'starts_at' => now()->toDateString(),
                'ends_at' => now()->addYears(5)->toDateString(),
                'access_note' => 'Demo seeder: long-lived paid plan for local demos.',
            ]);
        } else {
            $subscription->forceFill([
                'plan' => 'standard',
                'status' => 'active',
                'billing_cycle' => 'monthly',
                'starts_at' => $subscription->starts_at ?: now()->toDateString(),
                'ends_at' => now()->addYears(5)->toDateString(),
                'access_note' => 'Demo seeder: long-lived paid plan for local demos.',
            ])->save();
        }

        return $subscription->fresh();
    }
}
