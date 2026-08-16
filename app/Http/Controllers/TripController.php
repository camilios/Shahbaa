<?php

namespace App\Http\Controllers;

use App\Http\Requests\TripRequest;
use App\Models\Booking;
use App\Models\Checkpoint;
use App\Models\Seat;
use App\Models\Trip;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Validator;
use Nette\Schema\ValidationException;

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


    public function index_user_trip()
    {

           $users = auth('sanctum')->user()->id;

        // $bok = Booking::where('user_id', $user->id)->pluck('trip_id')->toArray();
        // $trips = Trip::whereIn('id', $bok)->get('departure_date');

        $Trips = DB::table('bookings')->where('user_id' , $users)->
        join('trips' , 'trips.id' , 'bookings.trip_id')->join('seats' , 'seats.trip_id', 'bookings.trip_id')
        ->join('trip_checkpoints' , 'trip_checkpoints.trip_id' , 'seats.trip_id')->
        join('checkpoints' , 'checkpoints.id' , 'trip_checkpoints.checkpoint_id')
        ->get(['seat_number' , 'departure_date' , 'governorate' , 'name' , 'bookings.status']);
        return response()->json(['Trips' => $Trips]);

    }

        public function index_gov()
    {
        $gov = Checkpoint::pluck('governorate')->unique()->values();

        return response()->json(['governorates' => $gov]);

    }


    }



