<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Monitor;
use Illuminate\Support\Facades\DB;

echo "\n=== Final Test - Monitor Auto Check (Monitor 58) ===\n\n";

// Check current queue
$queueSize = DB::table('jobs')->count();
echo "Current queue size: " . number_format($queueSize) . " jobs\n";
echo "Expected behavior: " . ($queueSize > 15000 ? 'SYNCHRONOUS check' : 'ASYNC dispatch') . "\n\n";

// Create monitor
echo "Creating Monitor 58...\n";
$start = microtime(true);

$monitor = Monitor::create([
    'name' => 'Final Test 58',
    'type' => 'http',
    'target' => 'https://www.google.com',
    'interval' => 1,
    'timeout' => 30,
    'enabled' => true,
    'created_by' => 1
]);

$createTime = round((microtime(true) - $start) * 1000, 2);

echo "✓ Monitor created in {$createTime}ms\n";
echo "  ID: {$monitor->id}\n";
echo "  Name: {$monitor->name}\n\n";

// Immediate check (should be instant if sync, empty if async)
$monitor->refresh();
echo "--- Immediate Status (0s wait) ---\n";
echo "Last Status: {$monitor->last_status}\n";
echo "Last Checked: " . ($monitor->last_checked ?? 'null') . "\n";

$checksNow = DB::table('monitor_checks')->where('monitor_id', $monitor->id)->count();
echo "Checks: {$checksNow}\n\n";

if ($checksNow == 0) {
    echo "Waiting 3 seconds...\n";
    sleep(3);
    
    $monitor->refresh();
    $checksAfter = DB::table('monitor_checks')->where('monitor_id', $monitor->id)->count();
    $logsAfter = DB::table('monitoring_logs')->where('monitor_id', $monitor->id)->count();
    
    echo "\n--- After 3s Wait ---\n";
    echo "Last Status: {$monitor->last_status}\n";
    echo "Last Checked: " . ($monitor->last_checked ?? 'null') . "\n";
    echo "Checks: {$checksAfter}\n";
    echo "Logs: {$logsAfter}\n";
}

// Final verdict
echo "\n=== Result ===\n";
if ($checksNow > 0) {
    echo "✓ SUCCESS (SYNCHRONOUS): Monitor checked immediately during create!\n";
    echo "  This means queue was overloaded (>{$queueSize} jobs)\n";
} else if (DB::table('monitor_checks')->where('monitor_id', $monitor->id)->count() > 0) {
    echo "✓ SUCCESS (ASYNC): Monitor checked after dispatch to queue\n";
} else {
    echo "✗ FAILED: Monitor not checked\n";
    echo "  - Check storage/logs/laravel.log for errors\n";
    echo "  - Queue worker may not be processing priority queue\n";
}

echo "\n";
