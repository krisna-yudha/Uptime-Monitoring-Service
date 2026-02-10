<?php

require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== CHECKING FAILED JOBS ===\n\n";

$failedJobs = DB::table('failed_jobs')
    ->orderBy('failed_at', 'desc')
    ->limit(5)
    ->get();

if ($failedJobs->count() > 0) {
    foreach ($failedJobs as $job) {
        echo "Failed Job ID: {$job->id}\n";
        echo "UUID: {$job->uuid}\n";
        echo "Connection: {$job->connection}\n";
        echo "Queue: {$job->queue}\n";
        echo "Failed At: {$job->failed_at}\n";
        
        // Decode payload to see monitor ID
        $payload = json_decode($job->payload, true);
        if (isset($payload['displayName'])) {
            echo "Job: {$payload['displayName']}\n";
        }
        
        // Try to extract monitor ID from the payload
        if (isset($payload['data']['command'])) {
            $command = unserialize($payload['data']['command']);
            if (isset($command->monitor)) {
                echo "Monitor ID: {$command->monitor->id}\n";
                echo "Monitor Name: {$command->monitor->name}\n";
            }
        }
        
        echo "Exception:\n";
        echo substr($job->exception, 0, 500) . "...\n";
        echo "\n" . str_repeat('-', 80) . "\n\n";
    }
} else {
    echo "No failed jobs found.\n";
}
