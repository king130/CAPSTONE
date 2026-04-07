<?php

use App\Models\Contract;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Contract::query()
            ->where(function ($query) {
                $query->whereNull('moa_reference_no')
                    ->orWhere('moa_reference_no', '');
            })
            ->orderBy('id')
            ->chunkById(100, function ($contracts): void {
                foreach ($contracts as $contract) {
                    $contract->forceFill([
                        'moa_reference_no' => Contract::generateReferenceNumber(
                            (int) $contract->id,
                            $contract->created_at?->year
                        ),
                    ])->saveQuietly();
                }
            });

        Schema::table('contracts', function (Blueprint $table) {
            $table->index('moa_reference_no', 'contracts_moa_reference_no_idx');
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropIndex('contracts_moa_reference_no_idx');
        });
    }
};
