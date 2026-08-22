<?php

namespace App\Http\Controllers;

use App\Http\Requests\TripRequest;
use App\Models\Checkpoint;
use App\Models\Governorate;
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
            'sourceGovernorateRelation',
            'destinationGovernorateRelation',
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
            'sourceGovernorateRelation',
            'destinationGovernorateRelation',
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

            $this->validateRouteGovernorates($data, $checkpointIds);

            $data['available_seats'] = $data['total_seats'];
            $trip = Trip::create($data);

            $this->replaceCheckpoints($trip, $checkpointIds);
            $this->synchronizeSeats($trip, $trip->total_seats);

            return $trip->load([
                'driver',
                'seats.booking.user',
                'checkpoints.checkpoint',
                'sourceGovernorateRelation',
                'destinationGovernorateRelation',
            ]);
        });
    }

    public function update(TripRequest $request, Trip $trip)
    {
        return DB::transaction(function () use ($request, $trip) {
            $data = $request->validated();
            $checkpointIds = Arr::pull($data, 'checkpoint_ids');
            unset($data['available_seats']);

            $routeCheckpointIds = $checkpointIds ?? $trip->checkpoints()
                ->orderBy('id')
                ->pluck('checkpoint_id')
                ->all();
            $this->validateRouteGovernorates($data, $routeCheckpointIds, $trip);

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
                'sourceGovernorateRelation',
                'destinationGovernorateRelation',
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

    private function validateRouteGovernorates(array &$data, array $checkpointIds, ?Trip $trip = null): void
    {
        $sourceId = $data['source_governorate_id'] ?? $trip?->source_governorate_id;
        $destinationId = $data['destination_governorate_id'] ?? $trip?->destination_governorate_id;
        $route = Checkpoint::query()
            ->whereIn('id', $checkpointIds)
            ->get(['id', 'governorate_id'])
            ->keyBy('id');
        $firstGovernorateId = $route->get($checkpointIds[0] ?? null)?->governorate_id;
        $lastGovernorateId = $route->get($checkpointIds[count($checkpointIds) - 1] ?? null)?->governorate_id;

        if ((int) $sourceId === (int) $destinationId) {
            throw ValidationException::withMessages([
                'destination_governorate_id' => ['محافظة الوصول يجب أن تختلف عن محافظة الانطلاق.'],
            ]);
        }

        if ((int) $sourceId !== (int) $firstGovernorateId) {
            throw ValidationException::withMessages([
                'source_governorate_id' => ['محافظة الانطلاق يجب أن تطابق محافظة أول نقطة في مسار الرحلة.'],
            ]);
        }

        if ((int) $destinationId !== (int) $lastGovernorateId) {
            throw ValidationException::withMessages([
                'destination_governorate_id' => ['محافظة الوصول يجب أن تطابق محافظة آخر نقطة في مسار الرحلة.'],
            ]);
        }

        $data['source_governorate'] = Governorate::findOrFail($sourceId)->name;
        $data['destination_governorate'] = Governorate::findOrFail($destinationId)->name;
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
