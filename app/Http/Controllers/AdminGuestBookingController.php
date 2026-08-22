<?php

namespace App\Http\Controllers;

use App\Http\Requests\GuestBookingRequest;
use App\Models\Booking;
use App\Models\Checkpoint;
use App\Models\Trip;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminGuestBookingController extends Controller
{
    public function __invoke(GuestBookingRequest $request): JsonResponse
    {
        return DB::transaction(function () use ($request): JsonResponse {
            $data = $request->validated();
            $trip = Trip::query()->lockForUpdate()->findOrFail($data['trip_id']);

            if (! $trip->departure_date || $trip->departure_date->lte(now())) {
                throw ValidationException::withMessages([
                    'trip_id' => ['Bookings cannot be created after the trip has departed.'],
                ]);
            }

            if (in_array($trip->status, ['cancelled', 'canceled', 'completed'], true)) {
                throw ValidationException::withMessages([
                    'trip_id' => ['Bookings cannot be created for a cancelled or completed trip.'],
                ]);
            }

            $checkpointIds = $trip->checkpoints()->pluck('checkpoint_id');
            if (
                ! $checkpointIds->contains($data['pickup_checkpoint_id'])
                || ! $checkpointIds->contains($data['dropoff_checkpoint_id'])
            ) {
                throw ValidationException::withMessages([
                    'pickup_checkpoint_id' => ['Pickup and dropoff checkpoints must belong to the trip.'],
                ]);
            }

            $pickupCheckpoint = Checkpoint::findOrFail($data['pickup_checkpoint_id']);
            $dropoffCheckpoint = Checkpoint::findOrFail($data['dropoff_checkpoint_id']);
            $orderedCheckpointIds = $trip->checkpoints()->orderBy('id')->pluck('checkpoint_id');
            $sourceGovernorate = $trip->source_governorate
                ?? Checkpoint::find($orderedCheckpointIds->first())?->governorate;
            $destinationGovernorate = $trip->destination_governorate
                ?? Checkpoint::find($orderedCheckpointIds->last())?->governorate;
            if ($pickupCheckpoint->governorate !== $sourceGovernorate) {
                throw ValidationException::withMessages([
                    'pickup_checkpoint_id' => ['نقطة الصعود يجب أن تكون ضمن محافظة انطلاق الرحلة.'],
                ]);
            }
            if ($dropoffCheckpoint->governorate !== $destinationGovernorate) {
                throw ValidationException::withMessages([
                    'dropoff_checkpoint_id' => ['نقطة النزول يجب أن تكون ضمن محافظة وصول الرحلة.'],
                ]);
            }

            $seatNumbers = $data['seat_numbers'] ?? null;
            if ($seatNumbers && count($seatNumbers) !== $data['seats_count']) {
                throw ValidationException::withMessages([
                    'seat_numbers' => ['The selected seats must match the seat count.'],
                ]);
            }

            $seatsQuery = $trip->seats()->whereNull('booking_id')->lockForUpdate();
            $seats = $seatNumbers
                ? $seatsQuery->whereIn('seat_number', $seatNumbers)->get()
                : $seatsQuery->orderBy('seat_number')->limit($data['seats_count'])->get();

            if ($seats->count() !== $data['seats_count']) {
                throw ValidationException::withMessages([
                    'seat_numbers' => ['The requested seats are not available.'],
                ]);
            }

            $booking = Booking::create([
                'user_id' => null,
                'booking_source' => 'office_guest',
                'guest_name' => $data['guest_name'],
                'guest_phone' => $data['guest_phone'],
                'guest_gender' => $data['guest_gender'],
                'guest_national_number' => $data['guest_national_number'] ?? null,
                'driver_id' => $trip->driver_id,
                'trip_id' => $trip->id,
                'pickup_checkpoint_id' => $data['pickup_checkpoint_id'],
                'dropoff_checkpoint_id' => $data['dropoff_checkpoint_id'],
                'seats_count' => $data['seats_count'],
                'status' => 'pending',
            ]);

            $trip->seats()->whereKey($seats->modelKeys())->update(['booking_id' => $booking->id]);
            $trip->decrement('available_seats', $data['seats_count']);

            return response()->json([
                'message' => 'Office guest booking created successfully.',
                'booking' => $booking->load([
                    'driver',
                    'trip',
                    'seats',
                    'pickupCheckpoint',
                    'dropoffCheckpoint',
                ]),
            ], 201);
        });
    }
}
