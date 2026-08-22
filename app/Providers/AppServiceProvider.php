<?php

namespace App\Providers;

use App\Contracts\StripeCheckoutGateway;
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
use App\Services\StripeSdkCheckoutGateway;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(StripeCheckoutGateway::class, StripeSdkCheckoutGateway::class);
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
