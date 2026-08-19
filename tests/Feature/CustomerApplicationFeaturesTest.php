<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Checkpoint;
use App\Models\Complaint;
use App\Models\Rating;
use App\Models\Trip;
use App\Models\User;
use App\Models\WaitingList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerApplicationFeaturesTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_endpoints_require_authentication(): void
    {
        $this->getJson('/api/customer/trips')->assertUnauthorized();
        $this->getJson('/api/customer/governorates')->assertUnauthorized();
        $this->postJson('/api/logout')->assertUnauthorized();
    }

    public function test_customer_can_logout_and_revoke_the_current_token(): void
    {
        $customer = $this->user();
        $token = $customer->createToken('customer-app');

        $this->withToken($token->plainTextToken)
            ->postJson('/api/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Logged out successfully');

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $token->accessToken->id,
        ]);
    }

    public function test_governorates_are_unique_and_checkpoints_are_filtered(): void
    {
        Sanctum::actingAs($this->user());
        Checkpoint::create(['name' => 'A', 'governorate' => 'Damascus']);
        Checkpoint::create(['name' => 'B', 'governorate' => 'Damascus']);
        Checkpoint::create(['name' => 'C', 'governorate' => 'Aleppo']);

        $this->getJson('/api/customer/governorates')
            ->assertOk()
            ->assertJsonPath('governorates', ['Aleppo', 'Damascus']);

        $this->postJson('/api/customer/checkpoints/by-governorate', [
            'governorate' => 'Damascus',
        ])->assertOk()->assertJsonCount(2);
    }

    public function test_customer_sees_only_their_own_booked_trips(): void
    {
        $customer = $this->user();
        $other = $this->user();
        [$trip, $from, $to] = $this->trip();
        $own = $this->booking($customer, $trip, $from, $to);
        $this->booking($other, $trip, $from, $to);
        Sanctum::actingAs($customer);

        $this->getJson('/api/customer/trips')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->id);
    }

    public function test_ticket_details_are_owned_and_scan_state_is_enforced(): void
    {
        $customer = $this->user();
        [$trip, $from, $to] = $this->trip();
        $booking = $this->booking($customer, $trip, $from, $to);
        Sanctum::actingAs($customer);

        $this->postJson("/api/customer/trips/{$trip->id}/ticket/before-scan")
            ->assertOk()
            ->assertJsonPath('booking.id', $booking->id);

        $this->postJson("/api/customer/trips/{$trip->id}/ticket/after-scan")
            ->assertUnprocessable();

        $booking->update(['boarded_at' => now()]);
        $this->postJson("/api/customer/trips/{$trip->id}/ticket/after-scan")
            ->assertOk();

        Sanctum::actingAs($this->user());
        $this->postJson("/api/customer/trips/{$trip->id}/ticket/after-scan")
            ->assertNotFound();
    }

    public function test_leaving_waiting_list_never_deletes_another_customers_entry(): void
    {
        $customer = $this->user();
        $other = $this->user();
        [$trip, $from, $to] = $this->trip();
        foreach ([$customer, $other] as $user) {
            WaitingList::create([
                'user_id' => $user->id,
                'trip_id' => $trip->id,
                'pickup_checkpoint_id' => $from->id,
                'dropoff_checkpoint_id' => $to->id,
                'seats_count' => 1,
            ]);
        }
        Sanctum::actingAs($customer);

        $this->deleteJson("/api/customer/waiting-list/{$trip->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('waiting_lists', ['user_id' => $customer->id]);
        $this->assertDatabaseHas('waiting_lists', ['user_id' => $other->id]);
    }

    public function test_customer_cannot_modify_another_customers_feedback(): void
    {
        $owner = $this->user();
        $attacker = $this->user();
        [$trip] = $this->trip();
        $rating = Rating::create([
            'customer_id' => $owner->id,
            'trip_id' => $trip->id,
            'rate' => 4,
        ]);
        $complaint = Complaint::create([
            'customer_id' => $owner->id,
            'comment' => 'Late',
        ]);
        Sanctum::actingAs($attacker);

        $this->patchJson("/api/customer/ratings/{$rating->id}", ['rate' => 1])
            ->assertForbidden();
        $this->deleteJson("/api/customer/complaints/{$complaint->id}")
            ->assertForbidden();
    }

    private function user(string $role = 'customer'): User
    {
        return User::factory()->create(['role' => $role, 'status' => 'active']);
    }

    private function trip(): array
    {
        $driver = $this->user('driver');
        $from = Checkpoint::create(['name' => 'From', 'governorate' => 'Damascus']);
        $to = Checkpoint::create(['name' => 'To', 'governorate' => 'Damascus']);
        $trip = Trip::create([
            'driver_id' => $driver->id,
            'status' => 'scheduled',
            'departure_date' => now()->addDay(),
            'arrival_date' => now()->addDay()->addHours(2),
            'total_seats' => 5,
            'available_seats' => 5,
        ]);
        $trip->checkpoints()->create(['checkpoint_id' => $from->id]);
        $trip->checkpoints()->create(['checkpoint_id' => $to->id]);

        return [$trip, $from, $to];
    }

    private function booking(User $user, Trip $trip, Checkpoint $from, Checkpoint $to): Booking
    {
        return Booking::create([
            'user_id' => $user->id,
            'driver_id' => $trip->driver_id,
            'trip_id' => $trip->id,
            'pickup_checkpoint_id' => $from->id,
            'dropoff_checkpoint_id' => $to->id,
            'seats_count' => 1,
            'status' => 'pending',
        ]);
    }
}
