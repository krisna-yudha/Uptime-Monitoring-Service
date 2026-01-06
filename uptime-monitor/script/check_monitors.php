<?php

require 'vendor/autoload.php';

$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Available Monitors ===\n";
$monitors = DB::table('monitors')->get();

if ($monitors->count() > 0) {
    foreach ($monitors as $monitor) {
        echo "ID: {$monitor->id} - Name: {$monitor->name}";
        echo " - Status: {$monitor->last_status}\n";
        echo "Target: {$monitor->target}\n";
        echo "Type: {$monitor->type}\n";
        echo "Enabled: " . ($monitor->enabled ? 'Yes' : 'No') . "\n";
        echo "Pause until: " . ($monitor->pause_until ?? 'null') . "\n";
        echo "Next check at: " . ($monitor->next_check_at ?? 'null') . "\n";
        echo "Last checked: " . ($monitor->last_checked_at ?? 'Never') . "\n";
        echo "Error: " . ($monitor->error_message ?? 'None') . "\n";
        echo "---\n";
    }
} else {
    echo "No monitors found in database.\n";
}

echo "\n=== Jobs Table ===\n";
$jobs = DB::table('jobs')->count();
echo "Active jobs: {$jobs}\n";

echo "\n=== Failed Jobs Count ===\n";
$failedJobs = DB::table('failed_jobs')->count();
echo "Failed jobs: {$failedJobs}\n";