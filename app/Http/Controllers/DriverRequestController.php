<?php

namespace App\Http\Controllers;

use App\Models\DriverRequest;
use Illuminate\Http\Request;

class DriverRequestController extends Controller
{
    public function index()
    {
        return DriverRequest::with(['driver', 'trip'])->paginate(20);
    }

    public function show(DriverRequest $driverRequest)
    {
        return $driverRequest->load(['driver', 'trip']);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'driver_id' => 'required|exists:users,id',
            'trip_id' => 'required|exists:trips,id',
            'notes' => 'nullable|string',
            'status' => 'nullable|string|max:100',
        ]);

        return DriverRequest::create($data);
    }

    public function update(Request $request, DriverRequest $driverRequest)
    {
        $data = $request->validate([
            'driver_id' => 'sometimes|required|exists:users,id',
            'trip_id' => 'sometimes|required|exists:trips,id',
            'notes' => 'nullable|string',
            'status' => 'nullable|string|max:100',
        ]);

        $driverRequest->update($data);

        return $driverRequest;
    }

    public function destroy(DriverRequest $driverRequest)
    {
        $driverRequest->delete();

        return response()->noContent();
    }
}
