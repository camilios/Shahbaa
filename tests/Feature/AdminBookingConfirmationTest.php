<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Checkpoint;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminBookingConfirmationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_confirm_a_pending_booking_after_payment_verification(): void
    {
        Sanctum::actingAs($this->user('admin'));
        $booking = $this->booking(now()->addHours(2));

        $this->postJson("/api/admin/bookings/{$booking->id}/confirm", [
            'payment_verified' => true,
        ])->assertOk()
            ->assertJsonPath('booking.status', 'confirmed');

        $this->assertSame('confirmed', $booking->fresh()->status);
    }

    public function test_payment_verification_is_required(): void
    {
        Sanctum::actingAs($this->user('admin'));
        $booking = $this->booking(now()->addHours(2));

        $this->postJson("/api/admin/bookings/{$booking->id}/confirm")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('payment_verified');

        $this->assertSame('pending', $booking->fresh()->status);
    }

    public function test_booking_cannot_be_confirmed_during_the_last_hour(): void
    {
        Sanctum::actingAs($this->user('admin'));
        $booking = $this->booking(now()->addMinutes(59));

        $this->postJson("/api/admin/bookings/{$booking->id}/confirm", [
            'payment_verified' => true,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('trip');

        $this->assertSame('pending', $booking->fresh()->status);
    }

    public function test_only_admin_can_confirm_and_only_once(): void
    {
        $booking = $this->booking(now()->addHours(2));
        Sanctum::actingAs($this->user('customer'));

        $this->postJson("/api/admin/bookings/{$booking->id}/confirm", [
            'payment_verified' => true,
        ])->assertForbidden();

        $booking->update(['status' => 'confirmed']);
        Sanctum::actingAs($this->user('admin'));
        $this->postJson("/api/admin/bookings/{$booking->id}/confirm", [
            'payment_verified' => true,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    private function booking($departure): Booking
    {
        $driver = $this->user('driver');
        $customer = $this->user('customer');
        $from = Checkpoint::create(['name' => 'From']);
        $to = Checkpoint::create(['name' => 'To']);
        $trip = Trip::create([
            'driver_id' => $driver->id,
            'status' => 'scheduled',
            'departure_date' => $departure,
            'arrival_date' => $departure->copy()->addHours(2),
            'total_seats' => 10,
            'available_seats' => 9,
        ]);

        return Booking::create([
            'user_id' => $customer->id,
            'driver_id' => $driver->id,
            'trip_id' => $trip->id,
            'pickup_checkpoint_id' => $from->id,
            'dropoff_checkpoint_id' => $to->id,
            'seats_count' => 1,
            'status' => 'pending',
        ]);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role, 'status' => 'active']);
    }
}
