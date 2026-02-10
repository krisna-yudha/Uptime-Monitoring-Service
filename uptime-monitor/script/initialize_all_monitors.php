<?php

require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Monitor;
use App\Jobs\ProcessMonitorCheck;

echo "=== INITIALIZING ALL ENABLED MONITORS ===\n\n";

$monitors = Monitor::where('enabled', true)->get();

echo "Found {$monitors->count()} enabled monitors\n";
echo "Dispatching initial checks...\n\n";

foreach ($monitors as $monitor) {
    try {
        ProcessMonitorCheck::dispatch($monitor)->onQueue('monitor-checks-priority');
        echo "✓ {$monitor->id}: {$monitor->name} - job dispatched\n";
    } catch (\Exception $e) {
        echo "✗ {$monitor->id}: {$monitor->name} - ERROR: {$e->getMessage()}\n";
    }
}

echo "\n✓ All monitors initialized!\n";
echo "\nNext step: Make sure queue worker is running:\n";
echo "  php artisan queue:work --queue=monitor-checks-priority,monitor-checks --daemon\n";
