<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Checkpoint;
use App\Models\DriverCheckpointLog;
use App\Models\Scouring;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PointWalletPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_granted_points_are_stored_in_the_customer_wallet(): void
    {
        [$customer, $booking, $log] = $this->bookingFixture();

        Scouring::create([
            'driver_checkpoint_log_id' => $log->id,
            'customer_id' => $customer->id,
            'booking_id' => $booking->id,
            'points' => 80,
        ]);

        Sanctum::actingAs($customer);
        $this->getJson('/api/customer/points/wallet')
            ->assertOk()
            ->assertJsonPath('wallet.balance', 80)
            ->assertJsonPath('summary.total_earned', 80)
            ->assertJsonPath('summary.total_spent', 0);

        $this->getJson('/api/customer/points/transactions')
            ->assertOk()
            ->assertJsonPath('data.0.type', 'credit')
            ->assertJsonPath('data.0.amount', 80);
    }

    public function test_customer_can_pay_for_an_owned_booking_with_points_once(): void
    {
        [$customer, $booking, $log] = $this->bookingFixture(25, 2);
        Scouring::create([
            'driver_checkpoint_log_id' => $log->id,
            'customer_id' => $customer->id,
            'booking_id' => $booking->id,
            'points' => 100,
        ]);
        Sanctum::actingAs($customer);

        $this->postJson("/api/customer/bookings/{$booking->id}/pay-with-points")
            ->assertOk()
            ->assertJsonPath('points_spent', 50)
            ->assertJsonPath('wallet.balance', 50)
            ->assertJsonPath('booking.status', 'confirmed')
            ->assertJsonPath('booking.payment_method', 'points')
            ->assertJsonPath('booking.payment_status', 'paid');

        $this->postJson("/api/customer/bookings/{$booking->id}/pay-with-points")
            ->assertOk()
            ->assertJsonPath('wallet.balance', 50);

        $this->assertDatabaseCount('point_transactions', 2);
        $this->assertDatabaseHas('point_transactions', [
            'booking_id' => $booking->id,
            'type' => 'payment',
            'amount' => 50,
            'balance_after' => 50,
        ]);
    }

    public function test_payment_fails_without_enough_points_and_does_not_change_booking(): void
    {
        [$customer, $booking] = $this->bookingFixture(60, 2);
        Sanctum::actingAs($customer);

        $this->postJson("/api/customer/bookings/{$booking->id}/pay-with-points")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('points');

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'pending',
            'payment_status' => 'unpaid',
        ]);
        $this->assertDatabaseMissing('point_transactions', ['type' => 'payment']);
    }

    public function test_customer_cannot_pay_another_customers_booking(): void
    {
        [, $booking] = $this->bookingFixture();
        Sanctum::actingAs(User::factory()->create(['role' => 'customer', 'status' => 'active']));

        $this->postJson("/api/customer/bookings/{$booking->id}/pay-with-points")->assertNotFound();
    }

    public function test_non_customer_cannot_access_customer_wallet(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'status' => 'active']));

        $this->getJson('/api/customer/points/wallet')->assertForbidden();
    }

    private function bookingFixture(int $pointPrice = 25, int $seats = 1): array
    {
        $driver = User::factory()->create(['role' => 'driver', 'status' => 'active']);
        $customer = User::factory()->create(['role' => 'customer', 'status' => 'active']);
        $from = Checkpoint::create(['name' => 'From']);
        $to = Checkpoint::create(['name' => 'To']);
        $trip = Trip::create([
            'driver_id' => $driver->id,
            'point_price' => $pointPrice,
            'status' => 'scheduled',
            'departure_date' => now()->addDay(),
            'total_seats' => 10,
            'available_seats' => 10 - $seats,
        ]);
        $booking = Booking::create([
            'user_id' => $customer->id,
            'driver_id' => $driver->id,
            'trip_id' => $trip->id,
            'pickup_checkpoint_id' => $from->id,
            'dropoff_checkpoint_id' => $to->id,
            'seats_count' => $seats,
            'status' => 'pending',
        ]);
        $log = DriverCheckpointLog::create([
            'driver_id' => $driver->id,
            'trip_id' => $trip->id,
            'checkpoint_id' => $from->id,
            'scanned_at' => now(),
        ]);

        return [$customer, $booking, $log];
    }
}
