<?php

require __DIR__.'/vendor/autoload.php';

use Illuminate\Support\Facades\DB;

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$count = DB::table('jobs')->where('queue', 'notifications')->count();
echo "Jobs in notifications queue: {$count}\n";

if ($count > 0) {
    echo "\nJob details:\n";
    $jobs = DB::table('jobs')->where('queue', 'notifications')->get();
    foreach ($jobs as $job) {
        echo "  ID: {$job->id}, Attempts: {$job->attempts}, Created: {$job->created_at}\n";
    }
}
