<?php

namespace App\Http\Controllers;

use App\Http\Requests\BookingRequest;
use App\Models\Booking;
use App\Models\Checkpoint;
use App\Models\Seat;
use App\Models\Trip;
use App\Models\TripCheckpoint;
use App\Models\WaitingList;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

use function PHPSTORM_META\map;

class BookingController extends Controller
{
    public function index()
    {
        return Booking::with([
            'user',
            'driver',
            'trip',
            'pickupCheckpoint',
            'dropoffCheckpoint',
            'seats',
        ])->paginate(20);
    }

    public function show(Booking $booking)
    {
        return $booking->load([
            'user',
            'driver',
            'trip',
            'pickupCheckpoint',
            'dropoffCheckpoint',
            'seats',
            'scouring',
        ]);
    }

    public function store(Request $request)
    {
        
         $validate = Validator::make($request->all(),
        [
                'driver_id' => 'required|integer',
                'trip_id' => 'required|exists:trips,id',
                'pickup_checkpoint_id' => 'required|exists:checkpoints,id',
                'dropoff_checkpoint_id' => 'required|exists:checkpoints,id',
                'seats_count' => 'required|integer|min:1|max:50',
                'seat_numbers' => 'sometimes|required|array|min:1|max:50',
                'seat_numbers.*' => 'required|string|distinct|max:20',
                'status' => 'nullable|string|max:100',
        ]);
        if($validate->fails()){
            return response()->json($validate->errors(),400);
        }
        $data = $validate->validated();
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

    $booking= Booking::create([
                 'user_id' => auth('sanctum')->user()->id,
                 'driver_id' => $request->driver_id,
                'trip_id' => $request->trip_id,
                'pickup_checkpoint_id' => $request->pickup_checkpoint_id,
                'dropoff_checkpoint_id' => $request->dropoff_checkpoint_id,
                'seats_count' => $request->seats_count,
                'seat_numbers' => $request->seat_numbers,
        ]);

            $trip->available_seats -= $requestedSeats;
            $trip->save();

            $wat = WaitingList::where('trip_id' , $request->trip_id)->where('user_id' , auth('sanctum')->user()->id)->first();
            if($wat){
                $wat->delete();
            }

            return response()->json(['booking' => $booking]);
        
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

    //    // $data = $booking->validated();
    //     $booking = Booking::find($booking->id);
    // //    return response()->json(['sds']);
    //     return DB::transaction(function () use ($booking) {
    //         $originalTrip = $booking->trip;
    //         $originalSeats = (int) $booking->seats_count;
    //         $newTrip = $originalTrip;
    //         $newSeats = $originalSeats;


    //         if (array_key_exists('trip_id', $booking->trip_id) && ! is_null($booking->trip_id['trip_id']) && $booking->trip_id['trip_id'] !== $originalTrip->id) {
    //             $newTrip = Trip::find($booking->trip_id['trip_id']);

    //             if (! $newTrip) {
    //                 return response()->json(['message' => 'New trip not found.'], 404);
    //             }
    //         }


    //         if (array_key_exists('seats_count', $booking->seats_count) && ! is_null($booking->seats_count['seats_count'])) {
    //             $newSeats = (int) $booking->seats_count['seats_count'];
    //         }

    //         if ($newTrip->id !== $originalTrip->id) {
    //             if (Carbon::now()->addHours(2)->greaterThan(Carbon::parse($newTrip->departure_date))) {
    //                 return response()->json([
    //                     'message' => 'Booking must be made at least 2 hours before the trip departure.'
    //                 ], 422);
    //             }

    //             if ($newSeats > $newTrip->available_seats) {
    //                 return response()->json([
    //                     'message' => 'Not enough seats available on the new trip.'
    //                 ], 422);
    //             }

    //             $originalTrip->available_seats += $originalSeats;
    //             $originalTrip->save();

    //             $newTrip->available_seats -= $newSeats;
    //             $newTrip->save();
    //         } else {
    //             $seatDifference = $newSeats - $originalSeats;

    //             if ($seatDifference > 0 && $seatDifference > $originalTrip->available_seats) {
    //                 return response()->json([
    //                     'message' => 'Not enough seats available to increase reservation.'
    //                 ], 422);
    //             }

    //             $originalTrip->available_seats -= $seatDifference;
    //             $originalTrip->save();
    //         }

    //         $booking->update($booking->all());

    //         return $booking->load(['user', 'driver', 'trip', 'pickupCheckpoint', 'dropoffCheckpoint', 'scouring']);
    //     });
    }

