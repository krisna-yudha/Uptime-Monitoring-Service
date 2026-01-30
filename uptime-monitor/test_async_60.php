<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Monitor;
use Illuminate\Support\Facades\DB;

echo "\n=== Test ASYNC Mode (Clean Queue) - Monitor 60 ===\n\n";

// Check current queue
$queueSize = DB::table('jobs')->count();
echo "Current queue size: " . number_format($queueSize) . " jobs\n";
echo "Expected behavior: " . ($queueSize > 15000 ? 'SYNCHRONOUS' : 'ASYNC dispatch') . "\n\n";

// Create monitor
echo "Creating Monitor 60...\n";
$monitor = Monitor::create([
    'name' => 'Test Async 60',
    'type' => 'http',
    'target' => 'https://www.google.com',
    'interval' => 1,
    'timeout' => 30,
    'enabled' => true,
    'created_by' => 1
]);

echo "✓ Monitor created: ID #{$monitor->id}\n\n";

// Immediate check
$monitor->refresh();
$checksNow = DB::table('monitor_checks')->where('monitor_id', $monitor->id)->count();
$jobsInQueue = DB::table('jobs')
    ->where('payload', 'like', '%"monitorId":' . $monitor->id . '%')
    ->count();

echo "--- Immediate Status ---\n";
echo "Last Status: {$monitor->last_status}\n";
echo "Checks: {$checksNow}\n";
echo "Jobs in Queue: {$jobsInQueue}\n\n";

if ($checksNow == 0 && $jobsInQueue > 0) {
    echo "Waiting for queue worker to process...\n";
    sleep(5);
    
    $monitor->refresh();
    $checksAfter = DB::table('monitor_checks')->where('monitor_id', $monitor->id)->count();
    $logsAfter = DB::table('monitoring_logs')->where('monitor_id', $monitor->id)->count();
    
    echo "\n--- After 5s Wait ---\n";
    echo "Last Status: {$monitor->last_status}\n";
    echo "Checks: {$checksAfter}\n";
    echo "Logs: {$logsAfter}\n";
}

echo "\n=== Result ===\n";
$finalChecks = DB::table('monitor_checks')->where('monitor_id', $monitor->id)->count();

if ($finalChecks > 0) {
    if ($checksNow > 0) {
        echo "✓ SUCCESS (SYNC): Monitor checked immediately\n";
    } else {
        echo "✓ SUCCESS (ASYNC): Monitor checked by queue worker\n";
    }
} else {
    echo "⏳ PENDING: Job dispatched but not processed yet\n";
    echo "  Queue worker is processing other monitors\n";
}

echo "\n";
