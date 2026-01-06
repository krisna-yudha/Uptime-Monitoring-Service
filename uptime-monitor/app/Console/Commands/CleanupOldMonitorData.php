<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MonitorCheck;
use App\Models\MonitoringLog;
use App\Models\MonitorMetricAggregated;
use Carbon\Carbon;

class CleanupOldMonitorData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'metrics:cleanup 
                            {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cleanup old raw monitoring data based on retention policy';

    /**
     * Data retention policy (in days)
     */
    protected $retentionPolicy = [
        'raw_checks' => 30,      // Keep raw checks for 30 days
        'raw_logs' => 30,        // Keep raw logs for 30 days
        'minute_aggregates' => 30,  // Keep minute aggregates for 30 days
        'hour_aggregates' => 30,    // Keep hour aggregates for 30 days
        'day_aggregates' => 30,     // Keep day aggregates for 30 days
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');

        if ($isDryRun) {
            $this->warn('DRY RUN MODE - No data will be deleted');
        }

        $this->info('Starting cleanup process...');
        $this->newLine();

        // Cleanup raw checks (older than 7 days)
        $this->cleanupRawChecks($isDryRun);

        // Cleanup raw logs (older than 30 days)
        $this->cleanupRawLogs($isDryRun);

        // Cleanup minute aggregates (older than 30 days)
        $this->cleanupAggregates('minute', $this->retentionPolicy['minute_aggregates'], $isDryRun);

        // Cleanup hour aggregates (older than 90 days)
        $this->cleanupAggregates('hour', $this->retentionPolicy['hour_aggregates'], $isDryRun);

        // Day aggregates are kept for 1 year (365 days)
        $this->cleanupAggregates('day', $this->retentionPolicy['day_aggregates'], $isDryRun);

        $this->newLine();
        $this->info('✓ Cleanup completed!');

        return 0;
    }

    /**
     * Cleanup old raw monitor checks
     */
    protected function cleanupRawChecks(bool $isDryRun): void
    {
        $days = $this->retentionPolicy['raw_checks'];
        $cutoffDate = Carbon::now()->subDays($days);

        $query = MonitorCheck::where('checked_at', '<', $cutoffDate);
        $count = $query->count();

        if ($count > 0) {
            $this->line("Raw Checks: Found {$count} records older than {$days} days");
            
            if (!$isDryRun) {
                $deleted = $query->delete();
                $this->info("  → Deleted {$deleted} raw check records");
            } else {
                $this->comment("  → Would delete {$count} raw check records");
            }
        } else {
            $this->line("Raw Checks: No old records to delete");
        }
    }

    /**
     * Cleanup old raw monitoring logs
     */
    protected function cleanupRawLogs(bool $isDryRun): void
    {
        $days = $this->retentionPolicy['raw_logs'];
        $cutoffDate = Carbon::now()->subDays($days);

        $query = MonitoringLog::where('logged_at', '<', $cutoffDate);
        $count = $query->count();

        if ($count > 0) {
            $this->line("Raw Logs: Found {$count} records older than {$days} days");
            
            if (!$isDryRun) {
                $deleted = $query->delete();
                $this->info("  → Deleted {$deleted} raw log records");
            } else {
                $this->comment("  → Would delete {$count} raw log records");
            }
        } else {
            $this->line("Raw Logs: No old records to delete");
        }
    }

    /**
     * Cleanup old aggregated data
     */
    protected function cleanupAggregates(string $interval, int $days, bool $isDryRun): void
    {
        $cutoffDate = Carbon::now()->subDays($days);

        $query = MonitorMetricAggregated::where('interval', $interval)
            ->where('period_start', '<', $cutoffDate);
        
        $count = $query->count();

        if ($count > 0) {
            $intervalLabel = ucfirst($interval);
            $this->line("{$intervalLabel} Aggregates: Found {$count} records older than {$days} days");
            
            if (!$isDryRun) {
                $deleted = $query->delete();
                $this->info("  → Deleted {$deleted} {$interval} aggregate records");
            } else {
                $this->comment("  → Would delete {$count} {$interval} aggregate records");
            }
        } else {
            $this->line(ucfirst($interval) . " Aggregates: No old records to delete");
        }
    }
}
