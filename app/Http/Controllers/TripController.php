<?php

namespace App\Http\Controllers;

use App\Models\Trip;
use Illuminate\Http\Request;

class TripController extends Controller
{
    public function index()
    {
        return Trip::with(['driver', 'seats', 'checkpoints', 'ratings'])->paginate(20);
    }

    public function show(Trip $trip)
    {
        return $trip->load(['driver', 'seats', 'checkpoints', 'ratings', 'waitingList']);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'driver_id' => 'required|exists:users,id',
            'type' => 'required|string|max:255',
            'point_price' => 'nullable|numeric|min:0',
            'money_price' => 'nullable|numeric|min:0',
            'status' => 'nullable|string|max:100',
            'departure_date' => 'nullable|date',
            'arrival_date' => 'nullable|date',
            'total_seats' => 'nullable|integer|min:0',
            'available_seats' => 'nullable|integer|min:0',
            'earned_points' => 'nullable|integer|min:0',
        ]);

        return Trip::create($data);
    }

    public function update(Request $request, Trip $trip)
    {
        $data = $request->validate([
            'driver_id' => 'sometimes|required|exists:users,id',
            'type' => 'sometimes|required|string|max:255',
            'point_price' => 'nullable|numeric|min:0',
            'money_price' => 'nullable|numeric|min:0',
            'status' => 'nullable|string|max:100',
            'departure_date' => 'nullable|date',
            'arrival_date' => 'nullable|date',
            'total_seats' => 'nullable|integer|min:0',
            'available_seats' => 'nullable|integer|min:0',
            'earned_points' => 'nullable|integer|min:0',
        ]);

        $trip->update($data);

        return $trip;
    }

    public function destroy(Trip $trip)
    {
        $trip->delete();

        return response()->noContent();
    }
}
