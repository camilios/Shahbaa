<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesTripAccess;
use App\Models\DriverCheckpointLog;
use App\Models\Trip;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DriverTripCheckpointController extends Controller
{
    use AuthorizesTripAccess;

    /**
     * List the checkpoints the driver has already logged for this trip,
     * in the order they were reached (trip progress / route tracking).
     */
    public function index(Request $request, Trip $trip)
    {
        if ($denied = $this->denyIfNotTripOwner($request, $trip)) {
            return $denied;
        }

        $logs = $trip->driverCheckpointLogs()
            ->with('checkpoint')
            ->orderBy('scanned_at')
            ->get();

        return response()->json(['logs' => $logs]);
    }

    /**
     * Record that the driver has reached one of the trip's route
     * checkpoints. Admins can then follow the trip's progress through
     * these logs.
     */
    public function store(Request $request, Trip $trip)
    {
        if ($denied = $this->denyIfNotTripOwner($request, $trip)) {
            return $denied;
        }

        $validated = $request->validate([
            'checkpoint_id' => ['required', 'integer', 'exists:checkpoints,id'],
        ]);

        // A driver may only check in at checkpoints that belong to this
        // trip's predefined route, not arbitrary locations.
        $isOnRoute = $trip->checkpoints()
            ->where('checkpoint_id', $validated['checkpoint_id'])
            ->exists();

        if (! $isOnRoute) {
            return response()->json([
                'message' => 'This checkpoint is not part of the trip route.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Idempotent: one log per checkpoint per trip.
        $log = DriverCheckpointLog::firstOrCreate(
            [
                'trip_id' => $trip->id,
                'checkpoint_id' => $validated['checkpoint_id'],
            ],
            [
                'driver_id' => $request->user()->id,
                'scanned_at' => now(),
            ]
        );

        return response()->json([
            'message' => $log->wasRecentlyCreated
                ? 'Checkpoint recorded.'
                : 'Checkpoint already recorded.',
            'log' => $log->load('checkpoint'),
        ], $log->wasRecentlyCreated ? Response::HTTP_CREATED : Response::HTTP_OK);
    }
}
