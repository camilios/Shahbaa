<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'national_number')) {
                $table->string('national_number')->nullable()->unique()->after('phone');
            }

            if (! Schema::hasColumn('users', 'father_name')) {
                $table->string('father_name')->nullable()->after('national_number');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'father_name')) {
                $table->dropColumn('father_name');
            }

            if (Schema::hasColumn('users', 'national_number')) {
                $table->dropUnique(['national_number']);
                $table->dropColumn('national_number');
            }
        });
    }
};
