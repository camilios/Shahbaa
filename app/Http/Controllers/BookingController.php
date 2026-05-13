<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function index()
    {
        return Booking::with(['user', 'driver', 'trip', 'pickupCheckpoint', 'dropoffCheckpoint'])->paginate(20);
    }

    public function show(Booking $booking)
    {
        return $booking->load(['user', 'driver', 'trip', 'pickupCheckpoint', 'dropoffCheckpoint', 'scouring']);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'driver_id' => 'required|exists:users,id',
            'trip_id' => 'required|exists:trips,id',
            'pickup_checkpoint_id' => 'required|exists:checkpoints,id',
            'dropoff_checkpoint_id' => 'required|exists:checkpoints,id',
            'seats_count' => 'required|integer|min:1',
            'status' => 'nullable|string|max:100',
        ]);

        return Booking::create($data);
    }

    public function update(Request $request, Booking $booking)
    {
        $data = $request->validate([
            'user_id' => 'sometimes|required|exists:users,id',
            'driver_id' => 'sometimes|required|exists:users,id',
            'trip_id' => 'sometimes|required|exists:trips,id',
            'pickup_checkpoint_id' => 'sometimes|required|exists:checkpoints,id',
            'dropoff_checkpoint_id' => 'sometimes|required|exists:checkpoints,id',
            'seats_count' => 'nullable|integer|min:1',
            'status' => 'nullable|string|max:100',
        ]);

        $booking->update($data);

        return $booking;
    }

    public function destroy(Booking $booking)
    {
        $booking->delete();

        return response()->noContent();
    }
}
