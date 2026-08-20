<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Services\PointWalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CustomerPointsPaymentController extends Controller
{
    public function __invoke(Request $request, Booking $booking, PointWalletService $walletService)
    {
        abort_unless(strtolower((string) $request->user()->role) === 'customer', 403);
        abort_unless($booking->user_id === $request->user()->id, 404);

        return DB::transaction(function () use ($request, $booking, $walletService) {
            $lockedBooking = Booking::with('trip')->lockForUpdate()->findOrFail($booking->id);

            if ($lockedBooking->payment_status === 'paid' && $lockedBooking->payment_method === 'points') {
                return response()->json(['message' => 'Booking was already paid with points.', 'booking' => $lockedBooking, 'wallet' => $walletService->wallet($request->user())]);
            }
            if ($lockedBooking->status !== 'pending' || $lockedBooking->payment_status !== 'unpaid') {
                throw ValidationException::withMessages(['booking' => ['Only pending unpaid bookings can be paid with points.']]);
            }
            if (! $lockedBooking->trip?->departure_date || $lockedBooking->trip->departure_date->lte(now()->addHour())) {
                throw ValidationException::withMessages(['trip' => ['Payment must be completed at least one hour before departure.']]);
            }
            if (in_array($lockedBooking->trip->status, ['cancelled', 'canceled', 'completed'], true)) {
                throw ValidationException::withMessages(['trip' => ['This trip is not available for payment.']]);
            }

            $points = (int) ceil((float) $lockedBooking->trip->point_price * $lockedBooking->seats_count);
            if ($points <= 0) {
                throw ValidationException::withMessages(['points' => ['This trip does not have a valid points price.']]);
            }

            $reference = 'PTS-'.Str::uuid();
            $transaction = $walletService->debitForBooking($request->user(), $points, $lockedBooking->id, $reference);
            $lockedBooking->update([
                'status' => 'confirmed',
                'payment_method' => 'points',
                'payment_status' => 'paid',
                'paid_amount' => $points,
                'paid_at' => now(),
                'payment_reference' => $reference,
            ]);

            return response()->json([
                'message' => 'Booking paid successfully with points.',
                'points_spent' => $points,
                'booking' => $lockedBooking->fresh()->load(['trip', 'seats']),
                'wallet' => $walletService->wallet($request->user()),
                'transaction' => $transaction,
            ]);
        });
    }
}
