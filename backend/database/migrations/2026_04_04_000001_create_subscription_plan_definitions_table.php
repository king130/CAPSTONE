<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plan_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('school_price')->default(0);
            $table->unsignedInteger('company_price')->default(0);
            $table->json('school_features')->nullable();
            $table->json('company_features')->nullable();
            $table->unsignedInteger('school_coordinators_limit')->default(1);
            $table->unsignedInteger('school_students_limit')->default(5);
            $table->unsignedInteger('company_accounts_limit')->default(1);
            $table->unsignedInteger('company_internships_limit')->default(3);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::table('subscription_plan_definitions')->insert([
            [
                'slug' => 'free',
                'name' => 'Free',
                'description' => 'Perfect for getting started',
                'school_price' => 0,
                'company_price' => 0,
                'school_features' => json_encode([
                    '1 school coordinator account',
                    'Up to 5 student accounts',
                    'Basic student management',
                    'Simple reporting dashboard',
                    'Email support',
                    'Basic internship matching',
                ]),
                'company_features' => json_encode([
                    '1 company account',
                    'Post up to 3 internships',
                    'Basic applicant management',
                    'Simple reporting dashboard',
                    'Email support',
                    'Basic candidate matching',
                ]),
                'school_coordinators_limit' => 1,
                'school_students_limit' => 5,
                'company_accounts_limit' => 1,
                'company_internships_limit' => 3,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'slug' => 'standard',
                'name' => 'Standard',
                'description' => 'Ideal for growing organizations',
                'school_price' => 1999,
                'company_price' => 2499,
                'school_features' => json_encode([
                    '5 school coordinator accounts',
                    'Up to 100 student accounts',
                    'Advanced student analytics',
                    'Bulk student management',
                    'Priority email and chat support',
                    'Custom reports and exports',
                    'Advanced matching algorithms',
                ]),
                'company_features' => json_encode([
                    '5 company accounts',
                    'Unlimited internship postings',
                    'Advanced applicant tracking',
                    'Analytics dashboard',
                    'Priority email and chat support',
                    'Integration support',
                    'Advanced filtering and search',
                ]),
                'school_coordinators_limit' => 5,
                'school_students_limit' => 100,
                'company_accounts_limit' => 5,
                'company_internships_limit' => 999,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'slug' => 'premium',
                'name' => 'Premium',
                'description' => 'Complete enterprise solution',
                'school_price' => 3999,
                'company_price' => 4999,
                'school_features' => json_encode([
                    'Unlimited coordinator accounts',
                    'Unlimited student accounts',
                    'Advanced analytics dashboard',
                    'Custom integrations and API access',
                    'Dedicated account manager',
                    'White-label options',
                    'Priority phone support',
                ]),
                'company_features' => json_encode([
                    'Unlimited company accounts',
                    'Unlimited internship postings',
                    'Advanced analytics and reporting',
                    'Custom integrations and API access',
                    'Dedicated account manager',
                    'Custom branding options',
                    'Priority phone support',
                ]),
                'school_coordinators_limit' => 999,
                'school_students_limit' => 999,
                'company_accounts_limit' => 999,
                'company_internships_limit' => 999,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_plan_definitions');
    }
};
