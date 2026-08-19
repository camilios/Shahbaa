<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Checkpoint;
use App\Models\Complaint;
use App\Models\Rating;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_confirmation_notifies_the_customer(): void
    {
        [$customer, , , $booking] = $this->bookingFixture();
        $booking->update(['status' => 'confirmed']);

        $this->assertNotification($customer, 'booking_confirmed', 'booking_id', $booking->id);
    }

    public function test_trip_cancellation_notifies_each_active_passenger_once(): void
    {
        [$customer, $trip] = $this->bookingFixture();
        $trip->update(['status' => 'cancelled']);

        $this->assertNotification($customer, 'trip_cancelled', 'trip_id', $trip->id);
        $this->assertSame(1, $customer->notifications()->count());
    }

    public function test_replies_to_complaints_and_ratings_notify_the_customer(): void
    {
        [$customer, $trip] = $this->bookingFixture();
        $complaint = Complaint::create(['customer_id' => $customer->id, 'comment' => 'Late']);
        $rating = Rating::create(['customer_id' => $customer->id, 'trip_id' => $trip->id, 'rate' => 4]);

        $complaint->update(['admin_reply' => 'Reviewed', 'status' => 'answered']);
        $rating->update(['admin_reply' => 'Thank you']);

        $this->assertNotification($customer, 'complaint_replied', 'complaint_id', $complaint->id);
        $this->assertNotification($customer, 'rating_replied', 'rating_id', $rating->id);
    }

    public function test_user_can_list_and_mark_only_their_own_notifications_as_read(): void
    {
        [$customer, , , $booking] = $this->bookingFixture();
        $booking->update(['status' => 'confirmed']);
        $notification = $customer->notifications()->firstOrFail();
        Sanctum::actingAs($customer);

        $this->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->assertJsonPath('notifications.data.0.data.event', 'booking_confirmed');

        $this->patchJson("/api/notifications/{$notification->id}/read")
            ->assertOk()
            ->assertJsonPath('unread_count', 0);
        $this->assertNotNull($notification->fresh()->read_at);

        [$other, , , $otherBooking] = $this->bookingFixture();
        $otherBooking->update(['status' => 'confirmed']);
        $otherNotification = $other->notifications()->firstOrFail();
        $this->patchJson("/api/notifications/{$otherNotification->id}/read")
            ->assertNotFound();
    }

    public function test_user_can_mark_all_notifications_as_read(): void
    {
        [$customer, $trip, , $booking] = $this->bookingFixture();
        $booking->update(['status' => 'confirmed']);
        $trip->update(['status' => 'cancelled']);
        Sanctum::actingAs($customer);

        $this->patchJson('/api/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('unread_count', 0);

        $this->assertSame(0, $customer->unreadNotifications()->count());
    }

    public function test_device_tokens_can_be_registered_listed_reassigned_and_deleted(): void
    {
        $customer = $this->user('customer');
        Sanctum::actingAs($customer);

        $created = $this->postJson('/api/device-tokens', [
            'token' => 'device-token-123',
            'platform' => 'android',
            'device_name' => 'Pixel',
            'app_version' => '1.0.0',
        ])->assertCreated()->json('device_token');

        $this->getJson('/api/device-tokens')->assertOk()->assertJsonCount(1);

        $other = $this->user('customer');
        Sanctum::actingAs($other);
        $this->postJson('/api/device-tokens', [
            'token' => 'device-token-123',
            'platform' => 'ios',
        ])->assertOk()->assertJsonPath('device_token.user_id', $other->id);

        $this->deleteJson("/api/device-tokens/{$created['id']}")->assertNoContent();
        $this->assertDatabaseMissing('device_tokens', ['id' => $created['id']]);
    }

    public function test_notification_and_device_endpoints_require_authentication(): void
    {
        $this->getJson('/api/notifications')->assertUnauthorized();
        $this->postJson('/api/device-tokens', [])->assertUnauthorized();
    }

    private function assertNotification(User $user, string $event, string $key, int $value): void
    {
        $notification = $user->notifications()
            ->get()
            ->first(fn ($item) => $item->data['event'] === $event);

        $this->assertNotNull($notification);
        $this->assertSame($value, $notification->data[$key]);
    }

    private function bookingFixture(): array
    {
        $customer = $this->user('customer');
        $driver = $this->user('driver');
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
            'status' => 'pending',
        ]);

        return [$customer, $trip, $driver, $booking];
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role, 'status' => 'active']);
    }
}
