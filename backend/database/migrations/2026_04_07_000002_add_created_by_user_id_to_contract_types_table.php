<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contract_types', function (Blueprint $table) {
            if (! Schema::hasColumn('contract_types', 'created_by_user_id')) {
                $table->foreignId('created_by_user_id')
                    ->nullable()
                    ->after('organization_type')
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });

        $ownerIdsByOrganization = DB::table('organizations')
            ->whereNotNull('owner_user_id')
            ->pluck('owner_user_id', 'id');

        DB::table('contract_types')
            ->whereNotNull('organization_id')
            ->whereNull('created_by_user_id')
            ->orderBy('id')
            ->select(['id', 'organization_id'])
            ->chunkById(100, function ($types) use ($ownerIdsByOrganization) {
                foreach ($types as $type) {
                    $ownerUserId = $ownerIdsByOrganization[$type->organization_id] ?? null;
                    if (! $ownerUserId) {
                        continue;
                    }

                    DB::table('contract_types')
                        ->where('id', $type->id)
                        ->update(['created_by_user_id' => $ownerUserId]);
                }
            });

        Schema::table('contract_types', function (Blueprint $table) {
            $table->index(['organization_id', 'created_by_user_id'], 'ct_org_creator_idx');
        });
    }

    public function down(): void
    {
        Schema::table('contract_types', function (Blueprint $table) {
            $table->dropIndex('ct_org_creator_idx');

            if (Schema::hasColumn('contract_types', 'created_by_user_id')) {
                $table->dropConstrainedForeignId('created_by_user_id');
            }
        });
    }
};
