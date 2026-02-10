<?php

require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$latestFailed = DB::table('failed_jobs')->orderBy('failed_at', 'desc')->first();

if ($latestFailed) {
    echo "Latest Failed Job:\n";
    echo "Failed At: {$latestFailed->failed_at}\n";
    echo "Queue: {$latestFailed->queue}\n\n";
    
    echo "Exception:\n";
    echo $latestFailed->exception;
} else {
    echo "No failed jobs found.\n";
}
