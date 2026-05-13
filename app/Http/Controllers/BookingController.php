<?php

namespace App\Http\Controllers;

use App\Http\Requests\BookingRequest;
use App\Models\Booking;

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

    public function store(BookingRequest $request)
    {
        return Booking::create($request->validated());
    }

    public function update(BookingRequest $request, Booking $booking)
    {
        $booking->update($request->validated());

        return $booking;
    }

    public function destroy(Booking $booking)
    {
        $booking->delete();

        return response()->noContent();
    }
}
