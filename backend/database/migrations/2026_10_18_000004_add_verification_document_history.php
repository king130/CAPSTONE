<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('organization_verification_document_histories')) {
            Schema::create('organization_verification_document_histories', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('organization_verification_document_id');
                $table->unsignedBigInteger('organization_id');
                $table->unsignedBigInteger('uploaded_by_user_id')->nullable();
                $table->string('document_type', 64);
                $table->string('file_path');
                $table->string('file_name');
                $table->string('file_mime', 120)->nullable();
                $table->unsignedBigInteger('file_size')->default(0);
                $table->string('status', 32)->nullable();
                $table->text('rejection_reason')->nullable();
                $table->timestamp('uploaded_at')->nullable();
                $table->timestamps();

                $table->foreign('organization_verification_document_id', 'ovdh_doc_fk')
                    ->references('id')
                    ->on('organization_verification_documents')
                    ->cascadeOnDelete();
                $table->foreign('organization_id', 'ovdh_org_fk')
                    ->references('id')
                    ->on('organizations')
                    ->cascadeOnDelete();
                $table->foreign('uploaded_by_user_id', 'ovdh_user_fk')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();

                $table->index(['organization_id', 'document_type'], 'ovdh_org_type_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_verification_document_histories');
    }
};
