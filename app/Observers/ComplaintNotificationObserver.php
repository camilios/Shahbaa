<?php

namespace App\Observers;

use App\Models\Complaint;
use App\Notifications\SystemEventNotification;

class ComplaintNotificationObserver
{
    public function updated(Complaint $complaint): void
    {
        if (! $complaint->wasChanged('admin_reply') || blank($complaint->admin_reply)) {
            return;
        }

        $complaint->customer?->notify(new SystemEventNotification(
            'complaint_replied',
            'Complaint reply',
            'The administration has replied to your complaint.',
            ['complaint_id' => $complaint->id, 'reply' => $complaint->admin_reply]
        ));
    }
}
