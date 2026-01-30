<?php

require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== CHECKING QUEUE JOBS ===\n\n";

$jobs = DB::table('jobs')
    ->orderBy('created_at', 'desc')
    ->limit(10)
    ->get();

if ($jobs->count() > 0) {
    foreach ($jobs as $job) {
        $payload = json_decode($job->payload, true);
        
        echo "Job ID: {$job->id}\n";
        echo "Queue: {$job->queue}\n";
        echo "Attempts: {$job->attempts}\n";
        echo "Created: " . date('Y-m-d H:i:s', $job->created_at) . "\n";
        
        if (isset($payload['displayName'])) {
            echo "Class: {$payload['displayName']}\n";
        }
        
        // Try to extract monitor ID
        if (isset($payload['data']['command'])) {
            $command = @unserialize($payload['data']['command']);
            if ($command && isset($command->monitorId)) {
                echo "Monitor ID: {$command->monitorId}\n";
            } elseif ($command && property_exists($command, 'monitor')) {
                echo "Monitor (OLD): ID not accessible (serialized model)\n";
            }
        }
        
        echo str_repeat('-', 80) . "\n";
    }
} else {
    echo "No jobs in queue.\n";
}

echo "\n=== QUEUE SUMMARY ===\n";
echo "Total jobs in queue: " . DB::table('jobs')->count() . "\n";
echo "Jobs in monitor-checks-priority: " . DB::table('jobs')->where('queue', 'monitor-checks-priority')->count() . "\n";
echo "Jobs in monitor-checks: " . DB::table('jobs')->where('queue', 'monitor-checks')->count() . "\n";
