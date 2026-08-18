<?php

namespace App\Http\Controllers;

use App\Http\Requests\WaitingListRequest;
use App\Models\Booking;
use Illuminate\Support\Facades\Validator;
use App\Models\WaitingList;
use App\Models\Trip;
use App\Services\WaitingListPromotionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WaitingListController extends Controller
{
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

   public function insert_to_waitingList(Request $request)
{
    $validator = Validator::make($request->all(), [
        'trip_id' => 'required|integer|exists:trips,id',
        'pickup_checkpoint_id' => 'required|integer|exists:checkpoints,id',
        'dropoff_checkpoint_id' => 'required|integer|exists:checkpoints,id',
        'seats_count' => 'required|integer|min:1|max:50',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'message' => 'Validation error.',
            'errors' => $validator->errors(),
        ], 422);
    }

    $user = auth('sanctum')->user();

    if (!$user) {
        return response()->json([
            'message' => 'Unauthenticated.',
        ], 401);
    }

   
    $alreadyWaiting = WaitingList::where('user_id', $user->id)
        ->where('trip_id', $request->trip_id)
        ->exists();

    if ($alreadyWaiting) {
        return response()->json([
            'message' => 'You are already in the waiting list for this trip.',
        ], 409);
    }

    $waitingList = WaitingList::create([
        'user_id' => $user->id,
        'trip_id' => $request->trip_id,
        'pickup_checkpoint_id' => $request->pickup_checkpoint_id,
        'dropoff_checkpoint_id' => $request->dropoff_checkpoint_id,
        'seats_count' => $request->seats_count,
    ]);

    return response()->json([
        'message' => 'You have been added to the waiting list.',
        'data' => $waitingList,
        'status' => 201,
    ], 201);
}


    public function trip_wating_book(Request $request)
    {
        WaitingList::where('trip_id', $request->id)->delete();

           return response()->json([
            'message' => 'You have been removed from the waiting list.',
            'status' => 200,]);
    }







}
