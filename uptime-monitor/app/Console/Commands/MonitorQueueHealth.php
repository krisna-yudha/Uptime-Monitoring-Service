<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MonitorQueueHealth extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'queue:monitor-health 
                            {--cleanup : Cleanup old/stale jobs}
                            {--max-age=3600 : Maximum age in seconds for jobs before cleanup}
                            {--watch : Run continuously with interval}
                            {--interval=300 : Interval in seconds between checks when watching}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Monitor queue health and optionally cleanup stale jobs';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Watch mode: run continuously
        if ($this->option('watch')) {
            $this->info('=== Queue Health Monitor - Watch Mode ===');
            $this->info('Monitoring queue health every ' . $this->option('interval') . ' seconds');
            $this->info('Press Ctrl+C to stop');
            $this->newLine();
            
            while (true) {
                $this->runHealthCheck();
                $this->newLine();
                $this->comment('Next check in ' . $this->option('interval') . ' seconds...');
                $this->newLine();
                sleep($this->option('interval'));
            }
        }
        
        // Single run mode
        return $this->runHealthCheck();
    }

    /**
     * Run a single health check
     */
    private function runHealthCheck(): int
    {
        $this->info('=== Queue Health Monitor ===');
        $this->info('[' . now()->format('Y-m-d H:i:s') . ']');
        $this->newLine();

        // Get queue statistics
        $totalJobs = DB::table('jobs')->count();
        $failedJobs = DB::table('failed_jobs')->count();
        
        $priorityJobs = DB::table('jobs')
            ->where('queue', 'monitor-checks-priority')
            ->count();
        
        $regularJobs = DB::table('jobs')
            ->where('queue', 'monitor-checks')
            ->count();
        
        // Calculate timestamp for stale jobs (created_at is Unix timestamp in jobs table)
        $maxAgeTimestamp = now()->subSeconds($this->option('max-age'))->timestamp;
        
        $staleJobs = DB::table('jobs')
            ->where('created_at', '<', $maxAgeTimestamp)
            ->count();

        // Display statistics
        $this->table(
            ['Metric', 'Count', 'Status'],
            [
                ['Total Jobs', $totalJobs, $this->getStatusColor($totalJobs, 10000, 5000)],
                ['Priority Queue', $priorityJobs, $this->getStatusColor($priorityJobs, 1000, 500)],
                ['Regular Queue', $regularJobs, $this->getStatusColor($regularJobs, 9000, 4500)],
                ['Failed Jobs', $failedJobs, $failedJobs > 100 ? '⚠️ High' : '✓ OK'],
                ['Stale Jobs (>' . $this->option('max-age') . 's)', $staleJobs, $staleJobs > 100 ? '⚠️ Cleanup needed' : '✓ OK'],
            ]
        );

        $this->newLine();

        // Health assessment
        if ($totalJobs > 10000) {
            $this->error('❌ CRITICAL: Queue size exceeds safe limit (10,000)');
            $this->warn('Action required: Scale workers or enable cleanup');
        } elseif ($totalJobs > 5000) {
            $this->warn('⚠️  WARNING: Queue size is getting high');
            $this->info('Consider monitoring closely');
        } else {
            $this->info('✓ Queue health is good');
        }

        $this->newLine();

        // Cleanup if requested
        if ($this->option('cleanup')) {
            $this->performCleanup($staleJobs);
        } else {
            if ($staleJobs > 0) {
                $this->comment('Tip: Run with --cleanup to remove ' . $staleJobs . ' stale jobs');
            }
        }

        // Log to Laravel log
        Log::info('Queue health check', [
            'total_jobs' => $totalJobs,
            'priority_jobs' => $priorityJobs,
            'regular_jobs' => $regularJobs,
            'failed_jobs' => $failedJobs,
            'stale_jobs' => $staleJobs
        ]);

        return 0;
    }

    private function getStatusColor(int $value, int $critical, int $warning): string
    {
        if ($value >= $critical) {
            return '❌ Critical';
        } elseif ($value >= $warning) {
            return '⚠️ Warning';
        }
        return '✓ OK';
    }

    private function performCleanup(int $staleCount): void
    {
        if ($staleCount === 0) {
            $this->info('No stale jobs to cleanup');
            return;
        }

        if (!$this->confirm("Delete {$staleCount} stale jobs?", true)) {
            $this->info('Cleanup cancelled');
            return;
        }

        $this->info('Cleaning up stale jobs...');
        
        $maxAgeTimestamp = now()->subSeconds($this->option('max-age'))->timestamp;
        
        $deleted = DB::table('jobs')
            ->where('created_at', '<', $maxAgeTimestamp)
            ->delete();

        $this->info("✓ Deleted {$deleted} stale jobs");
        
        Log::warning('Queue cleanup performed', [
            'deleted_jobs' => $deleted,
            'max_age_seconds' => $this->option('max-age')
        ]);
    }
}
