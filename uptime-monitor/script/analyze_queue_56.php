<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "\n=== Queue Analysis for Monitor 56 ===\n\n";

// Total jobs
$totalJobs = DB::table('jobs')->count();
echo "Total Jobs in Queue: " . number_format($totalJobs) . "\n";

// Jobs for Monitor 56
$payload56 = '%"monitorId":56%';
$jobs56 = DB::table('jobs')
    ->where('payload', 'like', $payload56)
    ->count();

echo "Jobs for Monitor 56: {$jobs56}\n\n";

// Check if any Monitor 56 job in priority queue
$priority56 = DB::table('jobs')
    ->where('queue', 'monitor-checks-priority')
    ->where('payload', 'like', $payload56)
    ->count();

echo "Monitor 56 in Priority Queue: {$priority56}\n";

// Show sample job
if ($jobs56 > 0) {
    $sampleJob = DB::table('jobs')
        ->where('payload', 'like', $payload56)
        ->first();
    
    echo "\nSample Job for Monitor 56:\n";
    echo "  Queue: {$sampleJob->queue}\n";
    echo "  Attempts: {$sampleJob->attempts}\n";
    echo "  Reserved At: " . ($sampleJob->reserved_at ? date('Y-m-d H:i:s', $sampleJob->reserved_at) : 'Not reserved') . "\n";
    echo "  Created At: " . date('Y-m-d H:i:s', $sampleJob->created_at) . "\n";
}

// Queue breakdown
echo "\n=== Queue Breakdown ===\n";
$queueStats = DB::table('jobs')
    ->selectRaw('queue, COUNT(*) as count')
    ->groupBy('queue')
    ->get();

foreach ($queueStats as $stat) {
    echo "{$stat->queue}: " . number_format($stat->count) . " jobs\n";
}

// Oldest job
$oldestJob = DB::table('jobs')
    ->orderBy('created_at', 'asc')
    ->first();

if ($oldestJob) {
    $age = now()->timestamp - $oldestJob->created_at;
    $hours = floor($age / 3600);
    $minutes = floor(($age % 3600) / 60);
    
    echo "\nOldest Job:\n";
    echo "  Age: {$hours} hours {$minutes} minutes\n";
    echo "  Created: " . date('Y-m-d H:i:s', $oldestJob->created_at) . "\n";
    echo "  Queue: {$oldestJob->queue}\n";
}

echo "\n=== Recommendation ===\n";
if ($totalJobs > 10000) {
    echo "⚠️  CRITICAL: Queue has " . number_format($totalJobs) . " jobs (backlog)\n";
    echo "   Monitor 56 akan menunggu LAMA untuk diproses\n\n";
    echo "Solusi:\n";
    echo "1. Cleanup queue lama: php artisan queue:flush\n";
    echo "2. Atau truncate manual: php truncate_jobs.php\n";
    echo "3. Lalu force check: php force_check_56.php\n";
} else {
    echo "✓ Queue normal, tunggu beberapa saat\n";
}

echo "\n";
