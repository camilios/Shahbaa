<?php

namespace App\Observers;

use App\Models\Booking;
use App\Notifications\SystemEventNotification;
use App\Services\PointWalletService;

class BookingNotificationObserver
{
    public function created(Booking $booking): void
    {
        if ($booking->status === 'confirmed') {
            app(PointWalletService::class)->creditForConfirmedBooking($booking);
        }
    }

    public function updated(Booking $booking): void
    {
        if (! $booking->wasChanged('status') || $booking->status !== 'confirmed') {
            return;
        }

        app(PointWalletService::class)->creditForConfirmedBooking($booking);

        $booking->user?->notify(new SystemEventNotification(
            'booking_confirmed',
            'Booking confirmed',
            "Your booking #{$booking->id} has been confirmed.",
            ['booking_id' => $booking->id, 'trip_id' => $booking->trip_id]
        ));
    }
}
