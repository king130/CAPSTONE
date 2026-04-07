<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropUnique('roles_slug_unique');
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained('organizations')->nullOnDelete();
            $table->unique(['tenant_id', 'slug']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('platform_role_id')->constrained('organizations')->nullOnDelete();
            $table->foreignId('role_id')->nullable()->after('tenant_id')->constrained('roles')->nullOnDelete();
        });

        $memberships = DB::table('organization_memberships')
            ->select('user_id', 'organization_id', 'role_id')
            ->orderBy('id')
            ->get();

        foreach ($memberships as $membership) {
            DB::table('users')
                ->where('id', $membership->user_id)
                ->update([
                    'tenant_id' => $membership->organization_id,
                    'role_id' => $membership->role_id,
                ]);
        }

        DB::table('users')
            ->whereNull('role_id')
            ->whereNotNull('platform_role_id')
            ->update([
                'role_id' => DB::raw('platform_role_id'),
            ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('role_id');
            $table->dropConstrainedForeignId('tenant_id');
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'slug']);
            $table->dropConstrainedForeignId('tenant_id');
            $table->unique('slug');
        });
    }
};
