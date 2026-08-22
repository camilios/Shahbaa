<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckpointRequest;
use App\Models\Checkpoint;
use App\Models\Governorate;

class CheckpointController extends Controller
{
    public function index()
    {
        return Checkpoint::with(['governorateRelation', 'tripCheckpoints', 'pickupBookings', 'dropoffBookings'])->paginate(20);
    }

    public function show(Checkpoint $checkpoint)
    {
        return $checkpoint->load(['governorateRelation', 'tripCheckpoints', 'pickupBookings', 'dropoffBookings', 'driverCheckpointLogs']);
    }

    public function store(CheckpointRequest $request)
    {
        $data = $request->validated();
        $data['governorate'] = Governorate::findOrFail($data['governorate_id'])->name;

        return Checkpoint::create($data)->load('governorateRelation');
    }

    public function update(CheckpointRequest $request, Checkpoint $checkpoint)
    {
        $data = $request->validated();
        if (isset($data['governorate_id'])) {
            $data['governorate'] = Governorate::findOrFail($data['governorate_id'])->name;
        }
        $checkpoint->update($data);

        return $checkpoint->load('governorateRelation');
    }

    public function destroy(Checkpoint $checkpoint)
    {
        $checkpoint->delete();

        return response()->noContent();
    }
}
