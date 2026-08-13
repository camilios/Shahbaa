<?php

namespace App\Http\Controllers;

use App\Http\Requests\TripRequest;
use App\Models\Booking;
use App\Models\Checkpoint;
use App\Models\Seat;
use App\Models\Trip;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Validator;

class TripController extends Controller
{
    public function index()
    {
        return Trip::with([
            'driver',
            'seats',
            'checkpoints.checkpoint',
            'ratings.repliedBy',
        ])->paginate(20);
    }

    public function show(Trip $trip)
    {
        return $trip->load([
            'driver',
            'seats',
            'bookedSeats',
            'checkpoints.checkpoint',
            'ratings.repliedBy',
            'waitingList',
        ]);
    }

    public function store(TripRequest $request)
    {
        return DB::transaction(function () use ($request) {
            $data = $request->validated();
            $checkpointIds = Arr::pull($data, 'checkpoint_ids');
            unset($data['available_seats']);

            $data['available_seats'] = $data['total_seats'];
            $trip = Trip::create($data);

            $this->replaceCheckpoints($trip, $checkpointIds);
            $this->synchronizeSeats($trip, $trip->total_seats);

            return $trip->load([
                'driver',
                'seats',
                'checkpoints.checkpoint',
            ]);
        });
    }

    public function update(TripRequest $request, Trip $trip)
    {
        return DB::transaction(function () use ($request, $trip) {
            $data = $request->validated();
            $checkpointIds = Arr::pull($data, 'checkpoint_ids');
            unset($data['available_seats']);

            if (isset($data['total_seats'])) {
                $booked = $trip->seats()
                    ->whereNotNull('booking_id')
                    ->count();

                if ($data['total_seats'] < $booked) {
                    throw ValidationException::withMessages([
                        'total_seats' => [
                            'The seat count cannot be less than the number of booked seats.',
                        ],
                    ]);
                }
            }

            $trip->update($data);

            if ($checkpointIds !== null) {
                $this->replaceCheckpoints($trip, $checkpointIds);
            }

            if (isset($data['total_seats'])) {
                $this->synchronizeSeats($trip, $data['total_seats']);
            }

            $trip->update([
                'available_seats' => $trip->seats()
                    ->whereNull('booking_id')
                    ->count(),
            ]);

            return $trip->load([
                'driver',
                'seats',
                'checkpoints.checkpoint',
            ]);
        });
    }

    public function destroy(Trip $trip)
    {
        $trip->delete();

        return response()->noContent();
    }

    private function replaceCheckpoints(Trip $trip, array $checkpointIds): void
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



