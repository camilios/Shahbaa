<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckpointRequest;
use App\Models\Booking;
use App\Models\Checkpoint;
use App\Models\Trip;
use App\Models\TripCheckpoint;
use Illuminate\Http\Request;
use Carbon\Carbon;


class CheckpointController extends Controller
{
    public function index_droppoff_pickup(Request $request)
    {

       return Checkpoint::where('governorate',$request->governorate)->get(['id', 'name']);
    }

    public function details_not_scan(Request $request)
    {
        $book = Booking::where('trip_id' , $request->id)->value('id');
        $status = Booking::where('id' , $book)->value('status');
           if ($status == 'pending')
            {

        if($book != null)
            {
        $trip_date = Trip::where('id',$request->id)->value('departure_date');
            }

               $booking = Booking::find($request->id);
               $booking->dropoff_checkpoint_id = $request->dropoff_checkpoint_id;
               $booking->save();

               $droopoff = Checkpoint::where('id' , $request->dropoff_checkpoint_id)->value('name');



    $trip = Trip::findOrFail($request->id);

    $startTime = Carbon::parse($trip->departure_date);

    $endTime = $startTime->copy()->addMinutes(90);

    $now = Carbon::now();

    if ($now->lt($startTime)) {

        $remaining = 90 * 60;

    } elseif ($now->gte($endTime)) {

        $remaining = 0;

    } else {

        $remaining = $now->diffInSeconds($endTime);

    }

    return response()->json([
        'departure_date' => $startTime->format('Y-m-d H:i:s'),
        'end_time' => $endTime->format('Y-m-d H:i:s'),
        'remaining_seconds' => $remaining,
        'remaining_time' => gmdate('H:i:s', $remaining),
        'trip_date' => $trip_date,
        'droopoff' => $droopoff
    ]);
            }
            else
            {
                return response()->json([
                    'message' => 'You have already scanned your ticket for this trip.',
                    'status' => 400,
                ]);
            }
    }

    public function details_scan(Request $request)
    {
         $book = Booking::where('trip_id' , $request->id)->value('id');
         $status = Booking::where('id' , $book)->value('status');
          if ($status == 'booked')
            {
            
        
        if($book != null)
            {
        $trip_date = Trip::where('id',$request->id)->value('departure_date');
            }

               $booking = Booking::find($request->id);
               $booking->dropoff_checkpoint_id = $request->dropoff_checkpoint_id;
               $booking->save();

               $droopoff = Checkpoint::where('id' , $request->dropoff_checkpoint_id)->value('name');

               $checkpoint = TripCheckpoint::where('trip_id', $request->id)->value('checkpoint_id');
               $governorate = Checkpoint::where('id' , $checkpoint)->value('governorate');
               $all_point = Checkpoint::where('governorate' , $governorate)->get('name');

                   return response()->json([
        'trip_date' => $trip_date,
        'droopoff' => $droopoff,
       'all_point'=> $all_point
    ]);
            }
            else
            {
                return response()->json([
                    'message' => 'You have not scanned your ticket for this trip.',
                    'status' => 400,
                ]);
            }


    }

    public function store(CheckpointRequest $request)
    {
        return Checkpoint::create($request->validated());
    }

    public function update(CheckpointRequest $request, Checkpoint $checkpoint)
    {
        $checkpoint->update($request->validated());

        return $checkpoint;
    }

    public function destroy(Checkpoint $checkpoint)
    {
        $checkpoint->delete();

        return response()->noContent();
    }

    public function index_checkpoints(Request $request)
    {

        $checkpoints = Checkpoint::where('governorate', $request->governorate)->get(['location']);
        return response()->json($checkpoints, 200);
    }

}
