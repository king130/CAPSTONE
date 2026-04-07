<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('internships')) {
            return;
        }

        Schema::table('internships', function (Blueprint $table) {
            if (! Schema::hasColumn('internships', 'school_id')) {
                $table->foreignId('school_id')->nullable()->after('company_id')->constrained()->nullOnDelete();
            }

            if (! Schema::hasColumn('internships', 'host_type')) {
                $table->string('host_type')->default('company')->after('company_name');
            }

            if (! Schema::hasColumn('internships', 'host_name')) {
                $table->string('host_name')->nullable()->after('host_type');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('internships')) {
            return;
        }

        Schema::table('internships', function (Blueprint $table) {
            if (Schema::hasColumn('internships', 'school_id')) {
                $table->dropForeign(['school_id']);
                $table->dropColumn('school_id');
            }

            if (Schema::hasColumn('internships', 'host_name')) {
                $table->dropColumn('host_name');
            }

            if (Schema::hasColumn('internships', 'host_type')) {
                $table->dropColumn('host_type');
            }
        });
    }
};
