<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Checkpoint;
use App\Models\Governorate;
use App\Models\Trip;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CustomerTripController extends Controller
{
    public function governorates()
    {
        return response()->json([
            'governorates' => Governorate::query()->orderBy('name')->pluck('name'),
            'data' => Governorate::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function checkpointsByGovernorate(Request $request)
    {
        $data = $request->validate([
            'governorate_id' => ['nullable', 'integer', 'exists:governorates,id', 'required_without:governorate'],
            'governorate' => ['nullable', 'string', 'max:255', 'required_without:governorate_id'],
        ]);

        return Checkpoint::query()
            ->when(
                isset($data['governorate_id']),
                fn ($query) => $query->where('governorate_id', $data['governorate_id']),
                fn ($query) => $query->where('governorate', $data['governorate'])
            )
            ->orderBy('name')
            ->get(['id', 'name', 'location', 'governorate', 'governorate_id']);
    }

    public function myTrips(Request $request)
    {
        return Booking::query()
            ->where('user_id', $request->user()->id)
            ->where('status', '!=', 'cancelled')
            ->with([
                'trip.driver',
                'trip.checkpoints.checkpoint',
                'pickupCheckpoint',
                'dropoffCheckpoint',
                'seats',
            ])
            ->latest('id')
            ->paginate(20);
    }

    public function detailsBeforeScan(Request $request, Trip $trip)
    {
        return $this->ticketDetails($request, $trip, false);
    }

    public function detailsAfterScan(Request $request, Trip $trip)
    {
        return $this->ticketDetails($request, $trip, true);
    }

    public function detailsBeforeScanLegacy(Request $request)
    {
        $data = $request->validate(['id' => ['required', 'integer', 'exists:trips,id']]);

        return $this->ticketDetails($request, Trip::findOrFail($data['id']), false);
    }

    public function detailsAfterScanLegacy(Request $request)
    {
        $data = $request->validate(['id' => ['required', 'integer', 'exists:trips,id']]);

        return $this->ticketDetails($request, Trip::findOrFail($data['id']), true);
    }

    private function ticketDetails(Request $request, Trip $trip, bool $mustBeBoarded)
    {
        $checkpointIds = $trip->checkpoints()->pluck('checkpoint_id')->all();
        $data = $request->validate([
            'dropoff_checkpoint_id' => [
                'sometimes',
                'integer',
                Rule::in($checkpointIds),
            ],
        ]);

        $booking = $trip->bookings()
            ->where('user_id', $request->user()->id)
            ->where('status', '!=', 'cancelled')
            ->first();

        if (! $booking) {
            abort(404, 'No active booking was found for this trip.');
        }

        if ($mustBeBoarded !== ($booking->boarded_at !== null)) {
            throw ValidationException::withMessages([
                'booking' => [$mustBeBoarded
                    ? 'The ticket has not been scanned yet.'
                    : 'The ticket has already been scanned.'],
            ]);
        }

        if (isset($data['dropoff_checkpoint_id'])) {
            $booking->update([
                'dropoff_checkpoint_id' => $data['dropoff_checkpoint_id'],
            ]);
        }

        $trip->load('checkpoints.checkpoint');
        $booking->load(['pickupCheckpoint', 'dropoffCheckpoint', 'seats']);
        $endsAt = $trip->arrival_date ?? $trip->departure_date;

        return response()->json([
            'booking' => $booking,
            'trip' => $trip,
            'remaining_seconds' => $endsAt
                ? max(0, now()->diffInSeconds($endsAt, false))
                : null,
        ]);
    }
}
