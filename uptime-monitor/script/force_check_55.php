<?php

require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Monitor;
use App\Jobs\ProcessMonitorCheck;
use Illuminate\Support\Facades\Log;

echo "=== FORCING MANUAL CHECK FOR MONITOR 55 ===\n\n";

$monitor = Monitor::find(55);

if (!$monitor) {
    echo "❌ Monitor not found\n";
    exit(1);
}

echo "Monitor: {$monitor->name}\n";
echo "Target: {$monitor->target}\n";
echo "Type: {$monitor->type}\n\n";

echo "Running job synchronously (not queued)...\n";

try {
    $job = new ProcessMonitorCheck($monitor);
    $job->handle();
    
    echo "✓ Job executed successfully\n\n";
    
    // Check results
    $monitor->refresh();
    echo "After check:\n";
    echo "  Last Status: {$monitor->last_status}\n";
    echo "  Last Checked: {$monitor->last_checked_at}\n";
    
} catch (\Exception $e) {
    echo "❌ Job failed: {$e->getMessage()}\n";
    echo "Stack trace:\n";
    echo $e->getTraceAsString();
}