    public function destroy(Booking $booking)
    {

        // $booking = Booking::find($re);
        // $trip = $booking->trip;
        //    if ($trip) {
        //      $trip->available_seats += (int) $booking->seats_count;
        //      $trip->save();
        //               }
        //     $booking->delete();
        // return response()->json([
        //     'succes' =>true,
        //     'msg' => 'deleted succesfully'
        // ]);
        return DB::transaction(function () use ($booking) {
        $trip = $booking->trip;

        if ($trip) {
          $trip->available_seats += (int) $booking->seats_count;
          $trip->save();
           }
            $releasedSeats = $booking->seat_count;
            $booking->delete();

            
            $book = Booking::where('trip_id' , $booking->trip_id)->value('id');
         

            $booking = Booking::find($book);
            $booking->status = 'cancelled';
            $booking->save();

        //     $waiting = WaitingList::where('trip_id',  $booking->trip_id)
        //     ->where('seat_count', $releasedSeats)
        //     ->orderBy('created_at', 'asc')
        //     ->first();

        // // إذا وجدنا شخصاً مناسباً
        // if ($waiting) {

        //     // إنشاء حجز جديد له
        //     Booking::create([
        //         'trip_id'   => $trip->id,
        //         'user_id'   => $waiting->user_id,
        //         'seat_count'=> $waiting->seat_count,
        //         'driver_id' => $booking->driver_id,
        //         'pickup_checkpoint_id' => $waiting->pickup_checkpoint_id,
        //         'dropoff_checkpoint_id' => $waiting->dropoff_checkpoint_id,
              
        //     ]);

        //     // إنقاص المقاعد التي أخذها من الرحلة
        //     $trip->seat_available -= $waiting->seat_count;
        //     $trip->save();

        //     // حذفه من قائمة الانتظار
        //     $waiting->delete();
        // }

            $status = Booking::where('id' , $book)->value('status');

            return response()->json([
                'message' => 'Booking cancelled successfully.',
                'status' => $status,
            ]);



        
        });

        return response()->noContent();
    }

    private function validateBookingDetails(
        Trip $trip,
        array $data
    ): void {
        if (
            isset($data['seat_numbers'])
            && count($data['seat_numbers']) !== $data['seats_count']
        ) {
            throw ValidationException::withMessages([
                'seat_numbers' => [
                    'The selected seats must match the seat count.',
                ],
            ]);
        }

        $checkpointIds = $trip->checkpoints()
            ->pluck('checkpoint_id');

        if (
            ! $checkpointIds->contains($data['pickup_checkpoint_id'])
            || ! $checkpointIds->contains($data['dropoff_checkpoint_id'])
        ) {
            throw ValidationException::withMessages([
                'pickup_checkpoint_id' => [
                    'Pickup and dropoff checkpoints must belong to the trip.',
                ],
            ]);
        }
    }

    private function validateAvailableSeats(
        Trip $trip,
        array $data
    ): void {
        if (
            $trip->total_seats > 50
            || $data['seats_count'] > $trip->available_seats
        ) {
            throw ValidationException::withMessages([
                'seats_count' => [
                    'Not enough seats are available.',
                ],
            ]);
        }
    }

    public function index_trip_time(Request $request)
    {
        // $trip = Trip::all();

        // $loc = Checkpoint::where( 'governorate', $request->governorate )->value('id');

        // $chec_loc = TripCheckpoint::where('checkpoint_id',  $loc )->pluck('trip_id');

        // $trip_time = Trip::whereIn('id', $chec_loc)
        //     ->whereDate('departure_date', $request->time)
        //     ->pluck();
           
        // $time = Carbon::parse($trip->departure_date)->format('H:i');;

        // return response()->json($time);

    $loc = Checkpoint::where(
        'governorate',
        $request->governorate
    )->value('id');

    $chec_loc = TripCheckpoint::where(
        'checkpoint_id',
        $loc
    )->pluck('trip_id');

    $trip_time = Trip::whereIn('id', $chec_loc)
        ->whereDate('departure_date', $request->time)
        ->get()
        ->map(function ($trip) {

            return [
                'id' => $trip->id,
                'time' => \Carbon\Carbon::parse(
                    $trip->departure_date
                )->format('H:i'),
                'status' => $trip->status,
            ];

        });

    return response()->json($trip_time);

    }

    public function Index_droppoff(Request $request)
    {


        $dropoff = Checkpoint::where('governorate' , $request->governorate)->get('name');

        return response()->json([
            'dropoff' => $dropoff,
        ]);
    }

    // public function Index_pickup(Request $request)
    // {


    //     $pickup = Checkpoint::where('governorate' , $request->governorate)->get('location');

    //     return response()->json([
    //         'pickup' => $pickup,
    //     ]);
    // }


}
