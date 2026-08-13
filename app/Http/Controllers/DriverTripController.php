<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesTripAccess;
use App\Models\Trip;
use Illuminate\Http\Request;

class DriverTripController extends Controller
{
    use AuthorizesTripAccess;

    /**
     * List the authenticated driver's trips (schedules screen).
     *
     * Supports an optional `period` filter (today / week / month) so the
     * driver can view their daily, weekly, or monthly schedule.
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'period' => ['nullable', 'in:today,week,month'],
        ]);

        $query = Trip::where('driver_id', $request->user()->id)
            ->withCount('bookings');

        if ($period = $validated['period'] ?? null) {
            $query->whereBetween('departure_date', $this->periodRange($period));
        }

        $trips = $query->orderBy('departure_date')->paginate(20);

        return response()->json($trips);
    }

    /**
     * Resolve a period keyword to a [start, end] datetime range around now.
     *
     * @return array{0: \Illuminate\Support\Carbon, 1: \Illuminate\Support\Carbon}
     */
    private function periodRange(string $period): array
    {
        $now = now();

        return match ($period) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'week' => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()],
            'month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
        };
    }

    /**
     * Show the full details of one of the driver's own trips.
     */
    public function show(Request $request, Trip $trip)
    {
        // A driver may only view their own trips.
        if ($denied = $this->denyIfNotTripOwner($request, $trip)) {
            return $denied;
        }

        $trip->load([
            'checkpoints.checkpoint',
            'driver:id,name,phone',
            'bookings',
        ]);

        // Ordered route stops (from -> to).
        $route = $trip->checkpoints
            ->sortBy('id')
            ->values()
            ->map(fn ($tc, $i) => [
                'order' => $i + 1,
                'checkpoint_id' => $tc->checkpoint_id,
                'name' => $tc->checkpoint?->name,
                'governorate' => $tc->checkpoint?->governorate,
                'location' => $tc->checkpoint?->location,
                'description' => $tc->description,
            ]);

        // Actual passengers booked (excluding cancelled bookings).
        $passengersCount = (int) $trip->bookings
            ->where('status', '!=', 'cancelled')
            ->sum('seats_count');

        return response()->json([
            'trip' => [
                'id' => $trip->id,
                'status' => $trip->status,
                'type' => $trip->type,
                'from' => $route->isNotEmpty() ? $route->first()['name'] : null,
                'to' => $route->isNotEmpty() ? $route->last()['name'] : null,
                'departure_date' => $trip->departure_date,
                'arrival_date' => $trip->arrival_date,
                'seats' => [
                    'total' => $trip->total_seats,
                    'available' => $trip->available_seats,
                    'booked' => max(0, $trip->total_seats - $trip->available_seats),
                ],
                'passengers_count' => $passengersCount,
                'pricing' => [
                    'point_price' => $trip->point_price,
                    'money_price' => $trip->money_price,
                    'earned_points' => $trip->earned_points,
                ],
                'route' => $route,
                'driver' => [
                    'id' => $trip->driver?->id,
                    'name' => $trip->driver?->name,
                    'phone' => $trip->driver?->phone,
                ],
            ],
        ]);
    }
}
