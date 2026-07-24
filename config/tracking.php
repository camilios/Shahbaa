<?php

return [
    'service_secret' => env('TRACKING_SERVICE_SECRET'),

    // The current project uses "active" for a trip that is in progress.
    'trackable_trip_statuses' => array_values(array_filter(array_map(
        'trim',
        explode(',', env('TRACKING_TRACKABLE_TRIP_STATUSES', 'active'))
    ))),
];
