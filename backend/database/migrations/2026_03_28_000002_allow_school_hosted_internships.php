<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('internships', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
        });

        Schema::table('internships', function (Blueprint $table) {
            $table->unsignedBigInteger('company_id')->nullable()->change();
            $table->foreignId('school_id')->nullable()->after('company_id')->constrained()->nullOnDelete();
            $table->string('host_type')->default('company')->after('company_name');
            $table->string('host_name')->nullable()->after('host_type');
            $table->foreign('company_id')->references('id')->on('companies')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('internships', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropForeign(['school_id']);
            $table->dropColumn(['school_id', 'host_type', 'host_name']);
        });

        Schema::table('internships', function (Blueprint $table) {
            $table->unsignedBigInteger('company_id')->nullable(false)->change();
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
        });
    }
};
