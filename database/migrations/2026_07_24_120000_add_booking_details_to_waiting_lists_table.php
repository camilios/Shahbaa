<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('waiting_lists', function (Blueprint $table) {
            $table->foreignId('pickup_checkpoint_id')->nullable()->after('trip_id')->constrained('checkpoints')->nullOnDelete();
            $table->foreignId('dropoff_checkpoint_id')->nullable()->after('pickup_checkpoint_id')->constrained('checkpoints')->nullOnDelete();
            $table->unsignedInteger('seats_count')->default(1)->after('dropoff_checkpoint_id');
        });
    }

    public function down(): void
    {
        Schema::table('waiting_lists', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pickup_checkpoint_id');
            $table->dropConstrainedForeignId('dropoff_checkpoint_id');
            $table->dropColumn('seats_count');
        });
    }
};
