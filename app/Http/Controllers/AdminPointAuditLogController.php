<?php

namespace App\Http\Controllers;

use App\Models\PointAuditLog;
use Illuminate\Http\Request;

class AdminPointAuditLogController extends Controller
{
    public function __invoke(Request $request)
    {
        $validated = $request->validate([
            'action' => ['nullable', 'in:granted,adjusted,revoked'],
            'period' => ['nullable', 'in:last_7_days,last_30_days,last_3_months'],
        ]);

        $query = PointAuditLog::query()
            ->with([
                'actor:id,name,email',
                'customer:id,name,email',
                'booking:id,trip_id',
            ])
            ->latest('id');

        if (isset($validated['action'])) {
            $query->where('action', $validated['action']);
        }

        $period = $validated['period'] ?? 'last_30_days';
        $from = match ($period) {
            'last_7_days' => now()->subDays(6)->startOfDay(),
            'last_30_days' => now()->subDays(29)->startOfDay(),
            'last_3_months' => now()->subMonths(3)->startOfDay(),
        };
        $query->where('created_at', '>=', $from);

        return response()->json([
            'summary' => [
                'total' => PointAuditLog::count(),
                'today' => PointAuditLog::whereDate('created_at', today())->count(),
                'this_week' => PointAuditLog::where('created_at', '>=', now()->startOfWeek())->count(),
                'this_month' => PointAuditLog::where('created_at', '>=', now()->startOfMonth())->count(),
            ],
            'logs' => $query->paginate(20)->withQueryString(),
        ]);
    }
}
