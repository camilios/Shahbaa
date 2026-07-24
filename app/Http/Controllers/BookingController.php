<?php

namespace App\Http\Controllers;

use App\Http\Requests\BookingRequest;
use App\Models\Booking;
use App\Models\Trip;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingController extends Controller
{
    public function index()
    {
        return Booking::with(['user', 'driver', 'trip', 'pickupCheckpoint', 'dropoffCheckpoint', 'seats'])->paginate(20);
    }

    public function show(Booking $booking)
    {
        return $booking->load(['user', 'driver', 'trip', 'pickupCheckpoint', 'dropoffCheckpoint', 'seats', 'scouring']);
    }

    public function store(BookingRequest $request)
    {
        return DB::transaction(function () use ($request) {
            $data = $request->validated();
            $actor = $request->user();
            if (! $actor->isAdmin() && strtolower((string) $actor->role) !== 'customer') {
                throw ValidationException::withMessages([
                    'user' => ['Only customers or administrators can create bookings.'],
                ]);
            }

            $trip = Trip::query()->lockForUpdate()->findOrFail($data['trip_id']);
            $this->validateBooking($trip, $data);

            $seatNumbers = $data['seat_numbers'] ?? null;
            unset($data['seat_numbers']);
            $seatsQuery = $trip->seats()->whereNull('booking_id')->lockForUpdate();
            $seats = $seatNumbers
                ? $seatsQuery->whereIn('seat_number', $seatNumbers)->get()
                : $seatsQuery->orderBy('seat_number')->limit($data['seats_count'])->get();
            if ($seats->count() !== $data['seats_count']) {
                throw ValidationException::withMessages(['seat_numbers' => ['One or more selected seats are no longer available.']]);
            }

            $data['user_id'] = $actor->isAdmin() ? $data['user_id'] : $actor->id;
            $data['driver_id'] = $trip->driver_id;
            $booking = Booking::create($data);
            $trip->seats()->whereKey($seats->modelKeys())->update(['booking_id' => $booking->id]);
            $trip->decrement('available_seats', $data['seats_count']);

            return $booking->load(['user', 'driver', 'trip', 'seats', 'pickupCheckpoint', 'dropoffCheckpoint']);
        });
    }

    public function update(BookingRequest $request, Booking $booking)
    {
        $booking->update($request->validated());

        return $booking;
    }

    public function destroy(Booking $booking)
    {
        DB::transaction(function () use ($booking) {
            $count = $booking->seats()->count();
            $booking->seats()->update(['booking_id' => null]);
            $booking->trip()->increment('available_seats', $count);
            $booking->delete();
        });

        return response()->noContent();
    }

    private function validateBooking(Trip $trip, array $data): void
    {
        if (isset($data['seat_numbers']) && count($data['seat_numbers']) !== $data['seats_count']) {
            throw ValidationException::withMessages([
                'seat_numbers' => ['The selected seats must match the seat count.'],
            ]);
        }

        if ($trip->total_seats > 50 || $data['seats_count'] > $trip->available_seats) {
            throw ValidationException::withMessages(['seats_count' => ['Not enough seats are available.']]);
        }

        $checkpointIds = $trip->checkpoints()->pluck('checkpoint_id');
        if (! $checkpointIds->contains($data['pickup_checkpoint_id']) || ! $checkpointIds->contains($data['dropoff_checkpoint_id'])) {
            throw ValidationException::withMessages([
                'pickup_checkpoint_id' => ['Pickup and dropoff checkpoints must belong to the trip.'],
            ]);
        }
    }
}
