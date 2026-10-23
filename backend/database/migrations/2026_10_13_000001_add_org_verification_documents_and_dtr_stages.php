<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_verification_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('uploaded_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('document_type', 64);
            $table->string('file_path');
            $table->string('file_name');
            $table->string('file_mime', 120)->nullable();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('status', 32)->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
        });

        Schema::table('ojt_dtr_submissions', function (Blueprint $table) {
            $table->foreignId('company_reviewed_by_user_id')->nullable()->after('reviewed_at')->constrained('users')->nullOnDelete();
            $table->timestamp('company_reviewed_at')->nullable()->after('company_reviewed_by_user_id');
            $table->text('company_rejection_reason')->nullable()->after('company_reviewed_at');
        });

        // Existing pending rows are awaiting school coordinator review.
        DB::table('ojt_dtr_submissions')
            ->where('status', 'pending')
            ->update(['status' => 'pending_coordinator']);
    }

    public function down(): void
    {
        DB::table('ojt_dtr_submissions')
            ->where('status', 'pending_coordinator')
            ->update(['status' => 'pending']);
        DB::table('ojt_dtr_submissions')
            ->whereIn('status', ['pending_company', 'revision_required'])
            ->update(['status' => 'pending']);

        Schema::table('ojt_dtr_submissions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_reviewed_by_user_id');
            $table->dropColumn(['company_reviewed_at', 'company_rejection_reason']);
        });

        Schema::dropIfExists('organization_verification_documents');
    }
};
