<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Municipal Hall support: LGU fields on companies (host operational profile)
 * so existing company_id FKs (internships, applications, DTR) continue to work.
 * organizations.type may be 'municipal_hall'; existing company/school rows unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (! Schema::hasColumn('companies', 'lgu_type')) {
                $table->string('lgu_type', 32)->nullable()->after('company_type');
            }
            if (! Schema::hasColumn('companies', 'city_municipality')) {
                $table->string('city_municipality', 120)->nullable()->after('company_address');
            }
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (Schema::hasColumn('companies', 'city_municipality')) {
                $table->dropColumn('city_municipality');
            }
            if (Schema::hasColumn('companies', 'lgu_type')) {
                $table->dropColumn('lgu_type');
            }
        });
    }
};
