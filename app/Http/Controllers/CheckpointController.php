<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckpointRequest;
use App\Models\Checkpoint;

class CheckpointController extends Controller
{
    public function index()
    {
        return Checkpoint::with(['tripCheckpoints', 'pickupBookings', 'dropoffBookings'])->paginate(20);
    }

    public function show(Checkpoint $checkpoint)
    {
        return $checkpoint->load(['tripCheckpoints', 'pickupBookings', 'dropoffBookings', 'driverCheckpointLogs']);
    }

    public function store(CheckpointRequest $request)
    {
        return Checkpoint::create($request->validated());
    }

    public function update(CheckpointRequest $request, Checkpoint $checkpoint)
    {
        $checkpoint->update($request->validated());

        return $checkpoint;
    }

    public function destroy(Checkpoint $checkpoint)
    {
        $checkpoint->delete();

        return response()->noContent();
    }
}
