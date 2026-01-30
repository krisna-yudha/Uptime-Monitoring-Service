<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Monitor;
use App\Observers\MonitorObserver;
use Illuminate\Support\Facades\DB;

echo "\n=== Test Observer Direct Call ===\n\n";

// Check queue
$queueBefore = DB::table('jobs')->count();
echo "Queue before: {$queueBefore} jobs\n\n";

// Create monitor
echo "Creating Monitor 61 (will trigger observer)...\n";
$monitor = Monitor::create([
    'name' => 'Test Observer Direct 61',
    'type' => 'http',
    'target' => 'https://www.google.com',
    'interval' => 1,
    'timeout' => 30,
    'enabled' => true,
    'created_by' => 1
]);

echo "✓ Monitor created: ID #{$monitor->id}\n\n";

// Wait a moment
sleep(2);

// Check results
$monitor->refresh();
$checks = DB::table('monitor_checks')->where('monitor_id', $monitor->id)->count();
$logs = DB::table('monitoring_logs')->where('monitor_id', $monitor->id)->count();
$jobs = DB::table('jobs')
    ->where('payload', 'like', '%"monitorId":' . $monitor->id . '%')
    ->count();
$queueAfter = DB::table('jobs')->count();

echo "Results:\n";
echo "  Monitor Status: {$monitor->last_status}\n";
echo "  Checks: {$checks}\n";
echo "  Logs: {$logs}\n";
echo "  Jobs in Queue: {$jobs}\n";
echo "  Queue: {$queueBefore} → {$queueAfter}\n\n";

// Check if observer is registered
echo "=== Observer Registration Check ===\n";
echo "Monitor model has observers: " . (count(app()->make('events')->getListeners('eloquent.created: App\Models\Monitor')) > 0 ? 'YES' : 'NO') . "\n\n";

// Try manual observer call
if ($checks == 0 && $jobs == 0) {
    echo "⚠️  Observer tidak auto-trigger, mencoba manual...\n";
    $observer = new MonitorObserver();
    $observer->created($monitor);
    
    sleep(2);
    $monitor->refresh();
    $checks2 = DB::table('monitor_checks')->where('monitor_id', $monitor->id)->count();
    $jobs2 = DB::table('jobs')
        ->where('payload', 'like', '%"monitorId":' . $monitor->id . '%')
        ->count();
    
    echo "After manual observer call:\n";
    echo "  Status: {$monitor->last_status}\n";
    echo "  Checks: {$checks2}\n";
    echo "  Jobs: {$jobs2}\n";
}

echo "\n";
