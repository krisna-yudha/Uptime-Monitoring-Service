<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\NotificationChannel;
use App\Observers\NotificationChannelObserver;

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
        // Register NotificationChannel observer for auto webhook setup
        NotificationChannel::observe(NotificationChannelObserver::class);
    }
}
