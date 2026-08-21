<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminBookingConfirmationController extends Controller
{
    public function __invoke(Request $request, Booking $booking): JsonResponse
    {
        $request->validate([
            'payment_verified' => ['required', 'accepted'],
        ]);

        return DB::transaction(function () use ($booking) {
            $lockedBooking = Booking::query()
                ->with('trip')
                ->lockForUpdate()
                ->findOrFail($booking->id);

            if ($lockedBooking->status !== 'pending') {
                throw ValidationException::withMessages([
                    'status' => ['Only pending bookings can be confirmed.'],
                ]);
            }

            $departure = $lockedBooking->trip?->departure_date;
            if (! $departure || $departure->lte(now()->addHour())) {
                throw ValidationException::withMessages([
                    'trip' => ['The booking must be confirmed at least one hour before departure.'],
                ]);
            }

            if (in_array($lockedBooking->trip->status, ['cancelled', 'completed'], true)) {
                throw ValidationException::withMessages([
                    'trip' => ['Bookings cannot be confirmed for a cancelled or completed trip.'],
                ]);
            }

            $lockedBooking->update([
                'status' => 'confirmed',
                'payment_method' => $lockedBooking->payment_method ?? 'cash',
                'payment_status' => 'paid',
                'paid_amount' => $lockedBooking->paid_amount > 0
                    ? $lockedBooking->paid_amount
                    : (float) $lockedBooking->trip->money_price * $lockedBooking->seats_count,
                'paid_at' => $lockedBooking->paid_at ?? now(),
                'payment_reference' => $lockedBooking->payment_reference
                    ?? 'CASH-'.$lockedBooking->id.'-'.now()->format('YmdHis'),
            ]);

            return response()->json([
                'message' => 'Booking confirmed after payment verification.',
                'booking' => $lockedBooking->fresh()->load([
                    'user',
                    'driver',
                    'trip',
                    'pickupCheckpoint',
                    'dropoffCheckpoint',
                    'seats',
                ]),
            ]);
        });
    }
}
