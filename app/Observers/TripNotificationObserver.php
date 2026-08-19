<?php

namespace App\Observers;

use App\Models\Trip;
use App\Notifications\SystemEventNotification;

class TripNotificationObserver
{
    public function updated(Trip $trip): void
    {
        if (! $trip->wasChanged('status') || ! in_array($trip->status, ['cancelled', 'canceled'], true)) {
            return;
        }

        $this->notifyPassengers($trip);
    }

    public function deleting(Trip $trip): void
    {
        if (! in_array($trip->status, ['cancelled', 'canceled'], true)) {
            $this->notifyPassengers($trip);
        }
    }

    private function notifyPassengers(Trip $trip): void
    {
        $trip->bookings()
            ->where('status', '!=', 'cancelled')
            ->with('user')
            ->get()
            ->pluck('user')
            ->filter()
            ->unique('id')
            ->each(fn ($user) => $user->notify(new SystemEventNotification(
                'trip_cancelled',
                'Trip cancelled',
                "Trip #{$trip->id} has been cancelled.",
                ['trip_id' => $trip->id]
            )));
    }
}
