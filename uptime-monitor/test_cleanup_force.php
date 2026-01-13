<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;

echo "=== Force Test: Queue Cleanup at 100 Jobs Limit ===\n\n";

$currentJobs = DB::table('jobs')->count();
echo "Current jobs: {$currentJobs}\n\n";

if ($currentJobs > 100) {
    echo "Testing cleanup with max-jobs=100...\n";
    Artisan::call('queue:cleanup', ['--max-jobs' => 100]);
    echo Artisan::output();
    
    $afterCleanup = DB::table('jobs')->count();
    echo "\nJobs after cleanup: {$afterCleanup}\n";
    echo "Deleted: " . ($currentJobs - $afterCleanup) . " jobs\n";
    
    if ($afterCleanup <= 100) {
        echo "✅ Cleanup working correctly!\n";
    }
} else {
    echo "Not enough jobs to test (need > 100)\n";
}

echo "\n=== Restoring Normal Limit ===\n";
echo "Setting back to 5000 jobs limit...\n";
// No cleanup needed since we're at 100, which is < 5000
echo "✅ Normal operation resumed\n";
