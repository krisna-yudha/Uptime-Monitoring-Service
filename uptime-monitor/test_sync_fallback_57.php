<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Monitor;
use Illuminate\Support\Facades\DB;

echo "\n=== Test Auto Check with Queue Fallback (Monitor 57) ===\n\n";

// Check current queue size
$queueSize = DB::table('jobs')->count();
echo "Current queue size: " . number_format($queueSize) . " jobs\n";

if ($queueSize > 15000) {
    echo "⚠️  Queue overloaded - will use SYNCHRONOUS check\n\n";
} else {
    echo "✓ Queue healthy - will use ASYNC dispatch\n\n";
}

// Create monitor
echo "Creating Monitor 57...\n";
$monitor = Monitor::create([
    'name' => 'Test Sync Fallback 57',
    'type' => 'http',
    'target' => 'https://www.google.com',
    'interval' => 1,
    'timeout' => 30,
    'enabled' => true,
    'created_by' => 1
]);

echo "✓ Monitor created: ID #{$monitor->id}\n";
echo "  Created at: {$monitor->created_at}\n\n";

// Wait for check to execute
echo "Waiting 3 seconds for check to execute...\n";
sleep(3);

// Reload and check
$monitor->refresh();

echo "\n--- Monitor Status ---\n";
echo "Last Status: {$monitor->last_status}\n";
echo "Last Checked: " . ($monitor->last_checked ?? 'never') . "\n\n";

// Check database
$checksCount = DB::table('monitor_checks')
    ->where('monitor_id', $monitor->id)
    ->count();

$logsCount = DB::table('monitoring_logs')
    ->where('monitor_id', $monitor->id)
    ->count();

echo "Monitor Checks: {$checksCount}\n";
echo "Monitoring Logs: {$logsCount}\n\n";

// Check if job in queue
$jobsInQueue = DB::table('jobs')
    ->where('payload', 'like', '%"monitorId":' . $monitor->id . '%')
    ->count();

echo "Jobs in Queue for this monitor: {$jobsInQueue}\n\n";

// Result
echo "=== Result ===\n";
if ($checksCount > 0) {
    echo "✓ SUCCESS: Monitor checked automatically!\n";
    echo "  Mode: " . ($queueSize > 15000 ? 'SYNCHRONOUS (queue overloaded)' : 'ASYNC (queue processed)') . "\n";
} else if ($jobsInQueue > 0) {
    echo "⏳ PENDING: Job in queue, waiting for worker\n";
} else {
    echo "✗ FAILED: No checks and no jobs in queue\n";
    echo "  Check laravel.log for errors\n";
}

echo "\n";
