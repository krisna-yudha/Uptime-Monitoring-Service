<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\NotificationChannel;
use App\Models\Monitor;
use App\Observers\NotificationChannelObserver;
use App\Observers\MonitorObserver;

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
        // Register observers
        NotificationChannel::observe(NotificationChannelObserver::class);
        Monitor::observe(MonitorObserver::class);
    }
}
