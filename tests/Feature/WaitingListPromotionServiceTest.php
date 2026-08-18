<?php

namespace Tests\Feature;

use App\Models\Checkpoint;
use App\Models\Trip;
use App\Models\User;
use App\Models\WaitingList;
use App\Services\WaitingListPromotionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WaitingListPromotionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_an_identical_trip_when_waiting_seats_reach_thirty(): void
    {
        [$trip, $pickup, $dropoff] = $this->createFullTrip();

        foreach ([10, 10, 9] as $seats) {
            $this->addWaitingCustomer($trip, $pickup, $dropoff, $seats);
        }

        $service = app(WaitingListPromotionService::class);
        $this->assertNull($service->promoteWhenReady($trip));

        $this->addWaitingCustomer($trip, $pickup, $dropoff, 1);
        $newTrip = $service->promoteWhenReady($trip);

        $this->assertNotNull($newTrip);
        $trip->refresh();
        $this->assertSame($trip->only([
            'driver_id', 'type', 'point_price', 'money_price', 'status',
            'departure_date', 'arrival_date', 'total_seats', 'earned_points',
        ]), $newTrip->only([
            'driver_id', 'type', 'point_price', 'money_price', 'status',
            'departure_date', 'arrival_date', 'total_seats', 'earned_points',
        ]));
        $this->assertSame(30, $newTrip->bookings()->sum('seats_count'));
        $this->assertSame(20, $newTrip->available_seats);
        $this->assertCount(50, $newTrip->seats);
        $this->assertSame(0, $trip->waitingList()->count());
        $this->assertEquals(
            $trip->checkpoints()->orderBy('id')->pluck('checkpoint_id'),
            $newTrip->checkpoints()->orderBy('id')->pluck('checkpoint_id'),
        );
    }

    public function test_it_counts_requested_seats_instead_of_waiting_list_rows(): void
    {
        [$trip, $pickup, $dropoff] = $this->createFullTrip();

        $this->addWaitingCustomer($trip, $pickup, $dropoff, 15);
        $this->addWaitingCustomer($trip, $pickup, $dropoff, 15);

        $newTrip = app(WaitingListPromotionService::class)->promoteWhenReady($trip);

        $this->assertNotNull($newTrip);
        $this->assertSame(2, $newTrip->bookings()->count());
        $this->assertSame(30, $newTrip->bookings()->sum('seats_count'));
    }

    private function createFullTrip(): array
    {
        $driver = User::factory()->create(['role' => 'driver']);
        $pickup = Checkpoint::create(['name' => 'Pickup']);
        $dropoff = Checkpoint::create(['name' => 'Dropoff']);
        $trip = Trip::create([
            'driver_id' => $driver->id,
            'type' => 'standard',
            'point_price' => 25,
            'money_price' => 100,
            'status' => 'pending',
            'departure_date' => now()->addDay(),
            'arrival_date' => now()->addDay()->addHours(2),
            'total_seats' => 50,
            'available_seats' => 0,
            'earned_points' => 5,
        ]);
        $trip->checkpoints()->create(['checkpoint_id' => $pickup->id, 'description' => 'Start']);
        $trip->checkpoints()->create(['checkpoint_id' => $dropoff->id, 'description' => 'End']);

        return [$trip, $pickup, $dropoff];
    }

    private function addWaitingCustomer(Trip $trip, Checkpoint $pickup, Checkpoint $dropoff, int $seats): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        WaitingList::create([
            'user_id' => $customer->id,
            'trip_id' => $trip->id,
            'pickup_checkpoint_id' => $pickup->id,
            'dropoff_checkpoint_id' => $dropoff->id,
            'seats_count' => $seats,
            'status' => 'pending',
        ]);
    }
}
