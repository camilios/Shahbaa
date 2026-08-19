<?php

namespace App\Http\Controllers;

use App\Models\Trip;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminReportsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        [$period, $start, $end] = $this->period($request);
        $trips = $this->trips($start, $end);

        return response()->json($this->report($period, $start, $end, $trips));
    }

    public function export(Request $request): StreamedResponse
    {
        [$period, $start, $end] = $this->period($request);
        $report = $this->report($period, $start, $end, $this->trips($start, $end));

        return response()->streamDownload(function () use ($report) {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Shahbaa report', $report['period']['label']]);
            fputcsv($output, ['From', $report['period']['from']]);
            fputcsv($output, ['To', $report['period']['to']]);
            fputcsv($output, []);
            fputcsv($output, ['Total revenue (SYP)', $report['summary']['total_revenue']]);
            fputcsv($output, ['Total trips', $report['summary']['total_trips']]);
            fputcsv($output, ['Total passengers', $report['summary']['total_passengers']]);
            fputcsv($output, ['Average occupancy (%)', $report['summary']['average_occupancy']]);
            fputcsv($output, []);
            fputcsv($output, ['Trip type', 'Trips', 'Revenue (SYP)', 'Average occupancy (%)']);

            foreach ($report['trip_type_performance'] as $type) {
                fputcsv($output, [
                    $type['type'],
                    $type['trips'],
                    $type['revenue'],
                    $type['occupancy'],
                ]);
            }

            fclose($output);
        }, "shahbaa-report-{$period}.csv", [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function period(Request $request): array
    {
        $validated = $request->validate([
            'period' => ['nullable', 'in:last_7_days,last_30_days,last_3_months,last_year'],
        ]);
        $period = $validated['period'] ?? 'last_30_days';
        $end = CarbonImmutable::now()->endOfDay();
        $start = match ($period) {
            'last_7_days' => $end->subDays(6)->startOfDay(),
            'last_30_days' => $end->subDays(29)->startOfDay(),
            'last_3_months' => $end->subMonths(3)->addDay()->startOfDay(),
            'last_year' => $end->subYear()->addDay()->startOfDay(),
        };

        return [$period, $start, $end];
    }

    private function trips(CarbonImmutable $start, CarbonImmutable $end): Collection
    {
        return Trip::query()
            ->whereBetween('departure_date', [$start, $end])
            ->with(['bookings' => fn ($query) => $query->where('status', '!=', 'cancelled')])
            ->orderBy('departure_date')
            ->get();
    }

    private function report(
        string $period,
        CarbonImmutable $start,
        CarbonImmutable $end,
        Collection $trips
    ): array {
        $tripMetrics = $trips->map(function (Trip $trip) {
            $passengers = (int) $trip->bookings->sum('seats_count');
            $revenue = $passengers * (float) $trip->money_price;
            $occupancy = $trip->total_seats > 0
                ? min(100, ($passengers / $trip->total_seats) * 100)
                : 0;

            return [
                'trip' => $trip,
                'passengers' => $passengers,
                'revenue' => $revenue,
                'occupancy' => $occupancy,
            ];
        });

        $bucketFormat = in_array($period, ['last_7_days', 'last_30_days'], true)
            ? 'Y-m-d'
            : 'Y-m';

        $grouped = $tripMetrics->groupBy(
            fn (array $metric) => $metric['trip']->departure_date->format($bucketFormat)
        );

        $revenueTrend = $grouped->map(fn (Collection $items, string $bucket) => [
            'period' => $bucket,
            'revenue' => round((float) $items->sum('revenue'), 2),
        ])->values();

        $tripsOverview = $grouped->map(fn (Collection $items, string $bucket) => [
            'period' => $bucket,
            'trips' => $items->count(),
        ])->values();

        $typePerformance = $tripMetrics
            ->groupBy(fn (array $metric) => $metric['trip']->type ?: 'unknown')
            ->map(fn (Collection $items, string $type) => [
                'type' => $type,
                'trips' => $items->count(),
                'revenue' => round((float) $items->sum('revenue'), 2),
                'occupancy' => round((float) $items->avg('occupancy'), 1),
            ])
            ->values();

        return [
            'period' => [
                'key' => $period,
                'label' => str_replace('_', ' ', ucfirst($period)),
                'from' => $start->toDateString(),
                'to' => $end->toDateString(),
            ],
            'summary' => [
                'total_revenue' => round((float) $tripMetrics->sum('revenue'), 2),
                'total_trips' => $trips->count(),
                'total_passengers' => (int) $tripMetrics->sum('passengers'),
                'average_occupancy' => round((float) ($tripMetrics->avg('occupancy') ?? 0), 1),
            ],
            'revenue_trend' => $revenueTrend,
            'trips_overview' => $tripsOverview,
            'trip_type_performance' => $typePerformance,
        ];
    }
}
