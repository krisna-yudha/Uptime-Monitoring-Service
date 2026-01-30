<?php

require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Monitor;
use App\Models\MonitorCheck;
use App\Models\MonitoringLog;

echo "=== CHECKING RECENT MONITORING DATA ===\n\n";

// Get latest monitor
$latestMonitor = Monitor::latest()->first();
if ($latestMonitor) {
    echo "Latest Monitor:\n";
    echo "  ID: {$latestMonitor->id}\n";
    echo "  Name: {$latestMonitor->name}\n";
    echo "  Type: {$latestMonitor->type}\n";
    echo "  Target: {$latestMonitor->target}\n";
    echo "  Last Status: {$latestMonitor->last_status}\n";
    echo "  Created: {$latestMonitor->created_at}\n";
    echo "\n";
}

// Get latest monitor checks
echo "Latest 5 Monitor Checks:\n";
$checks = MonitorCheck::with('monitor')->latest()->limit(5)->get();
foreach ($checks as $check) {
    echo "  ID: {$check->id} | Monitor: {$check->monitor->name} ({$check->monitor_id}) | Status: {$check->status} | Time: {$check->checked_at}\n";
}
echo "\n";

// Get latest monitoring logs
echo "Latest 10 Monitoring Logs:\n";
$logs = MonitoringLog::with('monitor')->latest('logged_at')->limit(10)->get();
foreach ($logs as $log) {
    $monitorName = $log->monitor ? $log->monitor->name : 'DELETED';
    $status = $log->status ?? 'NULL';
    echo "  ID: {$log->id} | Monitor: {$monitorName} ({$log->monitor_id}) | Event: {$log->event_type} | Status: {$status} | Time: {$log->logged_at}\n";
}
echo "\n";

// Check if latest monitor has any logs
if ($latestMonitor) {
    echo "Logs for Latest Monitor (ID: {$latestMonitor->id}):\n";
    $monitorLogs = MonitoringLog::where('monitor_id', $latestMonitor->id)
        ->latest('logged_at')
        ->limit(5)
        ->get();
    
    if ($monitorLogs->count() > 0) {
        foreach ($monitorLogs as $log) {
            echo "  Event: {$log->event_type} | Status: " . ($log->status ?? 'NULL') . " | Time: {$log->logged_at}\n";
        }
    } else {
        echo "  ⚠️ NO LOGS FOUND for this monitor!\n";
    }
    echo "\n";
    
    echo "Checks for Latest Monitor (ID: {$latestMonitor->id}):\n";
    $monitorChecks = MonitorCheck::where('monitor_id', $latestMonitor->id)
        ->latest('checked_at')
        ->limit(5)
        ->get();
    
    if ($monitorChecks->count() > 0) {
        foreach ($monitorChecks as $check) {
            echo "  Status: {$check->status} | Latency: " . ($check->latency_ms ?? 'NULL') . "ms | Time: {$check->checked_at}\n";
        }
    } else {
        echo "  ⚠️ NO CHECKS FOUND for this monitor!\n";
    }
}
