<?php

namespace App\Http\Controllers;

use App\Http\Requests\TripRequest;
use App\Models\Booking;
use App\Models\Checkpoint;
use App\Models\Seat;
use App\Models\Trip;
use App\Models\TripCheckpoint;
use App\Models\User;
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

    public function scan_qr (Request $request)
    {
      $user = User::where('qr_token' , $request->qr)->value('id');
      $book = Booking::where('user_id' , $user)->where('trip_id' , $request->trip_id)->value('status');
      if ($book == 'pending') {
          $id = Booking::where('user_id' , $user)->where('trip_id' , $request->trip_id)->value('id');
           $id->status = 'completed';
            $id->save();
      }
      return response()->json('he or she is booked in this trip' , 200);
    }

       public function detailes (Request $request)
     {


     }

     public function trip_checkpoint()
     {

     }

       public function index_user_trip ()
     {
         $user = Auth::user()->id;
         $book = Booking::where('user_id' , $user)->pluck('trip_id');
         $trip = Trip::whereIn('id' , $book)->get('status','departure_date');
         $chec_id = TripCheckpoint::whereIn('trip_id' , $book)->pluck('checkpoint_id');
         $che = Checkpoint::whereIn('id' , $chec_id)->get();
         $seats = Seat::whereIn('trip_id' , $book)->get();
         return response()->json(['trip'=>$trip , 'checkpoints'=>$che , 'seats'=>$seats]);
         


     }

     public function insert_trip_checkpoint(Request $request)
     {
        TripCheckpoint::insert([
        'trip_id' => $request->trip_id,
        'checkpoint_id' => $request->checkpoint_id,
        'description' => $request->description,
        ]);
        return response()->json('checkpoint added successfully' , 200);
     }

      public function index_trip_checkpoint($trip_id , $pickup_checkpoint_id, $dropoff_checkpoint_id )
        {
     

         }




