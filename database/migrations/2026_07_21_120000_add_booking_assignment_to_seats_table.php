<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seats', function (Blueprint $table) {
            $table->foreignId('booking_id')->nullable()->after('trip_id')->constrained('bookings')->nullOnDelete();
            $table->unique(['trip_id', 'seat_number']);
        });
    }

    public function down(): void
    {
        // MySQL may use the composite unique index to support the trip_id
        // foreign key, so provide a replacement before dropping it.
        Schema::table('seats', function (Blueprint $table) {
            $table->index('trip_id');
        });

        Schema::table('seats', function (Blueprint $table) {
            $table->dropUnique(['trip_id', 'seat_number']);
            $table->dropConstrainedForeignId('booking_id');
        });
    }
};
