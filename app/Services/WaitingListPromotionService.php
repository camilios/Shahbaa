<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Trip;

class WaitingListPromotionService
{
    private const MINIMUM_WAITING_SEATS = 30;

    public function promoteWhenReady(Trip $trip): ?Trip
    {
        if ($trip->available_seats > 0) {
            return null;
        }

        $waitingCustomers = $trip->waitingList()
            ->whereIn('status', ['pending', 'waiting'])
            ->whereNotNull('pickup_checkpoint_id')
            ->whereNotNull('dropoff_checkpoint_id')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ($waitingCustomers->sum('seats_count') < self::MINIMUM_WAITING_SEATS) {
            return null;
        }

        // Keep the waiting-list order and move only the requests that can be
        // fully accommodated by a trip with the same capacity.
        $selectedCustomers = collect();
        $requiredSeats = 0;
        foreach ($waitingCustomers as $waitingCustomer) {
            if ($requiredSeats + $waitingCustomer->seats_count > $trip->total_seats) {
                break;
            }

            $selectedCustomers->push($waitingCustomer);
            $requiredSeats += $waitingCustomer->seats_count;
        }

        if ($selectedCustomers->isEmpty()) {
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
        foreach ($selectedCustomers as $waitingCustomer) {
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
        $trip->waitingList()->whereKey($selectedCustomers->pluck('id'))->delete();

        return $newTrip->fresh();
    }
}
