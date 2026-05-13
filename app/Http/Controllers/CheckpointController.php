<?php

namespace App\Http\Controllers;

use App\Models\Checkpoint;
use Illuminate\Http\Request;

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

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'location' => 'nullable|string|max:255',
            'governorate' => 'nullable|string|max:255',
        ]);

        return Checkpoint::create($data);
    }

    public function update(Request $request, Checkpoint $checkpoint)
    {
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'location' => 'nullable|string|max:255',
            'governorate' => 'nullable|string|max:255',
        ]);

        $checkpoint->update($data);

        return $checkpoint;
    }

    public function destroy(Checkpoint $checkpoint)
    {
        $checkpoint->delete();

        return response()->noContent();
    }
}
