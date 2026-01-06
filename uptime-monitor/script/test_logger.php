<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Log;

echo "=== Testing Logger ===\n\n";

// Test write to log
Log::info("TEST LOG MESSAGE - This should appear in laravel.log");
Log::warning("TEST WARNING - Channel disabled test");
Log::error("TEST ERROR - Just for testing");

echo "Wrote test logs to storage/logs/laravel.log\n";
echo "Check with: Get-Content storage\\logs\\laravel.log -Tail 5\n";
