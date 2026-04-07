<?php

use App\Models\School;
use App\Models\Student;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('intern_code')->nullable()->unique()->after('organization_id');
        });

        Student::query()
            ->whereNull('intern_code')
            ->orWhere('intern_code', '')
            ->chunkById(100, function ($students): void {
                foreach ($students as $student) {
                    $school = $student->school_id ? School::query()->find($student->school_id) : null;
                    $student->forceFill([
                        'intern_code' => Student::generateInternCode($school, $student->school_subscription_code),
                    ])->save();
                }
            });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropUnique(['intern_code']);
            $table->dropColumn('intern_code');
        });
    }
};
