<?php

require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Monitor;
use App\Jobs\ProcessMonitorCheck;

echo "=== TESTING FIX: Dispatching job for Monitor 54 ===\n\n";

$monitor = Monitor::find(54);

if (!$monitor) {
    echo "❌ Monitor 54 not found!\n";
    exit(1);
}

echo "✓ Monitor 54 found: {$monitor->name}\n";
echo "  Target: {$monitor->target}\n";
echo "  Type: {$monitor->type}\n";
echo "\n";

echo "Dispatching job to priority queue...\n";
ProcessMonitorCheck::dispatch($monitor)->onQueue('monitor-checks-priority');
echo "✓ Job dispatched successfully!\n";
echo "\n";
echo "Now run: php artisan queue:work --once --queue=monitor-checks-priority,monitor-checks\n";
