<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Checkpoint;
use App\Models\Complaint;
use App\Models\PrivateTripRequest;
use App\Models\Rating;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminApplicationManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_drivers_only(): void
    {
        Sanctum::actingAs($this->user('admin'));
        $activeDriver = $this->user('driver');
        $inactiveDriver = User::factory()->create(['role' => 'driver', 'status' => 'inactive']);
        $this->user('customer');

        $response = $this->getJson('/api/drivers')->assertOk();

        $this->assertEqualsCanonicalizing(
            [$activeDriver->id, $inactiveDriver->id],
            collect($response->json('data'))->pluck('id')->all(),
        );
        $response->assertJsonPath('data.0.role', 'driver');
    }

    public function test_non_admin_cannot_list_drivers(): void
    {
        Sanctum::actingAs($this->user('customer'));

        $this->getJson('/api/drivers')->assertForbidden();
    }

    public function test_trip_creation_assigns_driver_checkpoints_and_at_most_fifty_seats(): void
    {
        Sanctum::actingAs($this->user('admin'));
        $driver = $this->user('driver');
        [$from, $to] = $this->checkpoints();

        $response = $this->postJson('/api/trips', [
            'driver_id' => $driver->id,
            'type' => 'standard',
            'status' => 'scheduled',
            'departure_date' => now()->addDay(),
            'arrival_date' => now()->addDay()->addHour(),
            'total_seats' => 50,
            'checkpoint_ids' => [$from->id, $to->id],
        ])->assertCreated();

        $trip = Trip::findOrFail($response->json('id'));
        $this->assertSame($driver->id, $trip->driver_id);
        $this->assertSame(50, $trip->seats()->count());
        $this->assertSame(50, $trip->available_seats);
        $this->assertEquals([$from->id, $to->id], $trip->checkpoints()->pluck('checkpoint_id')->all());

        $this->postJson('/api/trips', [
            'driver_id' => $driver->id,
            'type' => 'standard',
            'total_seats' => 51,
            'checkpoint_ids' => [$from->id, $to->id],
        ])->assertUnprocessable()->assertJsonValidationErrors('total_seats');
    }

    public function test_checkpoint_can_be_created(): void
    {
        Sanctum::actingAs($this->user('admin'));

        $this->postJson('/api/checkpoints', [
            'name' => 'New Station',
            'location' => 'Main road',
            'governorate' => 'Aleppo',
        ])->assertCreated()->assertJsonPath('name', 'New Station');
    }

    public function test_private_request_can_be_approved_into_a_private_trip(): void
    {
        Sanctum::actingAs($this->user('admin'));
        $customer = $this->user('customer');
        $driver = $this->user('driver');
        [$from, $to] = $this->checkpoints();
        $privateRequest = PrivateTripRequest::create([
            'user_id' => $customer->id,
            'from_location' => 'A',
            'to_location' => 'B',
        ]);

        $response = $this->postJson("/api/private-trip-requests/{$privateRequest->id}/approve", [
            'driver_id' => $driver->id,
            'checkpoint_ids' => [$from->id, $to->id],
            'departure_date' => now()->addDay(),
            'arrival_date' => now()->addDay()->addHour(),
            'total_seats' => 10,
        ])->assertCreated();

        $this->assertSame('private', $response->json('trip.type'));
        $this->assertSame($driver->id, $response->json('trip.driver_id'));
        $this->assertSame('approved', $privateRequest->fresh()->status);
    }

    public function test_admin_can_reject_pending_private_trip_request_with_a_required_reason(): void
    {
        $admin = $this->user('admin');
        $privateRequest = PrivateTripRequest::create([
            'user_id' => $this->user('customer')->id,
            'from_location' => 'A',
            'to_location' => 'B',
        ]);
        Sanctum::actingAs($admin);

        $this->postJson("/api/private-trip-requests/{$privateRequest->id}/reject", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reason');

        $this->postJson("/api/private-trip-requests/{$privateRequest->id}/reject", [
            'reason' => 'No driver is available for the requested time.',
        ])
            ->assertOk()
            ->assertJsonPath('status', 'rejected')
            ->assertJsonPath('rejection_reason', 'No driver is available for the requested time.')
            ->assertJsonPath('rejected_by.id', $admin->id);

        $this->assertNotNull($privateRequest->fresh()->rejected_at);
        $this->postJson("/api/private-trip-requests/{$privateRequest->id}/reject", [
            'reason' => 'Another reason',
        ])->assertUnprocessable()->assertJsonValidationErrors('status');
    }

    public function test_booking_immediately_assigns_seat_numbers_and_cannot_exceed_capacity(): void
    {
        Sanctum::actingAs($this->user('customer'));
        $customer = auth()->user();
        $driver = $this->user('driver');
        [$from, $to] = $this->checkpoints();
        $trip = $this->trip($driver, [$from, $to], 3);

        $response = $this->postJson('/api/bookings', [
            'trip_id' => $trip->id,
            'pickup_checkpoint_id' => $from->id,
            'dropoff_checkpoint_id' => $to->id,
            'seats_count' => 2,
        ])->assertCreated();

        $booking = Booking::findOrFail($response->json('id'));
        $this->assertEquals(['S01', 'S02'], $booking->seats()->pluck('seat_number')->all());
        $this->assertSame(1, $trip->fresh()->available_seats);

        $this->postJson('/api/bookings', [
            'trip_id' => $trip->id,
            'pickup_checkpoint_id' => $from->id,
            'dropoff_checkpoint_id' => $to->id,
            'seats_count' => 2,
        ])->assertUnprocessable()->assertJsonValidationErrors('seats_count');
    }

    public function test_customer_is_automatically_added_to_waiting_list_when_trip_is_full(): void
    {
        Sanctum::actingAs($this->user('customer'));
        $customer = auth()->user();
        $driver = $this->user('driver');
        [$from, $to] = $this->checkpoints();
        $trip = $this->trip($driver, [$from, $to], 1);

        $payload = [
            'trip_id' => $trip->id,
            'pickup_checkpoint_id' => $from->id,
            'dropoff_checkpoint_id' => $to->id,
            'seats_count' => 1,
        ];

        $this->postJson('/api/bookings', $payload)->assertCreated();

        $this->postJson('/api/bookings', $payload)
            ->assertCreated()
            ->assertJsonPath('waiting_list.user_id', $customer->id)
            ->assertJsonPath('waiting_list.trip_id', $trip->id)
            ->assertJsonPath('waiting_list.status', 'pending')
            ->assertJsonPath('waiting_list.user.id', $customer->id);

        $this->postJson('/api/bookings', $payload)->assertCreated();

        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseCount('waiting_lists', 1);
        $this->assertSame(0, $trip->fresh()->available_seats);
    }

    public function test_thirty_waiting_customers_are_booked_on_an_automatically_created_trip(): void
    {
        $driver = $this->user('driver');
        [$from, $to] = $this->checkpoints();
        $trip = $this->trip($driver, [$from, $to], 50);
        $existingBooking = Booking::create([
            'user_id' => $this->user('customer')->id,
            'driver_id' => $driver->id,
            'trip_id' => $trip->id,
            'pickup_checkpoint_id' => $from->id,
            'dropoff_checkpoint_id' => $to->id,
            'seats_count' => 50,
        ]);
        $trip->seats()->update(['booking_id' => $existingBooking->id]);
        $trip->update(['available_seats' => 0]);

        $lastResponse = null;
        foreach (User::factory()->count(30)->create(['role' => 'customer', 'status' => 'active']) as $customer) {
            Sanctum::actingAs($customer);
            $lastResponse = $this->postJson('/api/bookings', [
                'trip_id' => $trip->id,
                'pickup_checkpoint_id' => $from->id,
                'dropoff_checkpoint_id' => $to->id,
                'seats_count' => 1,
            ])->assertCreated();
        }

        $newTripId = $lastResponse->json('new_trip.id');
        $this->assertNotNull($newTripId);
        $newTrip = Trip::findOrFail($newTripId);

        $this->assertSame($trip->driver_id, $newTrip->driver_id);
        $this->assertSame($trip->type, $newTrip->type);
        $this->assertSame($trip->total_seats, $newTrip->total_seats);
        $this->assertSame(20, $newTrip->available_seats);
        $this->assertSame(30, $newTrip->bookings()->count());
        $this->assertSame(30, $newTrip->bookedSeats()->count());
        $this->assertEquals(
            $trip->checkpoints()->pluck('checkpoint_id')->all(),
            $newTrip->checkpoints()->pluck('checkpoint_id')->all(),
        );
        $this->assertDatabaseMissing('waiting_lists', ['trip_id' => $trip->id]);
    }

    public function test_manual_waiting_list_entry_is_wired_with_booking_details(): void
    {
        Sanctum::actingAs($this->user('admin'));
        $customer = $this->user('customer');
        [$from, $to] = $this->checkpoints();
        $trip = $this->trip($this->user('driver'), [$from, $to], 50);

        $this->postJson('/api/waiting-lists', [
            'user_id' => $customer->id,
            'trip_id' => $trip->id,
            'status' => 'pending',
        ])->assertCreated()
            ->assertJsonPath('waiting_list.user_id', $customer->id)
            ->assertJsonPath('waiting_list.pickup_checkpoint_id', $from->id)
            ->assertJsonPath('waiting_list.dropoff_checkpoint_id', $to->id)
            ->assertJsonPath('waiting_list.seats_count', 1)
            ->assertJsonPath('new_trip', null);
    }

    public function test_admin_can_book_for_a_selected_customer_and_driver_is_taken_from_trip(): void
    {
        Sanctum::actingAs($this->user('admin'));
        $customer = $this->user('customer');
        $driver = $this->user('driver');
        [$from, $to] = $this->checkpoints();
        $trip = $this->trip($driver, [$from, $to], 3);

        $response = $this->postJson('/api/bookings', [
            'user_id' => $customer->id,
            'driver_id' => $this->user('driver')->id,
            'trip_id' => $trip->id,
            'pickup_checkpoint_id' => $from->id,
            'dropoff_checkpoint_id' => $to->id,
            'seats_count' => 1,
        ])->assertCreated();

        $response
            ->assertJsonPath('user_id', $customer->id)
            ->assertJsonPath('driver_id', $driver->id)
            ->assertJsonPath('seats.0.seat_number', 'S01');
    }

    public function test_booking_assigns_the_explicitly_selected_seats(): void
    {
        Sanctum::actingAs($this->user('customer'));
        $driver = $this->user('driver');
        [$from, $to] = $this->checkpoints();
        $trip = $this->trip($driver, [$from, $to], 8);

        $response = $this->postJson('/api/bookings', [
            'trip_id' => $trip->id,
            'pickup_checkpoint_id' => $from->id,
            'dropoff_checkpoint_id' => $to->id,
            'seats_count' => 2,
            'seat_numbers' => ['S03', 'S04'],
        ])->assertCreated();

        $booking = Booking::findOrFail($response->json('id'));
        $this->assertEquals(['S03', 'S04'], $booking->seats()->orderBy('seat_number')->pluck('seat_number')->all());

        $this->postJson('/api/bookings', [
            'trip_id' => $trip->id,
            'pickup_checkpoint_id' => $from->id,
            'dropoff_checkpoint_id' => $to->id,
            'seats_count' => 1,
            'seat_numbers' => ['S03'],
        ])->assertUnprocessable()->assertJsonValidationErrors('seat_numbers');
    }

    public function test_admin_cannot_book_for_non_customer_and_customer_cannot_choose_user_id(): void
    {
        $admin = $this->user('admin');
        $driver = $this->user('driver');
        [$from, $to] = $this->checkpoints();
        $trip = $this->trip($driver, [$from, $to], 3);
        $payload = [
            'trip_id' => $trip->id,
            'pickup_checkpoint_id' => $from->id,
            'dropoff_checkpoint_id' => $to->id,
            'seats_count' => 1,
        ];

        Sanctum::actingAs($admin);
        $this->postJson('/api/bookings', [...$payload, 'user_id' => $driver->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('user_id');

        $customer = $this->user('customer');
        Sanctum::actingAs($customer);
        $this->postJson('/api/bookings', [...$payload, 'user_id' => $this->user('customer')->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('user_id');
    }

    public function test_admin_can_block_user_revoke_tokens_and_inactive_user_is_denied(): void
    {
        $admin = $this->user('admin');
        $customer = $this->user('customer');
        $customer->createToken('app-token');
        Sanctum::actingAs($admin);

        $this->patchJson("/api/users/{$customer->id}/block")
            ->assertOk()
            ->assertJsonPath('user.status', 'inactive');
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $customer->id]);

        Sanctum::actingAs($customer->fresh());
        $this->getJson('/api/checkpoints')->assertForbidden();
    }

    public function test_only_admin_can_unblock_a_user(): void
    {
        $target = User::factory()->create([
            'role' => 'customer',
            'status' => 'inactive',
        ]);

        $this->patchJson("/api/users/{$target->id}/unblock")
            ->assertUnauthorized();

        Sanctum::actingAs($this->user('customer'));
        $this->patchJson("/api/users/{$target->id}/unblock")
            ->assertForbidden();

        Sanctum::actingAs($this->user('admin'));
        $this->patchJson("/api/users/{$target->id}/unblock")
            ->assertOk()
            ->assertJsonPath('user.status', 'active');
    }

    public function test_admin_can_view_and_reply_to_ratings_and_complaints(): void
    {
        $admin = $this->user('admin');
        $customer = $this->user('customer');
        $driver = $this->user('driver');
        [$from, $to] = $this->checkpoints();
        $trip = $this->trip($driver, [$from, $to], 5);
        $rating = Rating::create(['customer_id' => $customer->id, 'trip_id' => $trip->id, 'rate' => 4, 'comment' => 'Good']);
        $complaint = Complaint::create(['customer_id' => $customer->id, 'comment' => 'Late']);
        Sanctum::actingAs($admin);

        $this->getJson('/api/ratings')->assertOk()->assertJsonPath('data.0.id', $rating->id);
        $this->postJson("/api/ratings/{$rating->id}/reply", ['reply' => 'Thank you'])
            ->assertOk()->assertJsonPath('admin_reply', 'Thank you');
        $this->getJson('/api/complaints')->assertOk()->assertJsonPath('data.0.id', $complaint->id);
        $this->postJson("/api/complaints/{$complaint->id}/reply", ['reply' => 'Resolved'])
            ->assertOk()->assertJsonPath('status', 'answered');
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role, 'status' => 'active']);
    }

    private function checkpoints(): array
    {
        return [
            Checkpoint::create(['name' => 'From']),
            Checkpoint::create(['name' => 'To']),
        ];
    }

    private function trip(User $driver, array $checkpoints, int $seats): Trip
    {
        $trip = Trip::create([
            'driver_id' => $driver->id,
            'type' => 'standard',
            'status' => 'scheduled',
            'total_seats' => $seats,
            'available_seats' => $seats,
        ]);
        foreach ($checkpoints as $checkpoint) {
            $trip->checkpoints()->create(['checkpoint_id' => $checkpoint->id]);
        }
        for ($number = 1; $number <= $seats; $number++) {
            $trip->seats()->create(['seat_number' => 'S'.str_pad((string) $number, 2, '0', STR_PAD_LEFT)]);
        }

        return $trip;
    }
}
