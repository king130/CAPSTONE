<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Renames partnership tables to agreement terminology.
 * Column names (contract_type_id, base_contract_type_id) are kept for compatibility;
 * Eloquent models resolve the active table name at runtime.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('contract_types') && ! Schema::hasTable('agreement_types')) {
            Schema::rename('contract_types', 'agreement_types');
        }

        if (Schema::hasTable('contracts') && ! Schema::hasTable('agreements')) {
            Schema::rename('contracts', 'agreements');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('agreements') && ! Schema::hasTable('contracts')) {
            Schema::rename('agreements', 'contracts');
        }

        if (Schema::hasTable('agreement_types') && ! Schema::hasTable('contract_types')) {
            Schema::rename('agreement_types', 'contract_types');
        }
    }
};
