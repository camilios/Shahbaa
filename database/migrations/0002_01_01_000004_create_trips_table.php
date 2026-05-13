<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('trips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained('users')->onDelete('cascade');
            $table->string('type')->default('standard');
            $table->decimal('point_price', 8, 2)->default(0);
            $table->decimal('money_price', 10, 2)->default(0);
            $table->string('status')->default('pending');
            $table->dateTime('departure_date')->nullable();
            $table->dateTime('arrival_date')->nullable();
            $table->unsignedInteger('total_seats')->default(0);
            $table->unsignedInteger('available_seats')->default(0);
            $table->unsignedInteger('earned_points')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trips');
    }
};
