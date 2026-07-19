<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Checkpoint;
use App\Models\Complaint;
use App\Models\DriverCheckpointLog;
use App\Models\DriverRequest;
use App\Models\PrivateTripRequest;
use App\Models\Rating;
use App\Models\Role;
use App\Models\Scouring;
use App\Models\Seat;
use App\Models\Trip;
use App\Models\TripCheckpoint;
use App\Models\User;
use App\Models\WaitingList;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();

        Scouring::truncate();
        DriverCheckpointLog::truncate();
        DriverRequest::truncate();
        WaitingList::truncate();
        PrivateTripRequest::truncate();
        Complaint::truncate();
        Rating::truncate();
        Booking::truncate();
        Seat::truncate();
        TripCheckpoint::truncate();
        Checkpoint::truncate();
        Trip::truncate();
        Role::truncate();
        User::truncate();

        Schema::enableForeignKeyConstraints();

        $admin = User::create([
            'name' => 'System Admin',
            'email' => 'admin@shahbaa.test',
            'password' => 'password',
            'phone' => '0999000001',
            'gender' => 'male',
            'role' => 'admin',
            'status' => 'active',
        ]);

        $driverOne = User::create([
            'name' => 'Ahmad Driver',
            'email' => 'driver1@shahbaa.test',
            'password' => 'password',
            'phone' => '0999000002',
            'gender' => 'male',
            'role' => 'driver',
            'status' => 'active',
        ]);

        $driverTwo = User::create([
            'name' => 'Maya Driver',
            'email' => 'driver2@shahbaa.test',
            'password' => 'password',
            'phone' => '0999000003',
            'gender' => 'female',
            'role' => 'driver',
            'status' => 'active',
        ]);

        $customerOne = User::create([
            'name' => 'Lina Customer',
            'email' => 'customer1@shahbaa.test',
            'password' => 'password',
            'phone' => '0999000004',
            'gender' => 'female',
            'role' => 'customer',
            'status' => 'active',
        ]);

        $customerTwo = User::create([
            'name' => 'Omar Customer',
            'email' => 'customer2@shahbaa.test',
            'password' => 'password',
            'phone' => '0999000005',
            'gender' => 'male',
            'role' => 'customer',
            'status' => 'active',
        ]);

        $customerThree = User::create([
            'name' => 'Nour Customer',
            'email' => 'customer3@shahbaa.test',
            'password' => 'password',
            'phone' => '0999000006',
            'gender' => 'female',
            'role' => 'customer',
            'status' => 'inactive',
        ]);

        foreach ([
            [$admin, 'Admin'],
            [$driverOne, 'Driver'],
            [$driverTwo, 'Driver'],
            [$customerOne, 'Customer'],
            [$customerTwo, 'Customer'],
            [$customerThree, 'Customer'],
        ] as [$user, $role]) {
            Role::create([
                'name' => $role,
                'user_id' => $user->id,
            ]);
        }

        $aleppoCenter = Checkpoint::create([
            'name' => 'Aleppo Center',
            'location' => 'Saadallah Al-Jabiri Square',
            'governorate' => 'Aleppo',
        ]);

        $university = Checkpoint::create([
            'name' => 'University Gate',
            'location' => 'Aleppo University',
            'governorate' => 'Aleppo',
        ]);

        $newAleppo = Checkpoint::create([
            'name' => 'New Aleppo',
            'location' => 'New Aleppo Main Road',
            'governorate' => 'Aleppo',
        ]);

        $damascusStation = Checkpoint::create([
            'name' => 'Damascus Station',
            'location' => 'Al-Hijaz Station',
            'governorate' => 'Damascus',
        ]);

        $tripOne = Trip::create([
            'driver_id' => $driverOne->id,
            'type' => 'standard',
            'point_price' => 25,
            'money_price' => 15000,
            'status' => 'scheduled',
            'departure_date' => now()->addDay()->setTime(8, 30),
            'arrival_date' => now()->addDay()->setTime(10, 0),
            'total_seats' => 12,
            'available_seats' => 8,
            'earned_points' => 120,
        ]);

        $tripTwo = Trip::create([
            'driver_id' => $driverTwo->id,
            'type' => 'private',
            'point_price' => 40,
            'money_price' => 45000,
            'status' => 'active',
            'departure_date' => now()->addDays(2)->setTime(14, 0),
            'arrival_date' => now()->addDays(2)->setTime(17, 30),
            'total_seats' => 8,
            'available_seats' => 5,
            'earned_points' => 80,
        ]);

        foreach ([$tripOne, $tripTwo] as $trip) {
            for ($seat = 1; $seat <= $trip->total_seats; $seat++) {
                Seat::create([
                    'trip_id' => $trip->id,
                    'seat_number' => 'S' . str_pad((string) $seat, 2, '0', STR_PAD_LEFT),
                ]);
            }
        }

        foreach ([
            [$tripOne, $aleppoCenter, 'Start point'],
            [$tripOne, $university, 'Student pickup point'],
            [$tripOne, $newAleppo, 'Final dropoff point'],
            [$tripTwo, $aleppoCenter, 'Private trip origin'],
            [$tripTwo, $damascusStation, 'Private trip destination'],
        ] as [$trip, $checkpoint, $description]) {
            TripCheckpoint::create([
                'trip_id' => $trip->id,
                'checkpoint_id' => $checkpoint->id,
                'description' => $description,
            ]);
        }

        $bookingOne = Booking::create([
            'user_id' => $customerOne->id,
            'driver_id' => $driverOne->id,
            'trip_id' => $tripOne->id,
            'pickup_checkpoint_id' => $aleppoCenter->id,
            'dropoff_checkpoint_id' => $university->id,
            'seats_count' => 2,
            'status' => 'confirmed',
        ]);

        $bookingTwo = Booking::create([
            'user_id' => $customerTwo->id,
            'driver_id' => $driverTwo->id,
            'trip_id' => $tripTwo->id,
            'pickup_checkpoint_id' => $aleppoCenter->id,
            'dropoff_checkpoint_id' => $damascusStation->id,
            'seats_count' => 1,
            'status' => 'pending',
        ]);

        Rating::create([
            'customer_id' => $customerOne->id,
            'trip_id' => $tripOne->id,
            'rate' => 5,
            'comment' => 'Comfortable ride and clear checkpoint timing.',
        ]);

        Rating::create([
            'customer_id' => $customerTwo->id,
            'trip_id' => $tripTwo->id,
            'rate' => 4,
            'comment' => 'Good service, slight delay at departure.',
        ]);

        Complaint::create([
            'customer_id' => $customerTwo->id,
            'comment' => 'The pickup location was not clear enough.',
            'status' => 'pending',
        ]);

        Complaint::create([
            'customer_id' => $customerThree->id,
            'comment' => 'I could not find available seats for my preferred time.',
            'status' => 'resolved',
        ]);

        PrivateTripRequest::create([
            'user_id' => $customerOne->id,
            'from_location' => 'Aleppo',
            'to_location' => 'Homs',
            'status' => 'pending',
        ]);

        PrivateTripRequest::create([
            'user_id' => $customerTwo->id,
            'from_location' => 'Aleppo',
            'to_location' => 'Damascus',
            'status' => 'approved',
        ]);

        WaitingList::create([
            'user_id' => $customerThree->id,
            'trip_id' => $tripOne->id,
            'status' => 'waiting',
        ]);

        WaitingList::create([
            'user_id' => $customerOne->id,
            'trip_id' => $tripTwo->id,
            'status' => 'notified',
        ]);

        DriverRequest::create([
            'driver_id' => $driverOne->id,
            'trip_id' => $tripOne->id,
            'notes' => 'Requesting approval for tomorrow morning route.',
            'status' => 'approved',
        ]);

        DriverRequest::create([
            'driver_id' => $driverTwo->id,
            'trip_id' => $tripTwo->id,
            'notes' => 'Private route needs station confirmation.',
            'status' => 'pending',
        ]);

        $logOne = DriverCheckpointLog::create([
            'driver_id' => $driverOne->id,
            'trip_id' => $tripOne->id,
            'checkpoint_id' => $aleppoCenter->id,
            'scanned_at' => now()->subHours(2),
        ]);

        DriverCheckpointLog::create([
            'driver_id' => $driverOne->id,
            'trip_id' => $tripOne->id,
            'checkpoint_id' => $university->id,
            'scanned_at' => now()->subHour(),
        ]);

        Scouring::create([
            'driver_checkpoint_log_id' => $logOne->id,
            'customer_id' => $customerOne->id,
            'booking_id' => $bookingOne->id,
            'points' => 25,
        ]);

        Scouring::create([
            'driver_checkpoint_log_id' => $logOne->id,
            'customer_id' => $customerTwo->id,
            'booking_id' => $bookingTwo->id,
            'points' => 15,
        ]);
    }
}
