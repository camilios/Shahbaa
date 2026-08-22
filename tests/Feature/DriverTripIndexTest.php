<?php

namespace Tests\Feature;

use App\Models\Checkpoint;
use App\Models\Trip;
use App\Models\TripCheckpoint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DriverTripIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_driver_trip_index_returns_ordered_source_and_destination(): void
    {
        $driver = User::factory()->create(['role' => 'driver', 'status' => 'active']);
        $trip = Trip::create([
            'driver_id' => $driver->id,
            'status' => 'scheduled',
            'departure_date' => now()->addDay(),
            'total_seats' => 10,
            'available_seats' => 10,
        ]);
        $source = Checkpoint::create([
            'name' => 'Aleppo Station',
            'location' => 'Aleppo Center',
            'governorate' => 'Aleppo',
        ]);
        $middle = Checkpoint::create(['name' => 'Hama Station']);
        $destination = Checkpoint::create([
            'name' => 'Damascus Station',
            'location' => 'Damascus Center',
            'governorate' => 'Damascus',
        ]);

        foreach ([$source, $middle, $destination] as $checkpoint) {
            TripCheckpoint::create([
                'trip_id' => $trip->id,
                'checkpoint_id' => $checkpoint->id,
            ]);
        }

        Sanctum::actingAs($driver);

        $this->getJson('/api/driver/trips')
            ->assertOk()
            ->assertJsonPath('data.0.from', 'Aleppo Station')
            ->assertJsonPath('data.0.to', 'Damascus Station')
            ->assertJsonPath('data.0.source.id', $source->id)
            ->assertJsonPath('data.0.source.location', 'Aleppo Center')
            ->assertJsonPath('data.0.source.governorate', 'Aleppo')
            ->assertJsonPath('data.0.destination.id', $destination->id)
            ->assertJsonPath('data.0.destination.location', 'Damascus Center')
            ->assertJsonPath('data.0.destination.governorate', 'Damascus')
            ->assertJsonMissingPath('data.0.checkpoints');
    }

    public function test_trip_without_checkpoints_returns_null_route_endpoints(): void
    {
        $driver = User::factory()->create(['role' => 'driver', 'status' => 'active']);
        Trip::create([
            'driver_id' => $driver->id,
            'status' => 'scheduled',
            'departure_date' => now()->addDay(),
            'total_seats' => 10,
            'available_seats' => 10,
        ]);
        Sanctum::actingAs($driver);

        $this->getJson('/api/driver/trips')
            ->assertOk()
            ->assertJsonPath('data.0.from', null)
            ->assertJsonPath('data.0.to', null)
            ->assertJsonPath('data.0.source', null)
            ->assertJsonPath('data.0.destination', null);
    }
}
