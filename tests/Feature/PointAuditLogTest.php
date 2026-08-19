<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Checkpoint;
use App\Models\DriverCheckpointLog;
use App\Models\PointAuditLog;
use App\Models\Scouring;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PointAuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_points_changes_create_an_immutable_audit_history(): void
    {
        $admin = $this->user('admin');
        Sanctum::actingAs($admin);
        [$booking, $checkpointLog] = $this->bookingAndCheckpointLog();

        $scouring = Scouring::create([
            'driver_checkpoint_log_id' => $checkpointLog->id,
            'customer_id' => $booking->user_id,
            'booking_id' => $booking->id,
            'points' => 25,
        ]);
        $scouring->update(['points' => 40]);
        $scouring->delete();

        $this->assertDatabaseHas('point_audit_logs', [
            'action' => 'granted',
            'actor_id' => $admin->id,
            'points_before' => 0,
            'points_after' => 25,
            'points_delta' => 25,
        ]);
        $this->assertDatabaseHas('point_audit_logs', [
            'action' => 'adjusted',
            'points_before' => 25,
            'points_after' => 40,
            'points_delta' => 15,
        ]);
        $this->assertDatabaseHas('point_audit_logs', [
            'action' => 'revoked',
            'scouring_id' => null,
            'points_before' => 40,
            'points_after' => 0,
            'points_delta' => -40,
        ]);
        $this->assertDatabaseCount('point_audit_logs', 3);
    }

    public function test_admin_can_filter_and_view_points_audit_logs(): void
    {
        Sanctum::actingAs($this->user('admin'));
        [$booking, $checkpointLog] = $this->bookingAndCheckpointLog();
        Scouring::create([
            'driver_checkpoint_log_id' => $checkpointLog->id,
            'customer_id' => $booking->user_id,
            'booking_id' => $booking->id,
            'points' => 30,
        ]);

        $this->getJson('/api/admin/point-audit-logs?action=granted&period=last_7_days')
            ->assertOk()
            ->assertJsonPath('summary.total', 1)
            ->assertJsonPath('logs.data.0.action', 'granted')
            ->assertJsonPath('logs.data.0.points_delta', 30)
            ->assertJsonPath('logs.data.0.booking.id', $booking->id);

        $this->getJson('/api/admin/point-audit-logs?action=invalid')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('action');
    }

    public function test_non_admin_cannot_read_points_audit_logs(): void
    {
        Sanctum::actingAs($this->user('customer'));

        $this->getJson('/api/admin/point-audit-logs')->assertForbidden();
    }

    private function bookingAndCheckpointLog(): array
    {
        $driver = $this->user('driver');
        $customer = $this->user('customer');
        $from = Checkpoint::create(['name' => 'From']);
        $to = Checkpoint::create(['name' => 'To']);
        $trip = Trip::create([
            'driver_id' => $driver->id,
            'status' => 'scheduled',
            'departure_date' => now()->addDay(),
            'total_seats' => 10,
            'available_seats' => 9,
        ]);
        $booking = Booking::create([
            'user_id' => $customer->id,
            'driver_id' => $driver->id,
            'trip_id' => $trip->id,
            'pickup_checkpoint_id' => $from->id,
            'dropoff_checkpoint_id' => $to->id,
            'seats_count' => 1,
            'status' => 'confirmed',
        ]);
        $checkpointLog = DriverCheckpointLog::create([
            'driver_id' => $driver->id,
            'trip_id' => $trip->id,
            'checkpoint_id' => $from->id,
            'scanned_at' => now(),
        ]);

        return [$booking, $checkpointLog];
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role, 'status' => 'active']);
    }
}
