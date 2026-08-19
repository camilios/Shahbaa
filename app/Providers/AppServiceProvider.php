<?php

namespace App\Providers;

use App\Models\Booking;
use App\Models\Complaint;
use App\Models\Rating;
use App\Models\Scouring;
use App\Models\Trip;
use App\Observers\BookingNotificationObserver;
use App\Observers\ComplaintNotificationObserver;
use App\Observers\RatingNotificationObserver;
use App\Observers\ScouringObserver;
use App\Observers\TripNotificationObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Scouring::observe(ScouringObserver::class);
        Booking::observe(BookingNotificationObserver::class);
        Trip::observe(TripNotificationObserver::class);
        Complaint::observe(ComplaintNotificationObserver::class);
        Rating::observe(RatingNotificationObserver::class);
    }
}
