<?php

namespace App\Http\Controllers;

use App\Http\Requests\TripRequest;
use App\Models\Trip;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Validator;

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

    public function create (Request $request)
    {
    //    $validate =Validator::make($request->all(), [
    //     'type' => 'required',
    //     'point_price' => 'required',
    //     'money_price' => 'required',
    //     'status' => 'required',
    //     'departure_date' => 'required',
    //     'arrival_date' => 'required',
    //     'total_seats' => 'required',
    //     'available_seats' => 'required',
    //     'earned_points' => 'required',
    //    ]);

        // if($validate->fails()){
        //     return response()->json($validate->errors(),400);
        // }

        // $trip = Trip::create([
        // 'driver_id' => $request->driver_id,
        // 'type' => $request->type,
        // 'point_price' => 'required',
        // 'money_price' => 'required',
        // 'status' => 'required',
        // 'departure_date' => 'required',
        // 'arrival_date' => 'required',
        // 'total_seats' => 'required',
        // 'available_seats' => 'required',
        // 'earned_points' => 'required',
        // ]);

    }

    }



