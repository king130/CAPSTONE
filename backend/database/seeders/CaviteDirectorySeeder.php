<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Internship;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Role;
use App\Models\School;
use App\Models\Student;
use App\Models\Subscription;
use App\Models\User;
use App\Services\TenantRoleService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CaviteDirectorySeeder extends Seeder
{
    private const SCHOOL_PASSWORD = 'School@2026!';

    private const COMPANY_PASSWORD = 'Company@2026!';

    private const STUDENT_PASSWORD = 'Student@2026!';

    private const ADMIN_PASSWORD = 'Admin@2026!';

    /** @var array<string, int|null> */
    private $roleIds = [];

    public function run(): void
    {
        $this->roleIds = [
            'platform_super_admin' => Role::query()->where('slug', 'platform_super_admin')->whereNull('tenant_id')->value('id'),
            'school_admin' => Role::query()->where('slug', 'school_admin')->whereNull('tenant_id')->value('id'),
            'company_admin' => Role::query()->where('slug', 'company_admin')->whereNull('tenant_id')->value('id'),
            'student_member' => Role::query()->where('slug', 'student_member')->whereNull('tenant_id')->value('id'),
        ];

        $this->ensurePlatformAdmin();
        $this->seedSchools();
        $this->seedCompanies();
    }

    private function ensurePlatformAdmin(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'admin@ojtpath.com'],
            [
                'name' => 'System Administrator',
                'password' => self::ADMIN_PASSWORD,
                'role' => 'admin',
                'platform_role_id' => $this->roleIds['platform_super_admin'],
                'profile' => ['title' => 'Administrator'],
                'is_active' => true,
                'is_temporary' => false,
                'must_change_password' => false,
                'profile_setup_complete' => true,
            ]
        );
    }

    private function seedSchools(): void
    {
        $schools = [
            [
                'code' => 'S001',
                'name' => 'De La Salle University-Dasmariñas',
                'type' => 'University',
                'city' => 'Dasmariñas',
                'address' => 'Dasmariñas, Cavite',
                'website' => 'https://www.dlsud.edu.ph',
                'email' => 'ojt@dlsud.edu.ph',
                'contact' => '+63 46 481 1900',
                'subscription_code' => 'DLSUD-2026',
            ],
            [
                'code' => 'S002',
                'name' => 'Cavite State University',
                'type' => 'University',
                'city' => 'Indang',
                'address' => 'Indang, Cavite',
                'website' => 'https://cvsu.edu.ph',
                'email' => 'school@cvsu.edu.ph',
                'contact' => '+63 46 862 0855',
                'subscription_code' => 'CVSU-2026',
            ],
            [
                'code' => 'S003',
                'name' => 'Polytechnic University of the Philippines - Cavite',
                'type' => 'University',
                'city' => 'General Trias',
                'address' => 'General Trias, Cavite',
                'website' => 'https://www.pup.edu.ph',
                'email' => 'ojt.cavite@pup.edu.ph',
                'contact' => '+63 46 437 2176',
                'subscription_code' => 'PUPCV-2026',
            ],
            [
                'code' => 'S004',
                'name' => 'Emilio Aguinaldo College - Cavite',
                'type' => 'University',
                'city' => 'Dasmariñas',
                'address' => 'Dasmariñas, Cavite',
                'website' => 'https://www.eac.edu.ph',
                'email' => 'ojt@eac.edu.ph',
                'contact' => '+63 46 416 4332',
                'subscription_code' => 'EACCV-2026',
            ],
            [
                'code' => 'S005',
                'name' => 'Lyceum of the Philippines University - Cavite',
                'type' => 'University',
                'city' => 'General Trias',
                'address' => 'General Trias, Cavite',
                'website' => 'https://cavite.lpu.edu.ph',
                'email' => 'ojt@cavite.lpu.edu.ph',
                'contact' => '+63 46 481 1400',
                'subscription_code' => 'LPUCV-2026',
            ],
        ];

        $studentFirstNames = ['Ana', 'Ben', 'Carla', 'Diego', 'Elena', 'Felix', 'Giselle', 'Hugo', 'Ivy', 'Jonas'];
        $courses = [
            'BS Information Technology',
            'BS Computer Science',
            'BS Computer Engineering',
            'BS Electronics Engineering',
        ];

        foreach ($schools as $index => $row) {
            $subscription = Subscription::query()->firstOrCreate(
                ['plan' => 'school-pro', 'status' => 'active', 'billing_cycle' => 'monthly'],
                ['starts_at' => now()->toDateString()]
            );

            $user = User::query()->updateOrCreate(
                ['email' => $row['email']],
                [
                    'name' => $row['name'].' OJT Office',
                    'password' => self::SCHOOL_PASSWORD,
                    'role' => 'school',
                    'profile' => [
                        'institutionName' => $row['name'],
                        'institutionType' => $row['type'],
                        'officialSchoolEmail' => $row['email'],
                        'schoolAddress' => $row['address'],
                        'schoolContactNumber' => $row['contact'],
                        'website' => $row['website'],
                        'city' => $row['city'],
                        'demoCode' => $row['code'],
                        'courses' => $courses,
                    ],
                    'is_active' => true,
                    'is_temporary' => false,
                    'must_change_password' => false,
                    'profile_setup_complete' => true,
                ]
            );

            $organization = Organization::query()->updateOrCreate(
                ['owner_user_id' => $user->id],
                [
                    'name' => $row['name'],
                    'type' => 'school',
                    'subscription_id' => $subscription->id,
                    'is_active' => true,
                    'tagline' => $row['type'].' · '.$row['city'].', Cavite',
                    'description' => $row['name'].' partners with Cavite host companies for OJT placements through OJT Intern Path.',
                    'address' => $row['address'],
                    'city' => $row['city'],
                    'website' => $row['website'],
                    'industry' => 'Higher Education',
                    'settings' => [
                        'purchase_confirmation_required' => true,
                        'delegated_admin_enabled' => true,
                        'demo_code' => $row['code'],
                    ],
                ]
            );

            app(TenantRoleService::class)->ensureDefaultRoles($organization);

            $school = School::query()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'subscription_id' => $subscription->id,
                    'organization_id' => $organization->id,
                    'institution_name' => $row['name'],
                    'institution_type' => $row['type'],
                    'official_school_email' => $row['email'],
                    'school_contact_number' => $row['contact'],
                    'school_address' => $row['address'],
                    'subscription_code' => $row['subscription_code'],
                    'verification_status' => 'approved',
                ]
            );

            $this->attachMembership($organization->id, $user->id, 'school_admin', 'Primary Admin');

            // 4 demo students per school so school dashboards are not empty.
            for ($n = 1; $n <= 4; $n++) {
                $studentEmail = strtolower($row['code']).'.student'.$n.'@ojtpath.demo';
                $first = $studentFirstNames[(($index * 4) + $n - 1) % count($studentFirstNames)];
                $course = $courses[($n - 1) % count($courses)];
                $year = $n <= 2 ? '3rd Year' : '4th Year';

                $studentUser = User::query()->updateOrCreate(
                    ['email' => $studentEmail],
                    [
                        'name' => $first.' '.$row['code'].$n,
                        'password' => self::STUDENT_PASSWORD,
                        'role' => 'student',
                        'profile' => [
                            'schoolName' => $row['name'],
                            'course' => $course,
                            'yearLevel' => $year,
                            'schoolId' => (string) $school->id,
                            'contactNumber' => '+63 9'.str_pad((string) (100000000 + ($index * 10) + $n), 9, '0', STR_PAD_LEFT),
                        ],
                        'is_active' => true,
                        'is_temporary' => false,
                        'must_change_password' => false,
                        'profile_setup_complete' => true,
                    ]
                );

                Student::query()->updateOrCreate(
                    ['user_id' => $studentUser->id],
                    [
                        'school_id' => $school->id,
                        'organization_id' => $organization->id,
                        'student_id_number' => sprintf('%s-%04d', $row['code'], $n),
                        'school_name' => $row['name'],
                        'school_subscription_code' => $row['subscription_code'],
                        'course' => $course,
                        'year_level' => $year,
                    ]
                );

                $this->attachMembership($organization->id, $studentUser->id, 'student_member', 'Student');
            }
        }
    }

    private function seedCompanies(): void
    {
        $companies = [
            [
                'code' => 'C001',
                'name' => 'Analog Devices Philippines',
                'industry' => 'Semiconductor/Technology',
                'city' => 'General Trias',
                'address' => 'General Trias, Cavite',
                'roles' => ['IT Intern', 'Software Intern', 'Engineering Intern', 'QA / Test Engineering Intern', 'IT Helpdesk Intern'],
                'programs' => ['BS Information Technology', 'BS Computer Science', 'BS Computer Engineering', 'BS Electronics Engineering'],
            ],
            [
                'code' => 'C002',
                'name' => 'onsemi Philippines',
                'industry' => 'Semiconductor/Technology',
                'city' => 'Carmona',
                'address' => 'Carmona, Cavite',
                'roles' => ['IT Intern', 'Engineering Intern', 'Data Intern', 'Data Analytics Intern', 'Network Support Intern'],
                'programs' => ['BS Information Technology', 'BS Computer Science', 'BS Computer Engineering', 'BS Electronics Engineering'],
            ],
            [
                'code' => 'C003',
                'name' => 'Rohm Mechatech Philippines',
                'industry' => 'Electronics Manufacturing',
                'city' => 'Cavite',
                'address' => 'Cavite',
                'roles' => ['IT Intern', 'Engineering Intern', 'Production Intern', 'Process Engineering Intern', 'Maintenance Planning Intern'],
                'programs' => ['BS Information Technology', 'BS Computer Science', 'BS Computer Engineering', 'BS Electronics Engineering', 'BS Industrial Engineering'],
            ],
            [
                'code' => 'C004',
                'name' => 'GP Synergia Technologies',
                'industry' => 'Technology/Engineering',
                'city' => 'Cavite',
                'address' => 'Cavite',
                'roles' => ['IT Intern', 'Technical Intern', 'Engineering Intern', 'Web Development Intern', 'Systems Documentation Intern'],
                'programs' => ['BS Information Technology', 'BS Computer Science', 'BS Computer Engineering', 'BS Electronics Engineering'],
            ],
            [
                'code' => 'C005',
                'name' => 'Suerte Steel Corporation',
                'industry' => 'Manufacturing',
                'city' => 'Cavite',
                'address' => 'Cavite',
                'roles' => ['IT Intern', 'Engineering Intern', 'Production Intern', 'Safety & Compliance Intern'],
                'programs' => ['BS Information Technology', 'BS Computer Science', 'BS Industrial Engineering', 'BS Mechanical Engineering'],
            ],
            [
                'code' => 'C006',
                'name' => 'Columbia Motors Corporation',
                'industry' => 'Automotive',
                'city' => 'Cavite',
                'address' => 'Cavite',
                'roles' => ['IT Intern', 'Automotive Intern', 'Admin Intern', 'Dealership Admin Intern', 'Workshop IT Support Intern'],
                'programs' => ['BS Information Technology', 'BS Computer Science', 'BS Automotive Engineering', 'BS Mechanical Engineering', 'BS Business Administration'],
            ],
            [
                'code' => 'C007',
                'name' => 'Cavite Biofuel Producers',
                'industry' => 'Energy/Manufacturing',
                'city' => 'Cavite',
                'address' => 'Cavite',
                'roles' => ['IT Intern', 'Engineering Intern', 'Operations Intern'],
                'programs' => ['BS Information Technology', 'BS Computer Science', 'BS Chemical Engineering', 'BS Industrial Engineering'],
            ],
            [
                'code' => 'C008',
                'name' => 'HRR Equipment Corporation',
                'industry' => 'Engineering/Manufacturing',
                'city' => 'Cavite',
                'address' => 'Cavite',
                'roles' => ['IT Intern', 'Engineering Intern', 'Technical Intern'],
                'programs' => ['BS Information Technology', 'BS Computer Science', 'BS Mechanical Engineering', 'BS Electrical Engineering'],
            ],
            [
                'code' => 'C009',
                'name' => 'San Miguel Yamamura',
                'industry' => 'Manufacturing',
                'city' => 'Cavite',
                'address' => 'Cavite',
                'roles' => ['IT Intern', 'Production Intern', 'Operations Intern', 'Inventory Control Intern'],
                'programs' => ['BS Information Technology', 'BS Computer Science', 'BS Industrial Engineering', 'BS Business Administration'],
            ],
            [
                'code' => 'C010',
                'name' => 'Robtek Design Engineering',
                'industry' => 'Engineering',
                'city' => 'Cavite',
                'address' => 'Cavite',
                'roles' => ['IT Intern', 'Engineering Intern', 'CAD Intern'],
                'programs' => ['BS Information Technology', 'BS Computer Science', 'BS Mechanical Engineering', 'BS Civil Engineering'],
            ],
            [
                'code' => 'C011',
                'name' => 'Circuit Solutions',
                'industry' => 'Electronics',
                'city' => 'Cavite',
                'address' => 'Cavite',
                'roles' => ['Software Intern', 'IT Intern', 'Electronics Intern', 'Embedded Software Intern', 'UI/UX Design Intern'],
                'programs' => ['BS Information Technology', 'BS Computer Science', 'BS Computer Engineering', 'BS Electronics Engineering'],
            ],
        ];

        foreach ($companies as $index => $row) {
            $email = strtolower($row['code']).'.hr@ojtpath.demo';
            $subscription = Subscription::query()->firstOrCreate(
                ['plan' => 'pro', 'status' => 'active', 'billing_cycle' => 'monthly'],
                ['starts_at' => now()->toDateString()]
            );

            $user = User::query()->updateOrCreate(
                ['email' => $email],
                [
                    'name' => $row['name'].' HR',
                    'password' => self::COMPANY_PASSWORD,
                    'role' => 'company',
                    'profile' => [
                        'companyName' => $row['name'],
                        'companyEmail' => $email,
                        'companyAddress' => $row['address'],
                        'industryType' => $row['industry'],
                        'contactPersonName' => $row['name'].' HR',
                        'contactPersonEmail' => $email,
                        'companyContactNumber' => '+63 46 '.str_pad((string) (4000000 + $index), 7, '0', STR_PAD_LEFT),
                        'city' => $row['city'],
                        'demoCode' => $row['code'],
                        'courses' => $row['programs'],
                    ],
                    'is_active' => true,
                    'is_temporary' => false,
                    'must_change_password' => false,
                    'profile_setup_complete' => true,
                ]
            );

            $organization = Organization::query()->updateOrCreate(
                ['owner_user_id' => $user->id],
                [
                    'name' => $row['name'],
                    'type' => 'company',
                    'subscription_id' => $subscription->id,
                    'is_active' => true,
                    'tagline' => $row['industry'].' · '.$row['city'],
                    'description' => $row['name'].' hosts OJT interns in Cavite across IT, engineering, and operations tracks.',
                    'address' => $row['address'],
                    'city' => $row['city'],
                    'website' => null,
                    'industry' => $row['industry'],
                    'perks' => ['Mentor support', 'Certificate of completion', 'On-site Cavite placement'],
                    'settings' => [
                        'purchase_confirmation_required' => true,
                        'delegated_admin_enabled' => true,
                        'demo_code' => $row['code'],
                    ],
                ]
            );

            app(TenantRoleService::class)->ensureDefaultRoles($organization);

            $company = Company::query()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'subscription_id' => $subscription->id,
                    'organization_id' => $organization->id,
                    'company_name' => $row['name'],
                    'industry_type' => $row['industry'],
                    'company_address' => $row['address'],
                    'company_email' => $email,
                    'contact_person_name' => $row['name'].' HR',
                    'contact_person_email' => $email,
                    'company_contact_number' => '+63 46 '.str_pad((string) (4000000 + $index), 7, '0', STR_PAD_LEFT),
                    'internship_description' => 'Open OJT roles for Cavite partner schools.',
                    'verification_status' => 'approved',
                ]
            );

            $this->attachMembership($organization->id, $user->id, 'company_admin', 'Primary Admin');

            foreach ($row['roles'] as $roleTitle) {
                $slugKey = Str::slug($row['code'].'-'.$roleTitle);
                Internship::query()->updateOrCreate(
                    [
                        'company_id' => $company->id,
                        'title' => $roleTitle,
                    ],
                    [
                        'company_name' => $row['name'],
                        'industry' => $row['industry'],
                        'host_type' => 'company',
                        'host_name' => $row['name'],
                        'description' => $roleTitle.' placement at '.$row['name'].' ('.$row['city'].').',
                        'location' => $row['address'],
                        'city' => $row['city'],
                        'work_setup' => 'On-site',
                        'type' => 'OJT',
                        'duration' => '486 hours',
                        'slots_available' => 5,
                        'status' => 'active',
                        'eligible_courses' => $row['programs'],
                        'allowance' => 'As agreed with host',
                        'perks' => ['Certificate', 'Mentor', 'Hands-on projects'],
                        'start_date' => now()->addWeeks(2)->toDateString(),
                        'application_deadline' => now()->addMonths(2)->toDateString(),
                        'requires_resume' => true,
                        'approval_status' => 'approved',
                        'required_skills' => ['Communication', 'Teamwork', 'Basic technical skills'],
                        'application_instructions' => 'Apply through OJT Intern Path with your school profile and resume.',
                        'contact_info' => $email,
                        // Stable marker for idempotent re-seeds without a dedicated column.
                        'tasks' => 'demo:'.$slugKey,
                    ]
                );
            }
        }
    }

    private function attachMembership(int $organizationId, int $userId, string $roleSlug, string $title): void
    {
        $roleId = $this->roleIds[$roleSlug] ?? null;
        if (! $roleId) {
            return;
        }

        OrganizationMembership::query()->updateOrCreate(
            ['organization_id' => $organizationId, 'user_id' => $userId],
            ['role_id' => $roleId, 'status' => 'active', 'title' => $title]
        );
    }
}
