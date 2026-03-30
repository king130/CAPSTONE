<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('contract_types')) {
            Schema::create('contract_types', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug');
                $table->string('scope', 20)->default('global');
                $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
                $table->string('organization_type', 20)->nullable();
                $table->foreignId('base_contract_type_id')->nullable()->constrained('contract_types')->nullOnDelete();
                $table->text('description')->nullable();
                $table->json('fields_schema');
                $table->json('default_values')->nullable();
                $table->json('settings')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();

                $table->unique(['slug', 'organization_id']);
                $table->index(['scope', 'organization_type', 'is_active'], 'ct_scope_orgtype_active_idx');
            });
        }

        Schema::table('contracts', function (Blueprint $table) {
            if (! Schema::hasColumn('contracts', 'contract_type_id')) {
                $table->foreignId('contract_type_id')->nullable()->after('subject')->constrained('contract_types')->nullOnDelete();
            }
            if (! Schema::hasColumn('contracts', 'requester_organization_id')) {
                $table->foreignId('requester_organization_id')->nullable()->after('company_user_id')->constrained('organizations')->nullOnDelete();
            }
            if (! Schema::hasColumn('contracts', 'partner_organization_id')) {
                $table->foreignId('partner_organization_id')->nullable()->after('requester_organization_id')->constrained('organizations')->nullOnDelete();
            }
            if (! Schema::hasColumn('contracts', 'requested_by_user_id')) {
                $table->foreignId('requested_by_user_id')->nullable()->after('requested_by_role')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('contracts', 'partner_user_id')) {
                $table->foreignId('partner_user_id')->nullable()->after('requested_by_user_id')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('contracts', 'dynamic_fields')) {
                $table->json('dynamic_fields')->nullable()->after('notes');
            }
            if (! Schema::hasColumn('contracts', 'schema_snapshot')) {
                $table->json('schema_snapshot')->nullable()->after('dynamic_fields');
            }
            if (! Schema::hasColumn('contracts', 'metadata')) {
                $table->json('metadata')->nullable()->after('schema_snapshot');
            }
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->index(['contract_type_id', 'status'], 'contracts_type_status_idx');
            $table->index(['requester_organization_id', 'partner_organization_id'], 'contracts_org_pair_idx');
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropIndex('contracts_type_status_idx');
            $table->dropIndex('contracts_org_pair_idx');
            $table->dropConstrainedForeignId('partner_user_id');
            $table->dropConstrainedForeignId('requested_by_user_id');
            $table->dropConstrainedForeignId('partner_organization_id');
            $table->dropConstrainedForeignId('requester_organization_id');
            $table->dropConstrainedForeignId('contract_type_id');
            $table->dropColumn(['dynamic_fields', 'schema_snapshot', 'metadata']);
        });

        Schema::dropIfExists('contract_types');
    }
};
