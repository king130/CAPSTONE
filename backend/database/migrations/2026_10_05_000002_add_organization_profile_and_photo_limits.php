<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('tagline', 255)->nullable()->after('name');
            $table->text('description')->nullable()->after('tagline');
            $table->string('address', 500)->nullable()->after('description');
            $table->string('city', 255)->nullable()->after('address');
            $table->string('website', 255)->nullable()->after('city');
            $table->string('industry', 255)->nullable()->after('website');
            $table->json('perks')->nullable()->after('industry');
        });

        Schema::table('subscription_plan_definitions', function (Blueprint $table) {
            $table->unsignedInteger('organization_photos_limit')->default(3)->after('company_internships_limit');
        });

        DB::table('subscription_plan_definitions')->where('slug', 'free')->update(['organization_photos_limit' => 3]);
        DB::table('subscription_plan_definitions')->where('slug', 'standard')->update(['organization_photos_limit' => 10]);
        DB::table('subscription_plan_definitions')->where('slug', 'premium')->update(['organization_photos_limit' => 30]);
    }

    public function down(): void
    {
        Schema::table('subscription_plan_definitions', function (Blueprint $table) {
            $table->dropColumn('organization_photos_limit');
        });

        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn([
                'tagline',
                'description',
                'address',
                'city',
                'website',
                'industry',
                'perks',
            ]);
        });
    }
};
