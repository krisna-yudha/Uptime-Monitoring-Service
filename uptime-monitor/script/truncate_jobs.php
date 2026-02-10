<?php

require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== TRUNCATING JOBS TABLE ===\n\n";

$count = DB::table('jobs')->count();
echo "Current jobs count: {$count}\n";

echo "Truncating jobs table...\n";
DB::table('jobs')->truncate();

echo "✓ Jobs table truncated successfully!\n";
echo "New jobs count: " . DB::table('jobs')->count() . "\n";
