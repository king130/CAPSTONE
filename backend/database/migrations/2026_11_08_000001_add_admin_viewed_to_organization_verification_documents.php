<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_verification_documents', function (Blueprint $table) {
            $table->timestamp('admin_viewed_at')->nullable()->after('reviewed_at');
            $table->foreignId('admin_viewed_by_user_id')
                ->nullable()
                ->after('admin_viewed_at')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('organization_verification_documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('admin_viewed_by_user_id');
            $table->dropColumn('admin_viewed_at');
        });
    }
};
