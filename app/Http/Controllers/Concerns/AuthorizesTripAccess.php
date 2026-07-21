<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Trip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

trait AuthorizesTripAccess
{
    /**
     * Return a 403 response if the trip does not belong to the
     * authenticated driver, otherwise null. Drivers may only act on
     * their own trips.
     */
    protected function denyIfNotTripOwner(Request $request, Trip $trip): ?JsonResponse
    {
        if ($trip->driver_id !== $request->user()->id) {
            return response()->json([
                'message' => 'This trip does not belong to you.',
            ], Response::HTTP_FORBIDDEN);
        }

        return null;
    }
}
