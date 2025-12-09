<?php

namespace App\Console\Commands;

use App\Jobs\ProcessMonitorCheck;
use App\Models\Monitor;
use Illuminate\Console\Command;

class RunMonitorChecks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'monitor:check {--monitor-id= : Check specific monitor only} {--loop : Run in continuous loop mode}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run monitor checks for due monitors';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if ($this->option('loop')) {
            return $this->runContinuous();
        }

        return $this->runOnce();
    }

    /**
     * Run monitor checks once
     */
    private function runOnce(): int
    {
        $query = Monitor::where('enabled', true)
            ->where(function ($q) {
                $q->whereNull('pause_until')
                  ->orWhere('pause_until', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('next_check_at')
                  ->orWhere('next_check_at', '<=', now());
            });

        // Check specific monitor if provided
        if ($monitorId = $this->option('monitor-id')) {
            $query->where('id', $monitorId);
        }

        $monitors = $query->get();

        if ($monitors->isEmpty()) {
            $this->info('No monitors are due for checking.');
            return 0;
        }

        $this->info("Found {$monitors->count()} monitors to check.");

        foreach ($monitors as $monitor) {
            // Skip push monitors (they don't need periodic checks)
            if ($monitor->type === 'push') {
                continue;
            }

            $this->line("Queueing check for monitor: {$monitor->name}");
            
            // Dispatch the job
            ProcessMonitorCheck::dispatch($monitor);

            // Update next check time to prevent duplicate runs
            $monitor->update([
                'next_check_at' => now()->addSeconds($monitor->interval_seconds)
            ]);
        }

        $this->info('All monitor checks have been queued successfully.');

        return 0;
    }

    /**
     * Run monitor checks in continuous loop every 1 second
     */
    private function runContinuous(): int
    {
        $this->info('Starting continuous monitor checking every 1 second...');
        $this->info('Press Ctrl+C to stop.');

        while (true) {
            try {
                $this->runOnce();
                
                // Sleep for 1 second before next iteration
                sleep(1);
                
            } catch (\Exception $e) {
                $this->error('Error during monitor check: ' . $e->getMessage());
                // Continue running even if there's an error
                sleep(1);
            }
        }

        return 0;
    }
}
