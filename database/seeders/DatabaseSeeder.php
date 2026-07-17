<?php

namespace Database\Seeders;

use App\Models\Checkpoint;
use App\Models\Role;
use App\Models\Trip;
use App\Models\TripCheckpoint;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        Role::firstOrCreate(['name' => 'Admin']);
        $driverRole = Role::firstOrCreate(['name' => 'Driver']);
        Role::firstOrCreate(['name' => 'Customer']);

        // Demo driver account. Drivers cannot self-register; the company
        // issues their credentials. This seeds one so the login flow can
        // be tested out of the box.
        $driver = User::firstOrCreate(
            ['email' => 'driver@shahbaa.com'],
            [
                'full_name' => 'Demo Driver',
                'father_name' => 'Company',
                'phone' => '0963900000001',
                'gender' => 'male',
                'national_number' => 'DRIVER-0001',
                'role_id' => $driverRole->id,
                'status' => 'active',
                'password' => 'password123',
            ]
        );

        // Route stops for the demo trip (Damascus -> Khan Arnabeh -> Sweida).
        $damascus = Checkpoint::firstOrCreate(['name' => 'دمشق'], ['governorate' => 'دمشق']);
        $khanArnabeh = Checkpoint::firstOrCreate(['name' => 'خان أرنبة'], ['governorate' => 'القنيطرة']);
        $sweida = Checkpoint::firstOrCreate(['name' => 'السويداء'], ['governorate' => 'السويداء']);

        // Demo trip assigned to the driver (20 seats, none booked yet).
        // Real passengers come from real bookings, so none are seeded.
        $trip = Trip::firstOrCreate(
            [
                'driver_id' => $driver->id,
                'departure_date' => '2026-07-20 12:00:00',
            ],
            [
                'type' => 'standard',
                'point_price' => 0,
                'money_price' => 15000,
                'status' => 'pending',
                'arrival_date' => '2026-07-20 14:00:00',
                'total_seats' => 20,
                'available_seats' => 20,
                'earned_points' => 10,
            ]
        );

        foreach ([$damascus, $khanArnabeh, $sweida] as $checkpoint) {
            TripCheckpoint::firstOrCreate([
                'trip_id' => $trip->id,
                'checkpoint_id' => $checkpoint->id,
            ]);
        }
    }
}
