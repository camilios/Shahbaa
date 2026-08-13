<?php

namespace Tests\Feature;

use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RealtimeTrackingAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private const SERVICE_SECRET = 'testing-tracking-secret';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'tracking.service_secret' => self::SERVICE_SECRET,
            'tracking.trackable_trip_statuses' => ['active'],
        ]);
    }

    public function test_unauthenticated_user_cannot_request_authorization(): void
    {
        $trip = $this->tripFor($this->user('driver'));

        $this->postJson('/api/v1/realtime/authorize', [
            'trip_id' => $trip->id,
            'action' => 'publish',
        ], $this->serviceHeaders())->assertUnauthorized();
    }

    public function test_assigned_active_driver_can_publish_for_an_active_trip(): void
    {
        $driver = $this->user('driver');
        $trip = $this->tripFor($driver);
        Sanctum::actingAs($driver);

        $this->authorizeTrip($trip, 'publish')
            ->assertOk()
            ->assertExactJson([
                'authorized' => true,
                'user_id' => $driver->id,
                'role' => 'driver',
                'trip_id' => $trip->id,
                'action' => 'publish',
            ]);
    }

    public function test_driver_cannot_publish_for_another_drivers_trip(): void
    {
        $driver = $this->user('driver');
        $trip = $this->tripFor($this->user('driver'));
        Sanctum::actingAs($driver);

        $this->authorizeTrip($trip, 'publish')->assertForbidden();
    }

    public function test_driver_cannot_subscribe_as_an_admin(): void
    {
        $driver = $this->user('driver');
        $trip = $this->tripFor($driver);
        Sanctum::actingAs($driver);

        $this->authorizeTrip($trip, 'subscribe')->assertForbidden();
    }

    public function test_active_admin_can_subscribe_to_an_active_trip(): void
    {
        $driver = $this->user('driver');
        $admin = $this->user('admin');
        $trip = $this->tripFor($driver);
        Sanctum::actingAs($admin);

        $this->authorizeTrip($trip, 'subscribe')->assertOk();
    }

    public function test_inactive_user_and_non_active_trip_are_rejected(): void
    {
        $driver = $this->user('driver', 'inactive');
        $trip = $this->tripFor($driver);
        Sanctum::actingAs($driver);
        $this->authorizeTrip($trip, 'publish')->assertForbidden();

        $driver->update(['status' => 'active']);
        $trip->update(['status' => 'scheduled']);
        $this->authorizeTrip($trip, 'publish')->assertForbidden();
    }

    public function test_invalid_payload_is_rejected(): void
    {
        Sanctum::actingAs($this->user('admin'));

        $this->postJson('/api/v1/realtime/authorize', [
            'trip_id' => 999999,
            'action' => 'watch',
        ], $this->serviceHeaders())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['trip_id', 'action']);
    }

    public function test_missing_or_wrong_service_secret_is_rejected(): void
    {
        $driver = $this->user('driver');
        $trip = $this->tripFor($driver);
        Sanctum::actingAs($driver);
        $payload = ['trip_id' => $trip->id, 'action' => 'publish'];

        $this->postJson('/api/v1/realtime/authorize', $payload)->assertForbidden();
        $this->postJson('/api/v1/realtime/authorize', $payload, [
            'X-Tracking-Service-Key' => 'wrong-secret',
        ])->assertForbidden();
    }

    private function authorizeTrip(Trip $trip, string $action)
    {
        return $this->postJson('/api/v1/realtime/authorize', [
            'trip_id' => $trip->id,
            'action' => $action,
        ], $this->serviceHeaders());
    }

    private function serviceHeaders(): array
    {
        return ['X-Tracking-Service-Key' => self::SERVICE_SECRET];
    }

    private function user(string $role, string $status = 'active'): User
    {
        return User::factory()->create(compact('role', 'status'));
    }

    private function tripFor(User $driver, string $status = 'active'): Trip
    {
        return Trip::query()->create([
            'driver_id' => $driver->id,
            'type' => 'standard',
            'status' => $status,
        ]);
    }
}
