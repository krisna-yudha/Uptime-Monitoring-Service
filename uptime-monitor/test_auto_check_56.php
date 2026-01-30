<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Monitor;
use Illuminate\Support\Facades\DB;

echo "\n=== Test Auto Check for New Monitor ===\n\n";

// Create a test monitor (Monitor 56)
$monitor = Monitor::create([
    'name' => 'Test Auto Check 56',
    'type' => 'http',
    'target' => 'https://www.google.com',
    'interval' => 1,
    'timeout' => 30,
    'enabled' => true,
    'created_by' => 1
]);

echo "✓ Monitor created: ID #{$monitor->id}\n";
echo "  Name: {$monitor->name}\n";
echo "  Type: {$monitor->type}\n";
echo "  Target: {$monitor->target}\n";
echo "  Created at: {$monitor->created_at}\n\n";

// Wait a moment for the check to execute
echo "Waiting 3 seconds for auto-check to execute...\n";
sleep(3);

// Reload monitor
$monitor->refresh();

echo "\n--- Monitor Status ---\n";
echo "Last Status: " . ($monitor->last_status ?? 'null') . "\n";
echo "Last Checked: " . ($monitor->last_checked ?? 'null') . "\n";
echo "Next Check: " . ($monitor->next_check_at ?? 'null') . "\n";

// Check for monitor checks
$checksCount = DB::table('monitor_checks')
    ->where('monitor_id', $monitor->id)
    ->count();

echo "\n--- Database Records ---\n";
echo "Monitor Checks: {$checksCount}\n";

if ($checksCount > 0) {
    $latestCheck = DB::table('monitor_checks')
        ->where('monitor_id', $monitor->id)
        ->orderBy('checked_at', 'desc')
        ->first();
    
    echo "Latest Check:\n";
    echo "  Status: {$latestCheck->status}\n";
    echo "  Response Time: {$latestCheck->response_time}ms\n";
    echo "  Checked At: {$latestCheck->checked_at}\n";
}

// Check for monitoring logs
$logsCount = DB::table('monitoring_logs')
    ->where('monitor_id', $monitor->id)
    ->count();

echo "\nMonitoring Logs: {$logsCount}\n";

if ($logsCount > 0) {
    $logs = DB::table('monitoring_logs')
        ->where('monitor_id', $monitor->id)
        ->orderBy('created_at', 'desc')
        ->limit(3)
        ->get();
    
    echo "Recent Logs:\n";
    foreach ($logs as $log) {
        echo "  - [{$log->event_type}] {$log->message}\n";
    }
}

// Check pending jobs
$pendingJobs = DB::table('jobs')
    ->where('queue', 'like', '%monitor-checks%')
    ->whereNull('reserved_at')
    ->count();

echo "\nPending Queue Jobs: {$pendingJobs}\n";

echo "\n=== Result ===\n";
if ($checksCount > 0 && $logsCount > 0) {
    echo "✓ SUCCESS: Auto-check executed automatically!\n";
    echo "  Monitor has {$checksCount} check(s) and {$logsCount} log(s)\n";
} else {
    echo "✗ FAILED: Auto-check did not execute\n";
    echo "  This means either:\n";
    echo "  1. Queue worker is not running (need to start queue:work)\n";
    echo "  2. Synchronous fallback failed\n";
    echo "  \n";
    echo "  Solution: Start queue worker or scheduler\n";
}

echo "\n";
