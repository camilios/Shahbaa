<?php

namespace App\Http\Controllers;

use App\Models\Scouring;
use Illuminate\Http\Request;

class ScouringController extends Controller
{
    public function index()
    {
        return Scouring::with(['driverCheckpointLog', 'customer', 'booking'])->paginate(20);
    }

    public function show(Scouring $scouring)
    {
        return $scouring->load(['driverCheckpointLog', 'customer', 'booking']);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'driver_checkpoint_log_id' => 'required|exists:driver_checkpoint_logs,id',
            'customer_id' => 'required|exists:users,id',
            'booking_id' => 'required|exists:bookings,id',
            'points' => 'nullable|integer|min:0',
        ]);

        return Scouring::create($data);
    }

    public function update(Request $request, Scouring $scouring)
    {
        $data = $request->validate([
            'driver_checkpoint_log_id' => 'sometimes|required|exists:driver_checkpoint_logs,id',
            'customer_id' => 'sometimes|required|exists:users,id',
            'booking_id' => 'sometimes|required|exists:bookings,id',
            'points' => 'nullable|integer|min:0',
        ]);

        $scouring->update($data);

        return $scouring;
    }

    public function destroy(Scouring $scouring)
    {
        $scouring->delete();

        return response()->noContent();
    }
}
