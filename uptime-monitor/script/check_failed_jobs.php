<?php

require __DIR__.'/vendor/autoload.php';

use Illuminate\Support\Facades\DB;

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Failed Jobs Details ===\n\n";

$failedJob = DB::table('failed_jobs')
    ->orderBy('failed_at', 'desc')
    ->first();

if (!$failedJob) {
    echo "No failed jobs found.\n";
    exit(0);
}

echo "UUID: {$failedJob->uuid}\n";
echo "Queue: {$failedJob->queue}\n";
echo "Failed At: {$failedJob->failed_at}\n\n";

echo "=== Exception ===\n";
echo $failedJob->exception . "\n";
