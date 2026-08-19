<?php

namespace App\Observers;

use App\Models\Rating;
use App\Notifications\SystemEventNotification;

class RatingNotificationObserver
{
    public function updated(Rating $rating): void
    {
        if (! $rating->wasChanged('admin_reply') || blank($rating->admin_reply)) {
            return;
        }

        $rating->customer?->notify(new SystemEventNotification(
            'rating_replied',
            'Rating reply',
            'The administration has replied to your trip rating.',
            ['rating_id' => $rating->id, 'trip_id' => $rating->trip_id, 'reply' => $rating->admin_reply]
        ));
    }
}
