<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schools', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->string('institution_name');
            $table->string('institution_type')->nullable();
            $table->string('department')->nullable();
            $table->string('position')->nullable();
            $table->string('official_school_email')->nullable();
            $table->string('school_contact_number')->nullable();
            $table->text('school_address')->nullable();
            $table->string('school_id_number')->nullable();
            $table->string('ched_deped_number')->nullable();
            $table->string('school_accreditation')->nullable();
            $table->string('registrar_name')->nullable();
            $table->string('registrar_email')->nullable();
            $table->string('subscription_code')->nullable();
            $table->string('verification_status')->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schools');
    }
};
