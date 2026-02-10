<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "Before cleanup:\n";
echo "Jobs: " . DB::table('jobs')->count() . "\n";
echo "Failed jobs: " . DB::table('failed_jobs')->count() . "\n\n";

// Delete old jobs (older than 1 hour)
$deleted = DB::table('jobs')
    ->where('created_at', '<', time() - 3600)
    ->delete();
echo "Deleted old jobs (>1h): {$deleted}\n";

// Clear failed jobs
$deletedFailed = DB::table('failed_jobs')->delete();
echo "Deleted failed jobs: {$deletedFailed}\n\n";

echo "After cleanup:\n";
echo "Jobs: " . DB::table('jobs')->count() . "\n";
echo "Failed jobs: " . DB::table('failed_jobs')->count() . "\n";
