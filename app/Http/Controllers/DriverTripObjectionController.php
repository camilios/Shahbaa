<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesTripAccess;
use App\Models\Trip;
use App\Models\TripObjection;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DriverTripObjectionController extends Controller
{
    use AuthorizesTripAccess;

    /**
     * List the objections the driver has filed against this trip.
     */
    public function index(Request $request, Trip $trip)
    {
        if ($denied = $this->denyIfNotTripOwner($request, $trip)) {
            return $denied;
        }

        $objections = $trip->objections()
            ->where('driver_id', $request->user()->id)
            ->latest()
            ->get();

        return response()->json(['objections' => $objections]);
    }

    /**
     * File an objection against the trip's schedule with a text reason.
     */
    public function store(Request $request, Trip $trip)
    {
        if ($denied = $this->denyIfNotTripOwner($request, $trip)) {
            return $denied;
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ]);

        $objection = TripObjection::create([
            'driver_id' => $request->user()->id,
            'trip_id' => $trip->id,
            'reason' => $validated['reason'],
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Objection submitted.',
            'objection' => $objection,
        ], Response::HTTP_CREATED);
    }
}
