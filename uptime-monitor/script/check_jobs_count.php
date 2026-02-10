<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "Jobs count: " . DB::table('jobs')->count() . "\n";
echo "Failed jobs count: " . DB::table('failed_jobs')->count() . "\n";
