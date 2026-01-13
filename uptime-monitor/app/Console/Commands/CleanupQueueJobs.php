<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CleanupQueueJobs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'queue:cleanup {--max-jobs=5000 : Maximum jobs to keep in queue}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up old completed jobs from queue to prevent bloat. Keeps max 5000 jobs.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $maxJobs = (int) $this->option('max-jobs');
        
        $this->info("Starting queue cleanup (max jobs: {$maxJobs})...");
        
        // Count current jobs
        $currentCount = DB::table('jobs')->count();
        $this->info("Current jobs in queue: {$currentCount}");
        
        if ($currentCount <= $maxJobs) {
            $this->info("✅ Queue size is within limit. No cleanup needed.");
            return 0;
        }
        
        // Calculate how many jobs to delete
        $toDelete = $currentCount - $maxJobs;
        $this->warn("Need to delete {$toDelete} old jobs to reach limit of {$maxJobs}");
        
        // Delete oldest jobs first (FIFO cleanup)
        // Keep the most recent jobs (highest ID = newest)
        $oldestJobId = DB::table('jobs')
            ->orderBy('id', 'desc')
            ->skip($maxJobs)
            ->value('id');
        
        if ($oldestJobId) {
            $deleted = DB::table('jobs')
                ->where('id', '<=', $oldestJobId)
                ->delete();
            
            $this->info("✅ Deleted {$deleted} old jobs");
            Log::info("Queue cleanup completed", [
                'deleted_jobs' => $deleted,
                'remaining_jobs' => DB::table('jobs')->count(),
                'max_jobs_limit' => $maxJobs
            ]);
        }
        
        // Also cleanup old failed jobs (keep last 1000)
        $failedCount = DB::table('failed_jobs')->count();
        if ($failedCount > 1000) {
            $oldestFailedId = DB::table('failed_jobs')
                ->orderBy('id', 'desc')
                ->skip(1000)
                ->value('id');
            
            if ($oldestFailedId) {
                $deletedFailed = DB::table('failed_jobs')
                    ->where('id', '<=', $oldestFailedId)
                    ->delete();
                
                $this->info("✅ Deleted {$deletedFailed} old failed jobs");
            }
        }
        
        $finalCount = DB::table('jobs')->count();
        $this->info("Final jobs count: {$finalCount}");
        
        return 0;
    }
}
