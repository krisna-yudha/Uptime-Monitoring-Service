<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Monitor;
use Illuminate\Support\Facades\DB;

echo "\n=== Check Monitor 56 Status ===\n\n";

$monitor = Monitor::find(56);

if (!$monitor) {
    echo "✗ Monitor 56 not found\n";
    exit(1);
}

echo "Monitor: {$monitor->name} (ID #{$monitor->id})\n";
echo "Status: {$monitor->last_status}\n";
echo "Last Checked: " . ($monitor->last_checked ?? 'never') . "\n";
echo "Next Check: {$monitor->next_check_at}\n\n";

// Check for monitor checks
$checksCount = DB::table('monitor_checks')
    ->where('monitor_id', $monitor->id)
    ->count();

echo "Monitor Checks: {$checksCount}\n";

if ($checksCount > 0) {
    $latestCheck = DB::table('monitor_checks')
        ->where('monitor_id', $monitor->id)
        ->orderBy('checked_at', 'desc')
        ->first();
    
    echo "\nLatest Check:\n";
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
        ->get();
    
    echo "All Logs:\n";
    foreach ($logs as $log) {
        echo "  - [{$log->event_type}] {$log->message} ({$log->created_at})\n";
    }
}

echo "\n=== Result ===\n";
if ($checksCount > 0) {
    echo "✓ Queue worker BERHASIL memproses monitor baru!\n";
    echo "  Checks: {$checksCount}, Logs: {$logsCount}\n";
} else {
    echo "✗ Belum ada checks (queue worker mungkin sedang backlog)\n";
}

echo "\n";
