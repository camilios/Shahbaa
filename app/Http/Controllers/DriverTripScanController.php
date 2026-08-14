<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesTripAccess;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DriverTripScanController extends Controller
{
    use AuthorizesTripAccess;

    /**
     * Scan a passenger's digital ticket (QR) at boarding.
     *
     * The QR on the passenger's phone carries their qr_token. Scanning
     * verifies the passenger is actually booked on this trip and, if so,
     * marks their booking as boarded so they appear in the boarded list.
     */
    public function store(Request $request, Trip $trip)
    {
        if ($denied = $this->denyIfNotTripOwner($request, $trip)) {
            return $denied;
        }

        $validated = $request->validate([
            'qr_token' => ['required', 'string'],
        ]);

        $passenger = User::where('qr_token', $validated['qr_token'])->first();

        if (! $passenger) {
            return response()->json([
                'message' => 'Invalid ticket: no passenger matches this QR code.',
            ], Response::HTTP_NOT_FOUND);
        }

        $booking = $trip->bookings()
            ->where('user_id', $passenger->id)
            ->where('status', '!=', 'cancelled')
            ->first();

        if (! $booking) {
            return response()->json([
                'message' => 'This passenger is not booked on this trip.',
                'passenger' => [
                    'id' => $passenger->id,
                    'name' => $passenger->name,
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $alreadyBoarded = $booking->boarded_at !== null;

        if (! $alreadyBoarded) {
            $booking->update([
                'boarded_at' => now(),
                'status' => 'booked',
            ]);
        }

        return response()->json([
            'message' => $alreadyBoarded
                ? 'Passenger already boarded.'
                : 'Passenger verified and boarded.',
            'passenger' => [
                'booking_id' => $booking->id,
                'id' => $passenger->id,
                'name' => $passenger->name,
                'phone' => $passenger->phone,
                'seats_count' => $booking->seats_count,
                'status' => $booking->status,
                'boarded_at' => $booking->boarded_at,
            ],
        ]);
    }
}
