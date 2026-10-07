<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('internships', function (Blueprint $table) {
            if (! Schema::hasColumn('internships', 'work_setup')) {
                $table->string('work_setup', 32)->nullable()->after('location');
            }
            if (! Schema::hasColumn('internships', 'perks')) {
                $table->json('perks')->nullable()->after('allowance');
            }
            if (! Schema::hasColumn('internships', 'application_deadline')) {
                $table->date('application_deadline')->nullable()->after('end_date');
            }
            if (! Schema::hasColumn('internships', 'city')) {
                $table->string('city', 255)->nullable()->after('location');
            }
        });
    }

    public function down(): void
    {
        Schema::table('internships', function (Blueprint $table) {
            $columns = collect(['work_setup', 'perks', 'application_deadline', 'city'])
                ->filter(fn (string $column) => Schema::hasColumn('internships', $column))
                ->values()
                ->all();

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
