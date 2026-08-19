<?php

namespace App\Observers;

use App\Models\PointAuditLog;
use App\Models\Scouring;

class ScouringObserver
{
    public function created(Scouring $scouring): void
    {
        $this->record($scouring, 'granted', 0, (int) $scouring->points);
    }

    public function updated(Scouring $scouring): void
    {
        $before = (int) $scouring->getOriginal('points');
        $after = (int) $scouring->points;

        if ($before !== $after || $scouring->wasChanged(['customer_id', 'booking_id', 'driver_checkpoint_log_id'])) {
            $this->record($scouring, 'adjusted', $before, $after);
        }
    }

    public function deleting(Scouring $scouring): void
    {
        $this->record($scouring, 'revoked', (int) $scouring->points, 0);
    }

    private function record(Scouring $scouring, string $action, int $before, int $after): void
    {
        PointAuditLog::create([
            'scouring_id' => $scouring->id,
            'actor_id' => auth()->id(),
            'customer_id' => $scouring->customer_id,
            'booking_id' => $scouring->booking_id,
            'action' => $action,
            'points_before' => $before,
            'points_after' => $after,
            'points_delta' => $after - $before,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'context' => [
                'driver_checkpoint_log_id' => $scouring->driver_checkpoint_log_id,
            ],
        ]);
    }
}
