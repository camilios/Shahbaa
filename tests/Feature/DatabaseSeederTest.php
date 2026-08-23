<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Checkpoint;
use App\Models\DeviceToken;
use App\Models\Governorate;
use App\Models\PointAuditLog;
use App\Models\PrivateTripRequest;
use App\Models\Rating;
use App\Models\Scouring;
use App\Models\Trip;
use App\Models\TripObjection;
use App\Models\User;
use App\Models\WaitingList;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seeder_populates_all_application_areas_with_consistent_relations(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertGreaterThanOrEqual(12, User::count());
        $this->assertGreaterThanOrEqual(8, Checkpoint::count());
        $this->assertSame(14, Governorate::count());
        $this->assertSame(0, Checkpoint::whereNull('governorate_id')->count());
        $this->assertGreaterThanOrEqual(6, Trip::count());
        $this->assertSame(0, Trip::whereNull('source_governorate_id')->orWhereNull('destination_governorate_id')->count());
        $this->assertGreaterThanOrEqual(10, Booking::count());
        $this->assertGreaterThanOrEqual(4, Rating::count());
        $this->assertGreaterThanOrEqual(3, PrivateTripRequest::count());
        $this->assertGreaterThanOrEqual(2, TripObjection::count());
        $this->assertSame(Scouring::count(), PointAuditLog::where('action', 'granted')->count());
        $this->assertGreaterThanOrEqual(3, DeviceToken::count());
        $this->assertGreaterThanOrEqual(4, DB::table('notifications')->count());

        $invalidBookings = Booking::query()
            ->get()
            ->filter(fn (Booking $booking) => $booking->user?->role !== 'customer'
                || $booking->driver_id !== $booking->trip?->driver_id);
        $this->assertCount(0, $invalidBookings);

        $assignedSeats = DB::table('seats')->whereNotNull('booking_id')->count();
        $expectedSeats = Booking::where('status', '!=', 'cancelled')->sum('seats_count');
        $this->assertSame((int) $expectedSeats, $assignedSeats);
    }

    public function test_thirtieth_seeded_waiting_customer_creates_a_matching_trip(): void
    {
        $this->seed(DatabaseSeeder::class);

        $originalTrip = Trip::where('type', 'waiting-list-test')->sole();
        $this->assertSame(29, WaitingList::where('trip_id', $originalTrip->id)->count());
        $this->assertSame(0, $originalTrip->available_seats);

        $customer = User::factory()->create(['role' => 'customer', 'status' => 'active']);
        $pickupId = $originalTrip->checkpoints()->orderBy('id')->value('checkpoint_id');
        $dropoffId = $originalTrip->checkpoints()->orderByDesc('id')->value('checkpoint_id');
        Sanctum::actingAs($customer);

        $newTripId = $this->postJson('/api/bookings', [
            'trip_id' => $originalTrip->id,
            'pickup_checkpoint_id' => $pickupId,
            'dropoff_checkpoint_id' => $dropoffId,
            'seats_count' => 1,
        ])->assertCreated()
            ->assertJsonPath('waiting_list', null)
            ->json('new_trip.id');

        $this->assertNotNull($newTripId);
        $newTrip = Trip::findOrFail($newTripId);
        $this->assertSame($originalTrip->driver_id, $newTrip->driver_id);
        $this->assertSame($originalTrip->type, $newTrip->type);
        $this->assertSame($originalTrip->source_governorate_id, $newTrip->source_governorate_id);
        $this->assertSame($originalTrip->destination_governorate_id, $newTrip->destination_governorate_id);
        $this->assertSame($originalTrip->departure_date->toDateTimeString(), $newTrip->departure_date->toDateTimeString());
        $this->assertSame($originalTrip->arrival_date->toDateTimeString(), $newTrip->arrival_date->toDateTimeString());
        $this->assertSame($originalTrip->total_seats, $newTrip->total_seats);
        $this->assertSame(20, $newTrip->available_seats);
        $this->assertSame(30, $newTrip->bookings()->count());
        $this->assertSame(30, $newTrip->bookedSeats()->count());
        $this->assertSame(0, WaitingList::where('trip_id', $originalTrip->id)->count());
    }
}
