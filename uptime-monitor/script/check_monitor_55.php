<?php

require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Monitor;
use App\Models\MonitorCheck;
use App\Models\MonitoringLog;
use Illuminate\Support\Facades\DB;

echo "=== CHECKING MONITOR 55 ===\n\n";

$monitor = Monitor::find(55);

if (!$monitor) {
    echo "❌ Monitor 55 not found!\n";
    exit(1);
}

echo "Monitor 55 Details:\n";
echo "  Name: {$monitor->name}\n";
echo "  Type: {$monitor->type}\n";
echo "  Target: {$monitor->target}\n";
echo "  Enabled: " . ($monitor->enabled ? 'YES' : 'NO') . "\n";
echo "  Last Status: {$monitor->last_status}\n";
echo "  Last Checked: " . ($monitor->last_checked_at ?? 'NULL') . "\n";
echo "  Created: {$monitor->created_at}\n";
echo "\n";

// Check for any checks
$checksCount = MonitorCheck::where('monitor_id', 55)->count();
echo "Total Checks: {$checksCount}\n";

if ($checksCount > 0) {
    $latestCheck = MonitorCheck::where('monitor_id', 55)->latest('checked_at')->first();
    echo "  Latest: {$latestCheck->status} at {$latestCheck->checked_at}\n";
} else {
    echo "  ⚠️ NO CHECKS FOUND\n";
}

// Check for any logs
$logsCount = MonitoringLog::where('monitor_id', 55)->count();
echo "\nTotal Logs: {$logsCount}\n";

if ($logsCount > 0) {
    $latestLog = MonitoringLog::where('monitor_id', 55)->latest('logged_at')->first();
    echo "  Latest: {$latestLog->event_type} at {$latestLog->logged_at}\n";
} else {
    echo "  ⚠️ NO LOGS FOUND\n";
}

// Check pending jobs for this monitor
echo "\nChecking pending jobs...\n";
$pendingJobs = DB::table('jobs')
    ->where(function($query) {
        $query->where('queue', 'monitor-checks')
              ->orWhere('queue', 'monitor-checks-priority');
    })
    ->where('payload', 'LIKE', '%"monitorId":55%')
    ->count();

echo "Pending jobs for monitor 55: {$pendingJobs}\n";

if ($pendingJobs === 0) {
    echo "\n🔧 DISPATCHING JOB FOR MONITOR 55...\n";
    \App\Jobs\ProcessMonitorCheck::dispatch($monitor)->onQueue('monitor-checks-priority');
    echo "✓ Job dispatched to priority queue\n";
    echo "\nNow run: php artisan queue:work --once --queue=monitor-checks-priority,monitor-checks\n";
}
