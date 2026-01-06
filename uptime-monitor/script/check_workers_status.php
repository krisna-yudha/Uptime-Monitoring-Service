<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== WORKER STATUS CHECK ===\n\n";

// Check pending jobs in queue
$pendingJobs = DB::table('jobs')->count();
echo "📋 Pending Jobs: {$pendingJobs}\n";

// Check recent jobs
$recentJobs = DB::table('jobs')
    ->orderBy('id', 'desc')
    ->limit(5)
    ->get(['id', 'queue', 'payload']);

if ($recentJobs->count() > 0) {
    echo "\n📝 Recent Jobs in Queue:\n";
    foreach ($recentJobs as $job) {
        $payload = json_decode($job->payload, true);
        $displayName = $payload['displayName'] ?? 'Unknown';
        echo "  - ID {$job->id}: {$displayName} (Queue: {$job->queue})\n";
    }
}

// Check monitors
$monitors = DB::table('monitors')
    ->where('enabled', true)
    ->get(['id', 'name', 'type', 'next_check_at']);

echo "\n📊 Active Monitors: {$monitors->count()}\n";
foreach ($monitors as $monitor) {
    $nextCheck = $monitor->next_check_at ? date('H:i:s', strtotime($monitor->next_check_at)) : 'Not scheduled';
    echo "  - {$monitor->name} ({$monitor->type}) - Next: {$nextCheck}\n";
}

// Check recent incidents
$recentIncidents = DB::table('incidents')
    ->orderBy('id', 'desc')
    ->limit(3)
    ->get(['id', 'monitor_id', 'status', 'started_at']);

echo "\n🚨 Recent Incidents: {$recentIncidents->count()}\n";
foreach ($recentIncidents as $incident) {
    $monitor = DB::table('monitors')->where('id', $incident->monitor_id)->value('name');
    $time = date('H:i:s', strtotime($incident->started_at));
    echo "  - ID {$incident->id}: {$monitor} ({$incident->status}) at {$time}\n";
}

echo "\n✅ Check complete!\n";
