<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Monitor;
use Illuminate\Support\Facades\DB;

$monitor = Monitor::find(49);

if (!$monitor) {
    echo "Monitor 49 not found\n";
    exit(1);
}

echo "=== Monitor 49 Status ===\n";
echo "Name: {$monitor->name}\n";
echo "Target: {$monitor->target}\n";
echo "Type: {$monitor->type}\n";
echo "Enabled: " . ($monitor->enabled ? 'YES' : 'NO') . "\n";
echo "Pause Until: " . ($monitor->pause_until ?? 'null') . "\n";
echo "Last Checked: {$monitor->last_checked_at}\n";
echo "Interval: {$monitor->interval_seconds}s\n";
echo "\n";

// Check for jobs in queue
$jobs = DB::table('jobs')->get();
echo "=== All Jobs in Queue: {$jobs->count()} ===\n";

foreach ($jobs as $job) {
    $payload = json_decode($job->payload, true);
    $displayName = $payload['displayName'] ?? 'Unknown';
    $monitorData = null;
    
    // Try to extract monitor info from payload
    if (isset($payload['data']['command'])) {
        $command = unserialize($payload['data']['command']);
        if (isset($command->monitor)) {
            $monitorData = [
                'id' => $command->monitor->id,
                'name' => $command->monitor->name,
                'target' => $command->monitor->target
            ];
        }
    }
    
    echo "\nJob ID: {$job->id}\n";
    echo "Queue: {$job->queue}\n";
    echo "Display Name: {$displayName}\n";
    echo "Available At: " . date('Y-m-d H:i:s', $job->available_at) . "\n";
    
    if ($monitorData) {
        echo "Monitor: #{$monitorData['id']} - {$monitorData['name']} ({$monitorData['target']})\n";
    }
}

echo "\n=== Manually Dispatching Check ===\n";
try {
    \App\Jobs\ProcessMonitorCheck::dispatch($monitor)
        ->onQueue('monitor-checks-priority');
    echo "✅ Job dispatched successfully!\n";
    echo "\nCheck queue again:\n";
    echo "SELECT COUNT(*) FROM jobs WHERE queue = 'monitor-checks-priority';\n";
} catch (\Exception $e) {
    echo "❌ Failed to dispatch: {$e->getMessage()}\n";
}
