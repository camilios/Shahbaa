<?php

namespace App\Http\Controllers;

use App\Http\Requests\RealtimeTrackingAuthorizationRequest;
use App\Models\Trip;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class RealtimeTrackingAuthorizationController extends Controller
{
    public function __invoke(RealtimeTrackingAuthorizationRequest $request): JsonResponse
    {
        $user = $request->user();
        $trip = Trip::query()->findOrFail($request->integer('trip_id'));
        $action = $request->string('action')->toString();

        if ($user->status !== 'active' || ! in_array($trip->status, config('tracking.trackable_trip_statuses'), true)) {
            return $this->denied();
        }

        $authorized = match ($action) {
            'publish' => $user->isDriver() && $trip->driver_id === $user->id,
            'subscribe' => $user->isAdmin(),
            default => false,
        };

        if (! $authorized) {
            return $this->denied();
        }

        return response()->json([
            'authorized' => true,
            'user_id' => $user->id,
            'role' => strtolower((string) $user->role),
            'trip_id' => $trip->id,
            'action' => $action,
        ]);
    }

    private function denied(): JsonResponse
    {
        return response()->json([
            'message' => 'You are not authorized to track this trip.',
        ], Response::HTTP_FORBIDDEN);
    }
}
