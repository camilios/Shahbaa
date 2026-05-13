<?php

namespace App\Http\Controllers;

use App\Http\Requests\DriverCheckpointLogRequest;
use App\Models\DriverCheckpointLog;

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

    public function store(DriverCheckpointLogRequest $request)
    {
        return DriverCheckpointLog::create($request->validated());
    }

    public function update(DriverCheckpointLogRequest $request, DriverCheckpointLog $driverCheckpointLog)
    {
        $driverCheckpointLog->update($request->validated());

        return $driverCheckpointLog;
    }

    public function destroy(DriverCheckpointLog $driverCheckpointLog)
    {
        $driverCheckpointLog->delete();

        return response()->noContent();
    }
}
