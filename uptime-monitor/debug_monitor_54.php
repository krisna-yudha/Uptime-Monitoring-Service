<?php

require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Monitor;
use Illuminate\Support\Facades\DB;

echo "=== CHECKING MONITOR 54 IN DATABASE ===\n\n";

// Check if monitor exists using raw query
$rawMonitor = DB::table('monitors')->where('id', 54)->first();

if ($rawMonitor) {
    echo "Monitor 54 found in database (raw query):\n";
    echo "  ID: {$rawMonitor->id}\n";
    echo "  Name: {$rawMonitor->name}\n";
    echo "  Enabled: {$rawMonitor->enabled}\n";
    echo "  Created At: {$rawMonitor->created_at}\n";
    echo "  Updated At: {$rawMonitor->updated_at}\n";
    if (isset($rawMonitor->deleted_at)) {
        echo "  Deleted At: " . ($rawMonitor->deleted_at ?? 'NULL') . "\n";
    }
} else {
    echo "Monitor 54 NOT found in database (raw query)\n";
}

echo "\n";

// Check using Eloquent
$monitor = Monitor::find(54);
if ($monitor) {
    echo "Monitor 54 found using Eloquent:\n";
    echo "  ID: {$monitor->id}\n";
    echo "  Name: {$monitor->name}\n";
} else {
    echo "Monitor 54 NOT found using Eloquent\n";
}

echo "\n";

// Check with soft deletes
$monitorWithTrashed = Monitor::withTrashed()->find(54);
if ($monitorWithTrashed) {
    echo "Monitor 54 found with withTrashed():\n";
    echo "  ID: {$monitorWithTrashed->id}\n";
    echo "  Name: {$monitorWithTrashed->name}\n";
    echo "  Deleted At: " . ($monitorWithTrashed->deleted_at ?? 'NULL') . "\n";
} else {
    echo "Monitor 54 NOT found even with withTrashed()\n";
}

// Check queue jobs for this monitor
echo "\n=== CHECKING QUEUE JOBS ===\n\n";
$queueJobs = DB::table('jobs')
    ->where('queue', 'like', '%monitor-checks%')
    ->orderBy('id', 'desc')
    ->limit(5)
    ->get();

echo "Recent queue jobs:\n";
foreach ($queueJobs as $job) {
    $payload = json_decode($job->payload, true);
    echo "  Job ID: {$job->id} | Queue: {$job->queue} | ";
    if (isset($payload['displayName'])) {
        echo "Type: {$payload['displayName']}";
    }
    echo "\n";
}
