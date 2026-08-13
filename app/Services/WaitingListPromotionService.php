<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Trip;

class WaitingListPromotionService
{
    public function promoteWhenReady(Trip $trip): ?Trip
    {
        $waitingCustomers = $trip->waitingList()
            ->whereIn('status', ['pending', 'waiting'])
            ->whereNotNull('pickup_checkpoint_id')
            ->whereNotNull('dropoff_checkpoint_id')
            ->orderBy('id')
            ->limit(30)
            ->lockForUpdate()
            ->get();

        if ($waitingCustomers->count() < 30) {
            return null;
        }

        $requiredSeats = $waitingCustomers->sum('seats_count');
        if ($requiredSeats > $trip->total_seats) {
            return null;
        }

        $newTrip = $trip->replicate();
        $newTrip->available_seats = $trip->total_seats;
        $newTrip->save();

        foreach ($trip->checkpoints()->orderBy('id')->get() as $checkpoint) {
            $newTrip->checkpoints()->create([
                'checkpoint_id' => $checkpoint->checkpoint_id,
                'description' => $checkpoint->description,
            ]);
        }

        for ($number = 1; $number <= $newTrip->total_seats; $number++) {
            $newTrip->seats()->create([
                'seat_number' => 'S'.str_pad((string) $number, 2, '0', STR_PAD_LEFT),
            ]);
        }

        $availableSeats = $newTrip->seats()->orderBy('seat_number')->get();
        $seatOffset = 0;
        foreach ($waitingCustomers as $waitingCustomer) {
            $booking = Booking::create([
                'user_id' => $waitingCustomer->user_id,
                'driver_id' => $newTrip->driver_id,
                'trip_id' => $newTrip->id,
                'pickup_checkpoint_id' => $waitingCustomer->pickup_checkpoint_id,
                'dropoff_checkpoint_id' => $waitingCustomer->dropoff_checkpoint_id,
                'seats_count' => $waitingCustomer->seats_count,
                'status' => 'pending',
            ]);

            $bookingSeats = $availableSeats->slice($seatOffset, $waitingCustomer->seats_count);
            $newTrip->seats()->whereKey($bookingSeats->modelKeys())->update(['booking_id' => $booking->id]);
            $seatOffset += $waitingCustomer->seats_count;
        }

        $newTrip->update(['available_seats' => $newTrip->total_seats - $requiredSeats]);
        $trip->waitingList()->whereKey($waitingCustomers->modelKeys())->delete();

        return $newTrip->fresh();
    }
}
