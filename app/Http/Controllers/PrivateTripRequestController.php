<?php

namespace App\Http\Controllers;

use App\Http\Requests\ApprovePrivateTripRequest;
use App\Http\Requests\PrivateTripRequestRequest;
use App\Http\Requests\RejectPrivateTripRequest;
use App\Models\PrivateTripRequest;
use App\Models\Trip;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PrivateTripRequestController extends Controller
{
    public function index()
    {
        return PrivateTripRequest::with(['user', 'rejectedBy'])->paginate(20);
    }

    public function show(PrivateTripRequest $privateTripRequest)
    {
        return $privateTripRequest->load(['user', 'rejectedBy']);
    }

    public function store(PrivateTripRequestRequest $request)
    {
        return PrivateTripRequest::create($request->validated());
    }

    public function update(PrivateTripRequestRequest $request, PrivateTripRequest $privateTripRequest)
    {
        $privateTripRequest->update($request->validated());

        return $privateTripRequest;
    }

    public function destroy(PrivateTripRequest $privateTripRequest)
    {
        $privateTripRequest->delete();

        return response()->noContent();
    }

    public function approve(ApprovePrivateTripRequest $request, PrivateTripRequest $privateTripRequest)
    {
        if ($privateTripRequest->status !== 'pending') {
            throw ValidationException::withMessages(['status' => ['Only pending private trip requests can be approved.']]);
        }

        return DB::transaction(function () use ($request, $privateTripRequest) {
            $data = $request->validated();
            $checkpointIds = $data['checkpoint_ids'];
            unset($data['checkpoint_ids']);
            $trip = Trip::create([
                ...$data,
                'type' => 'private',
                'status' => 'scheduled',
                'available_seats' => $data['total_seats'],
            ]);

            foreach ($checkpointIds as $index => $checkpointId) {
                $trip->checkpoints()->create([
                    'checkpoint_id' => $checkpointId,
                    'description' => 'Stop '.($index + 1),
                ]);
            }
            for ($number = 1; $number <= $trip->total_seats; $number++) {
                $trip->seats()->create(['seat_number' => 'S'.str_pad((string) $number, 2, '0', STR_PAD_LEFT)]);
            }
            $privateTripRequest->update(['status' => 'approved']);

            return response()->json([
                'request' => $privateTripRequest->fresh(),
                'trip' => $trip->load(['driver', 'checkpoints.checkpoint', 'seats']),
            ], 201);
        });
    }

    public function reject(RejectPrivateTripRequest $request, PrivateTripRequest $privateTripRequest)
    {
        if ($privateTripRequest->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => ['Only pending private trip requests can be rejected.'],
            ]);
        }

        $privateTripRequest->update([
            'status' => 'rejected',
            'rejection_reason' => $request->validated('reason'),
            'rejected_by' => $request->user()->id,
            'rejected_at' => now(),
        ]);

        return $privateTripRequest->load(['user', 'rejectedBy']);
    }
}
