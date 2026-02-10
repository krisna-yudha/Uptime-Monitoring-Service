<?php

require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Monitor;
use App\Models\MonitoringLog;
use Illuminate\Support\Facades\DB;

echo "=== SEARCHING FOR VALIDATION LOGS ===\n\n";

// Get all logs for event types related to validation
$validationLogs = MonitoringLog::whereIn('event_type', ['validation_start', 'validation_failed', 'service_validated'])
    ->latest('logged_at')
    ->limit(20)
    ->get();

if ($validationLogs->count() > 0) {
    echo "Recent Validation Logs:\n";
    foreach ($validationLogs as $log) {
        echo "  ID: {$log->id} | Monitor: {$log->monitor_id} | Event: {$log->event_type} | Status: " . ($log->status ?? 'NULL') . " | Time: {$log->logged_at}\n";
        if ($log->log_data) {
            echo "    Data: " . json_encode($log->log_data, JSON_PRETTY_PRINT) . "\n";
        }
    }
} else {
    echo "No validation logs found.\n";
}

echo "\n";

// Check for monitors with 'invalid' status
echo "Monitors with 'invalid' or 'unknown' status:\n";
$invalidMonitors = Monitor::whereIn('last_status', ['invalid', 'unknown'])
    ->orderBy('updated_at', 'desc')
    ->limit(10)
    ->get();

foreach ($invalidMonitors as $monitor) {
    echo "  ID: {$monitor->id} | Name: {$monitor->name} | Status: {$monitor->last_status} | Error: " . ($monitor->error_message ?? 'NULL') . "\n";
}
