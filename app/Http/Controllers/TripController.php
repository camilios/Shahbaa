<?php

namespace App\Http\Controllers;

use App\Http\Requests\TripRequest;
use App\Models\Trip;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TripController extends Controller
{
    public function index()
    {
        return Trip::with([
            'driver',
            'seats.booking.user',
            'checkpoints.checkpoint',
            'ratings.repliedBy',
        ])->paginate(20);
    }

    public function show(Trip $trip)
    {
        return $trip->load([
            'driver',
            'seats.booking.user',
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
                'seats.booking.user',
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
                'seats.booking.user',
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
        $trip->checkpoints()->delete();

        foreach ($checkpointIds as $index => $checkpointId) {
            $trip->checkpoints()->create([
                'checkpoint_id' => $checkpointId,
                'description' => 'Stop '.($index + 1),
            ]);
        }
    }

    private function synchronizeSeats(Trip $trip, int $total): void
    {
        $existing = $trip->seats()->pluck('booking_id', 'seat_number');

        for ($number = 1; $number <= $total; $number++) {
            $seatNumber = 'S'.str_pad(
                (string) $number,
                2,
                '0',
                STR_PAD_LEFT
            );

            if (! $existing->has($seatNumber)) {
                $trip->seats()->create([
                    'seat_number' => $seatNumber,
                ]);
            }
        }

        $allowed = collect(range(1, $total))
            ->map(
                fn ($number) => 'S'.str_pad(
                    (string) $number,
                    2,
                    '0',
                    STR_PAD_LEFT
                )
            );

        $trip->seats()
            ->whereNull('booking_id')
            ->whereNotIn('seat_number', $allowed)
            ->delete();
    }
}
