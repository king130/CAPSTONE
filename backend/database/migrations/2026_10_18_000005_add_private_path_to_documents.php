<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            if (! Schema::hasColumn('documents', 'file_path')) {
                $table->string('file_path')->nullable()->after('file_url');
            }
            if (! Schema::hasColumn('documents', 'disk')) {
                $table->string('disk', 32)->nullable()->after('file_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            if (Schema::hasColumn('documents', 'disk')) {
                $table->dropColumn('disk');
            }
            if (Schema::hasColumn('documents', 'file_path')) {
                $table->dropColumn('file_path');
            }
        });
    }
};
