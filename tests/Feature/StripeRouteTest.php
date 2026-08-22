<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Checkpoint;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StripeRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_requires_authentication(): void
    {
        $this->postJson('/api/customer/bookings/1/stripe-checkout')
            ->assertUnauthorized();
    }

    public function test_checkout_rejects_non_customers(): void
    {
        $booking = $this->bookingFixture();

        foreach (['admin', 'driver'] as $role) {
            Sanctum::actingAs(User::factory()->create([
                'role' => $role,
                'status' => 'active',
            ]));

            $this->postJson("/api/customer/bookings/{$booking->id}/stripe-checkout")
                ->assertForbidden();
        }
    }

    public function test_missing_booking_returns_not_found(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'role' => 'customer',
            'status' => 'active',
        ]));

        $this->postJson('/api/customer/bookings/999/stripe-checkout')
            ->assertNotFound();
    }

    public function test_webhook_only_accepts_post(): void
    {
        $this->getJson('/api/stripe/webhook')->assertMethodNotAllowed();
    }

    private function bookingFixture(): Booking
    {
        $driver = User::factory()->create(['role' => 'driver', 'status' => 'active']);
        $customer = User::factory()->create(['role' => 'customer', 'status' => 'active']);
        $from = Checkpoint::create(['name' => 'Route From']);
        $to = Checkpoint::create(['name' => 'Route To']);
        $trip = Trip::create([
            'driver_id' => $driver->id,
            'money_price' => 10,
            'status' => 'scheduled',
            'departure_date' => now()->addDay(),
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
}
