<?php

namespace App\Http\Controllers;

use App\Http\Requests\TripRequest;
use App\Models\Trip;

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

    public function store(TripRequest $request)
    {
        return Trip::create($request->validated());
    }

    public function update(TripRequest $request, Trip $trip)
    {
        $trip->update($request->validated());

        return $trip;
    }

    public function destroy(Trip $trip)
    {
        $trip->delete();

        return response()->noContent();
    }
}
