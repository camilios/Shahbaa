<?php

namespace App\Observers;

use App\Models\Booking;
use App\Notifications\SystemEventNotification;

class BookingNotificationObserver
{
    public function updated(Booking $booking): void
    {
        if (! $booking->wasChanged('status') || $booking->status !== 'confirmed') {
            return;
        }

        $booking->user?->notify(new SystemEventNotification(
            'booking_confirmed',
            'Booking confirmed',
            "Your booking #{$booking->id} has been confirmed.",
            ['booking_id' => $booking->id, 'trip_id' => $booking->trip_id]
        ));
    }
}
