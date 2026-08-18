<?php

namespace App\Http\Controllers;

use App\Http\Requests\BookingRequest;
use App\Models\Booking;
use App\Models\Checkpoint;
use App\Models\Trip;
use App\Models\TripCheckpoint;
use App\Models\WaitingList;
use App\Services\WaitingListPromotionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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

    public function store(
        BookingRequest $request,
        WaitingListPromotionService $promotionService
    ) {
        return DB::transaction(function () use ($request, $promotionService) {
            $data = $request->validated();
            $actor = $request->user();

            if (
                ! $actor->isAdmin()
                && strtolower((string) $actor->role) !== 'customer'
            ) {
                throw ValidationException::withMessages([
                    'user' => [
                        'Only customers or administrators can create bookings.',
                    ],
                ]);
            }

            $trip = Trip::query()
                ->lockForUpdate()
                ->findOrFail($data['trip_id']);

            if (
                $trip->departure_date
                && $trip->departure_date->lte(now()->addHours(2))
            ) {
                throw ValidationException::withMessages([
                    'trip_id' => [
                        'Bookings must be created at least two hours before departure.',
                    ],
                ]);
            }

            $this->validateBookingDetails($trip, $data);

            $userId = $actor->isAdmin()
                ? $data['user_id']
                : $actor->id;

            if ($trip->available_seats === 0) {
                $waitingList = WaitingList::firstOrCreate(
                    [
                        'user_id' => $userId,
                        'trip_id' => $trip->id,
                    ],
                    [
                        'pickup_checkpoint_id' => $data['pickup_checkpoint_id'],
                        'dropoff_checkpoint_id' => $data['dropoff_checkpoint_id'],
                        'seats_count' => $data['seats_count'],
                        'status' => 'pending',
                    ],
                );

                $newTrip = $promotionService->promoteWhenReady($trip);

                return response()->json([
                    'message' => $newTrip
                        ? 'A new trip has been created and the waiting customers have been booked on it.'
                        : 'The trip is full. The customer has been added to the waiting list.',
                    'waiting_list' => $newTrip
                        ? null
                        : $waitingList->load(['user', 'trip']),
                    'new_trip' => $newTrip?->load([
                        'driver',
                        'seats',
                        'checkpoints.checkpoint',
                        'bookings.user',
                    ]),
                ], 201);
            }

            $this->validateAvailableSeats($trip, $data);

            $seatNumbers = $data['seat_numbers'] ?? null;
            unset($data['seat_numbers']);

            $seatsQuery = $trip->seats()
                ->whereNull('booking_id')
                ->lockForUpdate();

            $seats = $seatNumbers
                ? $seatsQuery
                    ->whereIn('seat_number', $seatNumbers)
                    ->get()
                : $seatsQuery
                    ->orderBy('seat_number')
                    ->limit($data['seats_count'])
                    ->get();

            if ($seats->count() !== $data['seats_count']) {
                throw ValidationException::withMessages([
                    'seat_numbers' => [
                        'One or more selected seats are no longer available.',
                    ],
                ]);
            }

            $data['user_id'] = $userId;
            $data['driver_id'] = $trip->driver_id;

            $booking = Booking::create($data);

            $trip->seats()
                ->whereKey($seats->modelKeys())
                ->update(['booking_id' => $booking->id]);

            $trip->decrement(
                'available_seats',
                $data['seats_count']
            );

            return $booking->load([
                'user',
                'driver',
                'trip',
                'seats',
                'pickupCheckpoint',
                'dropoffCheckpoint',
            ]);
        });
    }

    public function update(
        BookingRequest $request,
        Booking $booking
    ) {
        $booking->update($request->validated());

        return $booking;
    }

    public function destroy(Booking $booking)
    {
        DB::transaction(function () use ($booking) {
            $count = $booking->seats()->count();

            $booking->seats()->update([
                'booking_id' => null,
            ]);

            $booking->trip()->increment(
                'available_seats',
                $count
            );

            $booking->delete();
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
        $data = $request->validate([
            'governorate' => ['required', 'string', 'max:255'],
            'time' => ['required', 'date'],
        ]);

        $checkpointIds = Checkpoint::query()
            ->where('governorate', $data['governorate'])
            ->pluck('id');

        $tripIds = TripCheckpoint::query()
            ->whereIn('checkpoint_id', $checkpointIds)
            ->pluck('trip_id');

        $tripTime = Trip::query()
            ->whereIn('id', $tripIds)
            ->whereDate('departure_date', $data['time'])
            ->orderBy('departure_date')
            ->get()
            ->map(fn (Trip $trip) => [
                'id' => $trip->id,
                'departure_date' => $trip->departure_date,
                'time' => $trip->departure_date?->format('H:i'),
                'status' => $trip->status,
            ]);

        return response()->json($tripTime);
    }

    public function Index_droppoff(Request $request)
    {
        $dropoff = Checkpoint::where(
            'governorate',
            $request->governorate
        )->get('name');

        return response()->json([
            'dropoff' => $dropoff,
        ]);
    }

    public function Index_pickup(Request $request)
    {
        $pickup = Checkpoint::where(
            'governorate',
            $request->governorate
        )->get('location');

        return response()->json([
            'pickup' => $pickup,
        ]);
    }
}
