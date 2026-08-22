<?php

namespace Tests\Feature;

use App\Models\Checkpoint;
use App\Models\Governorate;
use App\Models\Seat;
use App\Models\Trip;
use App\Models\TripCheckpoint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BookingCheckpointGovernorateTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_trip_route_must_start_and_end_in_selected_governorates(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $driver = User::factory()->create(['role' => 'driver', 'status' => 'active']);
        $aleppoGovernorate = Governorate::create(['name' => 'Aleppo']);
        $damascusGovernorate = Governorate::create(['name' => 'Damascus']);
        $aleppo = Checkpoint::create(['name' => 'Aleppo', 'governorate' => 'Aleppo', 'governorate_id' => $aleppoGovernorate->id]);
        $damascus = Checkpoint::create(['name' => 'Damascus', 'governorate' => 'Damascus', 'governorate_id' => $damascusGovernorate->id]);
        Sanctum::actingAs($admin);

        $this->postJson('/api/trips', [
            'driver_id' => $driver->id,
            'type' => 'standard',
            'source_governorate_id' => $damascusGovernorate->id,
            'destination_governorate_id' => $aleppoGovernorate->id,
            'total_seats' => 10,
            'checkpoint_ids' => [$aleppo->id, $damascus->id],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('source_governorate_id');

        $this->postJson('/api/trips', [
            'driver_id' => $driver->id,
            'type' => 'standard',
            'source_governorate_id' => $aleppoGovernorate->id,
            'destination_governorate_id' => $damascusGovernorate->id,
            'total_seats' => 10,
            'checkpoint_ids' => [$aleppo->id, $damascus->id],
        ])->assertCreated()
            ->assertJsonPath('source_governorate', 'Aleppo')
            ->assertJsonPath('destination_governorate', 'Damascus');
    }

    public function test_pickup_and_dropoff_must_match_route_endpoint_governorates(): void
    {
        [$customer, $trip, $source, $middle, $destination] = $this->routeFixture();
        Sanctum::actingAs($customer);

        $this->postJson('/api/bookings', $this->payload($trip, $middle, $destination))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('pickup_checkpoint_id')
            ->assertJsonPath(
                'errors.pickup_checkpoint_id.0',
                'نقطة الصعود يجب أن تكون ضمن محافظة انطلاق الرحلة.'
            );

        $this->postJson('/api/bookings', $this->payload($trip, $source, $middle))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('dropoff_checkpoint_id')
            ->assertJsonPath(
                'errors.dropoff_checkpoint_id.0',
                'نقطة النزول يجب أن تكون ضمن محافظة وصول الرحلة.'
            );

        $this->postJson('/api/bookings', $this->payload($trip, $source, $destination))
            ->assertCreated()
            ->assertJsonPath('pickup_checkpoint_id', $source->id)
            ->assertJsonPath('dropoff_checkpoint_id', $destination->id);
    }

    private function payload(Trip $trip, Checkpoint $pickup, Checkpoint $dropoff): array
    {
        return [
            'trip_id' => $trip->id,
            'pickup_checkpoint_id' => $pickup->id,
            'dropoff_checkpoint_id' => $dropoff->id,
            'seats_count' => 1,
        ];
    }

    private function routeFixture(): array
    {
        $driver = User::factory()->create(['role' => 'driver', 'status' => 'active']);
        $customer = User::factory()->create(['role' => 'customer', 'status' => 'active']);
        $aleppo = Governorate::create(['name' => 'Aleppo']);
        $hama = Governorate::create(['name' => 'Hama']);
        $damascus = Governorate::create(['name' => 'Damascus']);
        $trip = Trip::create([
            'driver_id' => $driver->id,
            'status' => 'scheduled',
            'source_governorate' => 'Aleppo',
            'destination_governorate' => 'Damascus',
            'source_governorate_id' => $aleppo->id,
            'destination_governorate_id' => $damascus->id,
            'departure_date' => now()->addDay(),
            'total_seats' => 2,
            'available_seats' => 2,
        ]);
        $source = Checkpoint::create(['name' => 'Source', 'governorate' => 'Aleppo', 'governorate_id' => $aleppo->id]);
        $middle = Checkpoint::create(['name' => 'Middle', 'governorate' => 'Hama', 'governorate_id' => $hama->id]);
        $destination = Checkpoint::create(['name' => 'Destination', 'governorate' => 'Damascus', 'governorate_id' => $damascus->id]);

        foreach ([$source, $middle, $destination] as $checkpoint) {
            TripCheckpoint::create(['trip_id' => $trip->id, 'checkpoint_id' => $checkpoint->id]);
        }
        Seat::create(['trip_id' => $trip->id, 'seat_number' => 'S01']);
        Seat::create(['trip_id' => $trip->id, 'seat_number' => 'S02']);

        return [$customer, $trip, $source, $middle, $destination];
    }
}
