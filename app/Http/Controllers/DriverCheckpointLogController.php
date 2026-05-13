<?php

namespace App\Http\Controllers;

use App\Models\DriverCheckpointLog;
use Illuminate\Http\Request;

class DriverCheckpointLogController extends Controller
{
    public function index()
    {
        return DriverCheckpointLog::with(['driver', 'trip', 'checkpoint'])->paginate(20);
    }

    public function show(DriverCheckpointLog $driverCheckpointLog)
    {
        return $driverCheckpointLog->load(['driver', 'trip', 'checkpoint', 'scouring']);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'driver_id' => 'required|exists:users,id',
            'trip_id' => 'required|exists:trips,id',
            'checkpoint_id' => 'required|exists:checkpoints,id',
            'scanned_at' => 'nullable|date',
        ]);

        return DriverCheckpointLog::create($data);
    }

    public function update(Request $request, DriverCheckpointLog $driverCheckpointLog)
    {
        $data = $request->validate([
            'driver_id' => 'sometimes|required|exists:users,id',
            'trip_id' => 'sometimes|required|exists:trips,id',
            'checkpoint_id' => 'sometimes|required|exists:checkpoints,id',
            'scanned_at' => 'nullable|date',
        ]);

        $driverCheckpointLog->update($data);

        return $driverCheckpointLog;
    }

    public function destroy(DriverCheckpointLog $driverCheckpointLog)
    {
        $driverCheckpointLog->delete();

        return response()->noContent();
    }
}
