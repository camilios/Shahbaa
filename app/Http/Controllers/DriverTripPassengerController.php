<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesTripAccess;
use App\Models\Trip;
use Illuminate\Http\Request;

class DriverTripPassengerController extends Controller
{
    use AuthorizesTripAccess;

    /**
     * List the passengers booked on the driver's trip, so the driver can
     * see the names of who is riding with them.
     */
    public function index(Request $request, Trip $trip)
    {
        if ($denied = $this->denyIfNotTripOwner($request, $trip)) {
            return $denied;
        }

        $bookings = $trip->bookings()
            ->where('status', '!=', 'cancelled')
            ->with([
                'user:id,full_name,phone',
                'pickupCheckpoint:id,name',
                'dropoffCheckpoint:id,name',
            ])
            ->get();

        $passengers = $bookings->map(fn ($booking) => [
            'booking_id' => $booking->id,
            'user_id' => $booking->user_id,
            'name' => $booking->user?->full_name,
            'phone' => $booking->user?->phone,
            'seats_count' => $booking->seats_count,
            'status' => $booking->status,
            'boarded' => $booking->boarded_at !== null,
            'boarded_at' => $booking->boarded_at,
            'pickup' => $booking->pickupCheckpoint?->name,
            'dropoff' => $booking->dropoffCheckpoint?->name,
        ]);

        return response()->json([
            'trip_id' => $trip->id,
            'passengers_count' => $passengers->count(),
            'seats_booked' => (int) $bookings->sum('seats_count'),
            'passengers' => $passengers,
        ]);
    }
}
