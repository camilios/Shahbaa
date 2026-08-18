<?php

namespace App\Http\Controllers;

use App\Http\Requests\WaitingListRequest;
use App\Models\WaitingList;
use App\Models\Trip;
use App\Services\WaitingListPromotionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WaitingListController extends Controller
{
    public function storeForCurrentUser(
        Request $request,
        WaitingListPromotionService $promotionService
    ) {
        $data = $request->validate([
            'trip_id' => ['required', 'integer', 'exists:trips,id'],
            'pickup_checkpoint_id' => ['required', 'integer', 'exists:checkpoints,id'],
            'dropoff_checkpoint_id' => ['required', 'integer', 'exists:checkpoints,id'],
            'seats_count' => ['required', 'integer', 'min:1', 'max:50'],
        ]);

        return DB::transaction(function () use ($request, $data, $promotionService) {
            $trip = Trip::query()->lockForUpdate()->findOrFail($data['trip_id']);

            if ($trip->available_seats > 0) {
                throw ValidationException::withMessages([
                    'trip_id' => ['The waiting list is available only when the trip is full.'],
                ]);
            }

            $checkpointIds = $trip->checkpoints()->pluck('checkpoint_id');
            if (
                ! $checkpointIds->contains($data['pickup_checkpoint_id'])
                || ! $checkpointIds->contains($data['dropoff_checkpoint_id'])
            ) {
                throw ValidationException::withMessages([
                    'pickup_checkpoint_id' => ['Pickup and dropoff checkpoints must belong to the trip.'],
                ]);
            }

            $waitingList = WaitingList::firstOrCreate(
                [
                    'user_id' => $request->user()->id,
                    'trip_id' => $trip->id,
                ],
                [
                    'pickup_checkpoint_id' => $data['pickup_checkpoint_id'],
                    'dropoff_checkpoint_id' => $data['dropoff_checkpoint_id'],
                    'seats_count' => $data['seats_count'],
                    'status' => 'pending',
                ],
            );

            $newTrip = $promotionService->promoteWhenReady($trip);

            return response()->json([
                'waiting_list' => $newTrip ? null : $waitingList,
                'new_trip' => $newTrip,
            ], 201);
        });
    }

    public function leaveCurrentUser(Request $request, Trip $trip)
    {
        $deleted = WaitingList::query()
            ->where('trip_id', $trip->id)
            ->where('user_id', $request->user()->id)
            ->delete();

        if ($deleted === 0) {
            abort(404, 'No waiting-list entry was found for this user and trip.');
        }

        return response()->noContent();
    }

    public function leaveCurrentUserLegacy(Request $request)
    {
        $data = $request->validate([
            'id' => ['required', 'integer', 'exists:trips,id'],
        ]);

        return $this->leaveCurrentUser($request, Trip::findOrFail($data['id']));
    }

    public function index()
    {
        return WaitingList::with(['user', 'trip', 'pickupCheckpoint', 'dropoffCheckpoint'])->paginate(20);
    }

    public function show(WaitingList $waitingList)
    {
        return $waitingList->load(['user', 'trip', 'pickupCheckpoint', 'dropoffCheckpoint']);
    }

    public function store(WaitingListRequest $request, WaitingListPromotionService $promotionService)
    {
        return DB::transaction(function () use ($request, $promotionService) {
            $data = $request->validated();
            $trip = Trip::query()->lockForUpdate()->findOrFail($data['trip_id']);
            $checkpointIds = $trip->checkpoints()->orderBy('id')->pluck('checkpoint_id');

            if ($checkpointIds->count() < 2) {
                throw ValidationException::withMessages([
                    'trip_id' => ['The trip must have at least two checkpoints.'],
                ]);
            }

            $pickupCheckpointId = $data['pickup_checkpoint_id'] ?? $checkpointIds->first();
            $dropoffCheckpointId = $data['dropoff_checkpoint_id'] ?? $checkpointIds->last();
            if (! $checkpointIds->contains($pickupCheckpointId) || ! $checkpointIds->contains($dropoffCheckpointId)) {
                throw ValidationException::withMessages([
                    'pickup_checkpoint_id' => ['Pickup and dropoff checkpoints must belong to the trip.'],
                ]);
            }

            $waitingList = WaitingList::firstOrCreate(
                ['user_id' => $data['user_id'], 'trip_id' => $trip->id],
                [
                    'pickup_checkpoint_id' => $pickupCheckpointId,
                    'dropoff_checkpoint_id' => $dropoffCheckpointId,
                    'seats_count' => $data['seats_count'] ?? 1,
                    'status' => $data['status'] ?? 'pending',
                ],
            );

            $newTrip = $promotionService->promoteWhenReady($trip);

            return response()->json([
                'message' => $newTrip
                    ? 'A new trip has been created and the waiting customers have been booked on it.'
                    : 'The customer has been added to the waiting list.',
                'waiting_list' => $newTrip ? null : $waitingList->load(['user', 'trip', 'pickupCheckpoint', 'dropoffCheckpoint']),
                'new_trip' => $newTrip?->load(['driver', 'seats', 'checkpoints.checkpoint', 'bookings.user']),
            ], 201);
        });
    }

    public function update(WaitingListRequest $request, WaitingList $waitingList)
    {
        $waitingList->update($request->validated());

        return $waitingList;
    }

    public function destroy(WaitingList $waitingList)
    {
        $waitingList->delete();

        return response()->noContent();
    }
}
