<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Checkpoint;
use App\Models\Complaint;
use App\Models\DriverCheckpointLog;
use App\Models\DriverRequest;
use App\Models\DeviceToken;
use App\Models\PointAuditLog;
use App\Models\PrivateTripRequest;
use App\Models\Rating;
use App\Models\Role;
use App\Models\Scouring;
use App\Models\Seat;
use App\Models\Trip;
use App\Models\TripCheckpoint;
use App\Models\TripObjection;
use App\Models\User;
use App\Models\WaitingList;
use App\Notifications\SystemEventNotification;
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

        PointAuditLog::truncate();
        DeviceToken::truncate();
        \Illuminate\Support\Facades\DB::table('notifications')->truncate();
        Scouring::truncate();
        DriverCheckpointLog::truncate();
        DriverRequest::truncate();
        TripObjection::truncate();
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

        $driverThree = User::create([
            'name' => 'Khaled Driver',
            'email' => 'driver3@shahbaa.test',
            'password' => 'password',
            'phone' => '0999000007',
            'gender' => 'male',
            'role' => 'driver',
            'status' => 'inactive',
        ]);

        $extraCustomers = collect([
            ['Rami Customer', 'customer4@shahbaa.test', '0999000008', 'male', 'active'],
            ['Sara Customer', 'customer5@shahbaa.test', '0999000009', 'female', 'active'],
            ['Yazan Customer', 'customer6@shahbaa.test', '0999000010', 'male', 'active'],
            ['Hala Customer', 'customer7@shahbaa.test', '0999000011', 'female', 'active'],
            ['Samer Customer', 'customer8@shahbaa.test', '0999000012', 'male', 'blocked'],
        ])->map(fn (array $data) => User::create([
            'name' => $data[0],
            'email' => $data[1],
            'password' => 'password',
            'phone' => $data[2],
            'gender' => $data[3],
            'role' => 'customer',
            'status' => $data[4],
        ]));

        foreach ([
            [$admin, 'Admin'],
            [$driverOne, 'Driver'],
            [$driverTwo, 'Driver'],
            [$customerOne, 'Customer'],
            [$customerTwo, 'Customer'],
            [$customerThree, 'Customer'],
            [$driverThree, 'Driver'],
        ] as [$user, $role]) {
            Role::create([
                'name' => $role,
                'user_id' => $user->id,
            ]);
        }

        foreach ($extraCustomers as $customer) {
            Role::create(['name' => 'Customer', 'user_id' => $customer->id]);
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

        $homsStation = Checkpoint::create(['name' => 'Homs Station', 'location' => 'Central Bus Station', 'governorate' => 'Homs']);
        $hamaStation = Checkpoint::create(['name' => 'Hama Station', 'location' => 'Aleppo Road', 'governorate' => 'Hama']);
        $latakiaStation = Checkpoint::create(['name' => 'Latakia Station', 'location' => 'Sports City', 'governorate' => 'Latakia']);
        $tartusStation = Checkpoint::create(['name' => 'Tartus Station', 'location' => 'Corniche Road', 'governorate' => 'Tartus']);

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

        $tripThree = Trip::create([
            'driver_id' => $driverOne->id, 'type' => 'entertainment', 'point_price' => 60,
            'money_price' => 80000, 'status' => 'scheduled',
            'departure_date' => now()->addDays(5)->setTime(7, 0), 'arrival_date' => now()->addDays(5)->setTime(13, 0),
            'total_seats' => 20, 'available_seats' => 15, 'earned_points' => 150,
        ]);
        $tripFour = Trip::create([
            'driver_id' => $driverTwo->id, 'type' => 'standard', 'point_price' => 30,
            'money_price' => 25000, 'status' => 'completed',
            'departure_date' => now()->subDays(5)->setTime(9, 0), 'arrival_date' => now()->subDays(5)->setTime(12, 0),
            'total_seats' => 16, 'available_seats' => 10, 'earned_points' => 90,
        ]);
        $tripFive = Trip::create([
            'driver_id' => $driverOne->id, 'type' => 'standard', 'point_price' => 35,
            'money_price' => 30000, 'status' => 'completed',
            'departure_date' => now()->subDays(20)->setTime(11, 0), 'arrival_date' => now()->subDays(20)->setTime(15, 0),
            'total_seats' => 18, 'available_seats' => 12, 'earned_points' => 100,
        ]);
        $tripSix = Trip::create([
            'driver_id' => $driverTwo->id, 'type' => 'private', 'point_price' => 50,
            'money_price' => 60000, 'status' => 'cancelled',
            'departure_date' => now()->subMonths(2), 'arrival_date' => now()->subMonths(2)->addHours(4),
            'total_seats' => 10, 'available_seats' => 10, 'earned_points' => 110,
        ]);

        foreach ([$tripOne, $tripTwo, $tripThree, $tripFour, $tripFive, $tripSix] as $trip) {
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
            [$tripThree, $aleppoCenter, 'Entertainment trip origin'],
            [$tripThree, $latakiaStation, 'Coastal stop'],
            [$tripThree, $tartusStation, 'Entertainment destination'],
            [$tripFour, $damascusStation, 'Origin'],
            [$tripFour, $homsStation, 'Destination'],
            [$tripFive, $aleppoCenter, 'Origin'],
            [$tripFive, $hamaStation, 'Intermediate stop'],
            [$tripFive, $homsStation, 'Destination'],
            [$tripSix, $homsStation, 'Origin'],
            [$tripSix, $latakiaStation, 'Destination'],
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

        $bookingThree = Booking::create([
            'user_id' => $extraCustomers[0]->id, 'driver_id' => $driverOne->id, 'trip_id' => $tripOne->id,
            'pickup_checkpoint_id' => $university->id, 'dropoff_checkpoint_id' => $newAleppo->id,
            'seats_count' => 2, 'status' => 'confirmed',
        ]);
        $bookingFour = Booking::create([
            'user_id' => $extraCustomers[1]->id, 'driver_id' => $driverTwo->id, 'trip_id' => $tripTwo->id,
            'pickup_checkpoint_id' => $aleppoCenter->id, 'dropoff_checkpoint_id' => $damascusStation->id,
            'seats_count' => 2, 'status' => 'confirmed',
        ]);
        $bookingFive = Booking::create([
            'user_id' => $extraCustomers[2]->id, 'driver_id' => $driverOne->id, 'trip_id' => $tripThree->id,
            'pickup_checkpoint_id' => $aleppoCenter->id, 'dropoff_checkpoint_id' => $tartusStation->id,
            'seats_count' => 3, 'status' => 'pending',
        ]);
        $bookingSix = Booking::create([
            'user_id' => $extraCustomers[3]->id, 'driver_id' => $driverOne->id, 'trip_id' => $tripThree->id,
            'pickup_checkpoint_id' => $latakiaStation->id, 'dropoff_checkpoint_id' => $tartusStation->id,
            'seats_count' => 2, 'status' => 'confirmed',
        ]);
        $bookingSeven = Booking::create([
            'user_id' => $customerOne->id, 'driver_id' => $driverTwo->id, 'trip_id' => $tripFour->id,
            'pickup_checkpoint_id' => $damascusStation->id, 'dropoff_checkpoint_id' => $homsStation->id,
            'seats_count' => 4, 'status' => 'booked', 'boarded_at' => now()->subDays(5),
        ]);
        $bookingEight = Booking::create([
            'user_id' => $customerTwo->id, 'driver_id' => $driverTwo->id, 'trip_id' => $tripFour->id,
            'pickup_checkpoint_id' => $damascusStation->id, 'dropoff_checkpoint_id' => $homsStation->id,
            'seats_count' => 2, 'status' => 'booked', 'boarded_at' => now()->subDays(5),
        ]);
        $bookingNine = Booking::create([
            'user_id' => $extraCustomers[0]->id, 'driver_id' => $driverOne->id, 'trip_id' => $tripFive->id,
            'pickup_checkpoint_id' => $aleppoCenter->id, 'dropoff_checkpoint_id' => $homsStation->id,
            'seats_count' => 3, 'status' => 'booked', 'boarded_at' => now()->subDays(20),
        ]);
        $bookingTen = Booking::create([
            'user_id' => $extraCustomers[1]->id, 'driver_id' => $driverOne->id, 'trip_id' => $tripFive->id,
            'pickup_checkpoint_id' => $hamaStation->id, 'dropoff_checkpoint_id' => $homsStation->id,
            'seats_count' => 3, 'status' => 'confirmed',
        ]);

        foreach ([$bookingOne, $bookingThree, $bookingTwo, $bookingFour, $bookingFive, $bookingSix, $bookingSeven, $bookingEight, $bookingNine, $bookingTen] as $booking) {
            Seat::where('trip_id', $booking->trip_id)
                ->whereNull('booking_id')
                ->orderBy('id')
                ->limit($booking->seats_count)
                ->update(['booking_id' => $booking->id]);
        }

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
            'admin_reply' => 'Thank you for your feedback. The delay was reviewed.',
            'replied_by' => $admin->id,
            'replied_at' => now()->subDay(),
        ]);

        Rating::create(['customer_id' => $extraCustomers[0]->id, 'trip_id' => $tripFour->id, 'rate' => 3, 'comment' => 'The bus was clean but crowded.']);
        Rating::create(['customer_id' => $extraCustomers[1]->id, 'trip_id' => $tripFive->id, 'rate' => 5, 'comment' => 'Excellent driver and timing.', 'admin_reply' => 'We are glad you enjoyed the trip.', 'replied_by' => $admin->id, 'replied_at' => now()]);

        Complaint::create([
            'customer_id' => $customerTwo->id,
            'comment' => 'The pickup location was not clear enough.',
            'status' => 'pending',
        ]);

        Complaint::create([
            'customer_id' => $customerThree->id,
            'comment' => 'I could not find available seats for my preferred time.',
            'status' => 'resolved',
            'admin_reply' => 'Additional trips have been scheduled.',
            'replied_by' => $admin->id,
            'replied_at' => now()->subDays(2),
        ]);

        Complaint::create(['customer_id' => $extraCustomers[2]->id, 'comment' => 'The trip departed ten minutes late.', 'status' => 'answered', 'admin_reply' => 'The operations team has been notified.', 'replied_by' => $admin->id, 'replied_at' => now()]);
        Complaint::create(['customer_id' => $extraCustomers[3]->id, 'comment' => 'Please add more evening trips.', 'status' => 'pending']);

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

        PrivateTripRequest::create([
            'user_id' => $extraCustomers[0]->id, 'from_location' => 'Homs', 'to_location' => 'Latakia',
            'status' => 'rejected', 'rejection_reason' => 'No driver is available on the requested date.',
            'rejected_by' => $admin->id, 'rejected_at' => now()->subDay(),
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

        WaitingList::create([
            'user_id' => $extraCustomers[2]->id, 'trip_id' => $tripThree->id,
            'pickup_checkpoint_id' => $aleppoCenter->id, 'dropoff_checkpoint_id' => $tartusStation->id,
            'seats_count' => 2, 'status' => 'pending',
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

        TripObjection::create(['driver_id' => $driverOne->id, 'trip_id' => $tripThree->id, 'reason' => 'Schedule overlaps with vehicle maintenance.', 'status' => 'pending']);
        TripObjection::create(['driver_id' => $driverTwo->id, 'trip_id' => $tripFour->id, 'reason' => 'Requested a thirty-minute departure adjustment.', 'status' => 'approved']);

        $logOne = DriverCheckpointLog::create([
            'driver_id' => $driverOne->id,
            'trip_id' => $tripOne->id,
            'checkpoint_id' => $aleppoCenter->id,
            'scanned_at' => now()->subHours(2),
        ]);

        $logTwo = DriverCheckpointLog::create([
            'driver_id' => $driverOne->id,
            'trip_id' => $tripOne->id,
            'checkpoint_id' => $university->id,
            'scanned_at' => now()->subHour(),
        ]);

        $logThree = DriverCheckpointLog::create([
            'driver_id' => $driverTwo->id,
            'trip_id' => $tripTwo->id,
            'checkpoint_id' => $aleppoCenter->id,
            'scanned_at' => now()->subMinutes(30),
        ]);

        $logFour = DriverCheckpointLog::create([
            'driver_id' => $driverTwo->id,
            'trip_id' => $tripFour->id,
            'checkpoint_id' => $homsStation->id,
            'scanned_at' => now()->subDays(5),
        ]);

        $scouringOne = Scouring::create([
            'driver_checkpoint_log_id' => $logOne->id,
            'customer_id' => $customerOne->id,
            'booking_id' => $bookingOne->id,
            'points' => 25,
        ]);

        $scouringTwo = Scouring::create([
            'driver_checkpoint_log_id' => $logThree->id,
            'customer_id' => $customerTwo->id,
            'booking_id' => $bookingTwo->id,
            'points' => 15,
        ]);

        $scouringThree = Scouring::create([
            'driver_checkpoint_log_id' => $logFour->id,
            'customer_id' => $customerOne->id,
            'booking_id' => $bookingSeven->id,
            'points' => 90,
        ]);

        foreach ([$scouringOne, $scouringTwo, $scouringThree] as $scouring) {
            PointAuditLog::create([
                'scouring_id' => $scouring->id,
                'actor_id' => $admin->id,
                'customer_id' => $scouring->customer_id,
                'booking_id' => $scouring->booking_id,
                'action' => 'granted',
                'points_before' => 0,
                'points_after' => $scouring->points,
                'points_delta' => $scouring->points,
                'ip_address' => '127.0.0.1',
                'context' => ['driver_checkpoint_log_id' => $scouring->driver_checkpoint_log_id, 'source' => 'demo-seeder'],
                'created_at' => $scouring->created_at,
                'updated_at' => $scouring->updated_at,
            ]);
        }

        DeviceToken::create(['user_id' => $customerOne->id, 'token' => 'demo-android-customer-1', 'platform' => 'android', 'device_name' => 'Samsung Galaxy', 'app_version' => '1.0.0', 'last_used_at' => now()]);
        DeviceToken::create(['user_id' => $customerTwo->id, 'token' => 'demo-ios-customer-2', 'platform' => 'ios', 'device_name' => 'iPhone', 'app_version' => '1.0.0', 'last_used_at' => now()->subHour()]);
        DeviceToken::create(['user_id' => $driverOne->id, 'token' => 'demo-android-driver-1', 'platform' => 'android', 'device_name' => 'Driver Phone', 'app_version' => '1.0.0', 'last_used_at' => now()->subMinutes(20)]);

        $customerOne->notify(new SystemEventNotification('booking_confirmed', 'Booking confirmed', "Your booking #{$bookingOne->id} has been confirmed.", ['booking_id' => $bookingOne->id, 'trip_id' => $tripOne->id]));
        $customerOne->notify(new SystemEventNotification('trip_cancelled', 'Trip cancelled', "Trip #{$tripSix->id} has been cancelled.", ['trip_id' => $tripSix->id]));
        $customerTwo->notify(new SystemEventNotification('rating_replied', 'Rating reply', 'The administration has replied to your trip rating.', ['rating_id' => 2, 'trip_id' => $tripTwo->id]));
        $customerThree->notify(new SystemEventNotification('complaint_replied', 'Complaint reply', 'The administration has replied to your complaint.', ['complaint_id' => 2]));

        $customerOne->notifications()->first()?->markAsRead();
    }
}
