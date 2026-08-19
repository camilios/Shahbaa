<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Checkpoint;
use App\Models\DeviceToken;
use App\Models\PointAuditLog;
use App\Models\PrivateTripRequest;
use App\Models\Rating;
use App\Models\Scouring;
use App\Models\Trip;
use App\Models\TripObjection;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seeder_populates_all_application_areas_with_consistent_relations(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertGreaterThanOrEqual(12, User::count());
        $this->assertGreaterThanOrEqual(8, Checkpoint::count());
        $this->assertGreaterThanOrEqual(6, Trip::count());
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
}
