<?php

namespace Database\Seeders;

use App\Models\Company;
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
        $platformAdminRole = Role::query()->where('slug', 'platform_super_admin')->first();
        $companyAdminRole = Role::query()->where('slug', 'company_admin')->first();
        $schoolAdminRole = Role::query()->where('slug', 'school_admin')->first();
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

        $companySubscription = Subscription::query()->firstOrCreate(
            ['plan' => 'pro', 'status' => 'active', 'billing_cycle' => 'monthly'],
            ['starts_at' => now()->toDateString()]
        );

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

        $schoolSubscription = Subscription::query()->firstOrCreate(
            ['plan' => 'school-pro', 'status' => 'active', 'billing_cycle' => 'monthly'],
            ['starts_at' => now()->toDateString()]
        );

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
    }
}
