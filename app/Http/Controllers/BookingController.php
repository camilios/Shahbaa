<?php

namespace App\Http\Controllers;

use App\Http\Requests\BookingRequest;
use App\Models\Booking;
use App\Models\Checkpoint;
use App\Models\Seat;
use App\Models\Trip;
use App\Models\TripCheckpoint;
use Carbon\Carbon;
use Carbon\Traits\Date as TraitsDate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

class BookingController extends Controller
{
    public function index()
    {
        return Booking::with(['user', 'driver', 'trip', 'pickupCheckpoint', 'dropoffCheckpoint', 'scouring'])->paginate(20);
    }

    public function show(Booking $booking)
    {
        return $booking->load(['user', 'driver', 'trip', 'pickupCheckpoint', 'dropoffCheckpoint', 'scouring']);
    }

    public function store(BookingRequest $request)
    {
        $data = $request->validated();
        $trip = Trip::find($data['trip_id']);

        if (! $trip) {
            return response()->json(['message' => 'Trip not found.'], 404);
        }

        if (Carbon::now()->addHours(2)->greaterThan(Carbon::parse($trip->departure_date))) {
            return response()->json([
                'message' => 'Booking must be made at least 2 hours before the trip departure.'
            ], 422);
        }

        $requestedSeats = (int) $data['seats_count'];

        if ($requestedSeats > $trip->available_seats || $trip->available_seats <= 0) {
            return response()->json([
                'message' => 'Not enough seats available for this trip.'
            ], 422);
           
        }
        $this->seat_num($request->trip_id , $requestedSeats);

        return DB::transaction(function () use ($data, $trip, $requestedSeats) {
            $booking = Booking::create($data);

            $trip->available_seats -= $requestedSeats;
            $trip->save();

            return $booking->load(['user', 'driver', 'trip', 'pickupCheckpoint', 'dropoffCheckpoint', 'scouring']);
        });
    }

     public function seat_num($trip_id , $seat_count)
    {
      $gender = Auth::user()->gender;
      $total_seats = Trip::where('id' , $trip_id)->value('total_seats');
      $seat = Seat::where('trip_id' , $trip_id)->latest('seat_number')->first();
    
        $trip = Trip::find($trip_id);
            if (! $trip) {
                return response()->json(['message' => 'Trip not found.'], 404);
            }
            $seat_t = $seat + $seat_count;
           for ($j = $seat; $j <= $seat_t; $j++) {

            Seat::create([
                'trip_id' => $trip_id,
                'seat_number' => $j
            ]);
        
           }
             
              
            

      
    }



    public function update( Request $request)
    {

        $booking = Booking::find($request->id);
        if(!$booking){
            return response()->json(['message' => 'Booking not found.'], 404);
        }
        if($request->trip_id){
            $trip = Trip::find($request->trip_id);
            if (! $trip) {
                return response()->json(['message' => 'Trip not found.'], 404);
            }
            if (Carbon::now()->addHours(2)->greaterThan(Carbon::parse($trip->departure_date))) {
                return response()->json([
                    'message' => 'Booking must be made at least 2 hours before the trip departure.'
                ], 422);
            }
            if ($request->seats_count > $trip->available_seats || $trip->available_seats <= 0) {
                return response()->json([
                    'message' => 'Not enough seats available for this trip.'
                ], 422);
            }
        }
        $booking->seats_count = $request->seats_count;
        $booking->trip_id = $request->trip_id;
        $booking->save();


        return response()->json(['Booking'=>$booking]);
   
    }

    public function destroy(Booking $booking)
    {

     
        return DB::transaction(function () use ($booking) {
        $trip = $booking->trip;

        if ($trip) {
          $trip->available_seats += (int) $booking->seats_count;
          $trip->save();
           }

           $booking->delete();

         return response()->noContent();
        });
    }


    // public function index_trip(Request $request)
    // {
    //  TripCheckpoint::insert([
    //     'trip_id' => $request->trip_id,
    //     'checkpoint_id' => $request->checkpoint_id,
    //     'description' => $request->description,
    //  ]);
    // }

    public function index_trip_time(Request $request)
    {
        $trip = Trip::all();

        $loc = Checkpoint::where('governorate' , $request->governorate)->value('id');
        $chec_loc = TripCheckpoint::where('checkpoint_id' , $loc)->pluck('trip_id');
        $trip_time = Trip::whereIn('id',$chec_loc)->whereDate('departure_date' , $request->time)->get();
        return response()->json($trip_time);
    }

    public function Index_droppoff (Request $request)
    {


        $dropoff =  Checkpoint::where('governorate', $request->name)->get(['location']);

        return response()->json(['dropoff'=>$dropoff]);
    }

        public function Index_pickup (Request $request)
    {


        $pickup = Checkpoint::where('governorate' , $request->name)->get('name');

        return response()->json(['pickup'=>$pickup]);
    }

   
}
