<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('governorates', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::table('checkpoints', function (Blueprint $table) {
            $table->foreignId('governorate_id')->nullable()->after('governorate')
                ->constrained()->restrictOnDelete();
        });
        Schema::table('trips', function (Blueprint $table) {
            $table->foreignId('source_governorate_id')->nullable()->after('source_governorate')
                ->constrained('governorates')->restrictOnDelete();
            $table->foreignId('destination_governorate_id')->nullable()->after('destination_governorate')
                ->constrained('governorates')->restrictOnDelete();
        });

        $names = DB::table('checkpoints')->whereNotNull('governorate')->pluck('governorate')
            ->merge(DB::table('trips')->whereNotNull('source_governorate')->pluck('source_governorate'))
            ->merge(DB::table('trips')->whereNotNull('destination_governorate')->pluck('destination_governorate'))
            ->filter(fn ($name) => trim((string) $name) !== '')
            ->unique();

        foreach ($names as $name) {
            DB::table('governorates')->insertOrIgnore([
                'name' => $name,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        foreach (DB::table('governorates')->get(['id', 'name']) as $governorate) {
            DB::table('checkpoints')->where('governorate', $governorate->name)
                ->update(['governorate_id' => $governorate->id]);
            DB::table('trips')->where('source_governorate', $governorate->name)
                ->update(['source_governorate_id' => $governorate->id]);
            DB::table('trips')->where('destination_governorate', $governorate->name)
                ->update(['destination_governorate_id' => $governorate->id]);
        }
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_governorate_id');
            $table->dropConstrainedForeignId('destination_governorate_id');
        });
        Schema::table('checkpoints', function (Blueprint $table) {
            $table->dropConstrainedForeignId('governorate_id');
        });
        Schema::dropIfExists('governorates');
    }
};
