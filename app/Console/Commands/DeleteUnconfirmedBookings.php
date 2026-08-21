<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\Trip;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DeleteUnconfirmedBookings extends Command
{
    protected $signature = 'bookings:delete-unconfirmed';

    protected $description = 'Delete unconfirmed bookings one hour before trip departure';

    public function handle(): int
    {
        $deleted = 0;

        Booking::query()
            ->where('status', 'pending')
            ->whereHas('trip', fn ($query) => $query
                ->whereNotNull('departure_date')
                ->where('departure_date', '<=', now()->addHour()))
            ->select('id')
            ->orderBy('id')
            ->chunkById(100, function ($bookings) use (&$deleted): void {
                foreach ($bookings as $booking) {
                    $wasDeleted = DB::transaction(function () use ($booking): bool {
                        $lockedBooking = Booking::query()
                            ->lockForUpdate()
                            ->find($booking->id);

                        if (! $lockedBooking || $lockedBooking->status !== 'pending') {
                            return false;
                        }

                        $trip = Trip::query()
                            ->lockForUpdate()
                            ->find($lockedBooking->trip_id);

                        if (! $trip?->departure_date || $trip->departure_date->gt(now()->addHour())) {
                            return false;
                        }

                        $releasedSeats = $lockedBooking->seats()->count();
                        $lockedBooking->seats()->update(['booking_id' => null]);

                        if ($releasedSeats > 0) {
                            $trip->increment('available_seats', $releasedSeats);
                        }

                        $lockedBooking->delete();

                        return true;
                    });

                    if ($wasDeleted) {
                        $deleted++;
                    }
                }
            });

        $this->info("Deleted {$deleted} unconfirmed booking(s).");

        return self::SUCCESS;
    }
}
