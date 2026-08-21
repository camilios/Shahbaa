<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->string('booking_source', 30)->default('app')->after('user_id');
            $table->string('guest_name')->nullable()->after('booking_source');
            $table->string('guest_phone', 50)->nullable()->after('guest_name');
            $table->string('guest_gender', 20)->nullable()->after('guest_phone');
            $table->string('guest_national_number')->nullable()->after('guest_gender');
        });
    }

    public function down(): void
    {
        DB::table('bookings')->whereNull('user_id')->delete();

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn([
                'booking_source',
                'guest_name',
                'guest_phone',
                'guest_gender',
                'guest_national_number',
            ]);
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
