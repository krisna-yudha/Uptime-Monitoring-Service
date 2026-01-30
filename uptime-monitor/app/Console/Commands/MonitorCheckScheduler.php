<?php

namespace App\Console\Commands;

use App\Models\Monitor;
use App\Jobs\ProcessMonitorCheck;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class MonitorCheckScheduler extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'monitor:check-scheduler 
                            {--loop : Run continuously in loop}
                            {--interval=5 : Interval in seconds between checks (default: 5)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run monitor checks without queue worker (scheduler-based mode)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $loopMode = $this->option('loop');
        $interval = (int) $this->option('interval');

        if ($loopMode) {
            $this->info('Starting monitoring in loop mode...');
            $this->info('Press Ctrl+C to stop');
            $this->newLine();

            while (true) {
                $this->runChecks();
                sleep($interval);
            }
        } else {
            $this->runChecks();
        }

        return 0;
    }

    private function runChecks(): void
    {
        // Get monitors that are due for checking
        $monitors = Monitor::where('enabled', true)
            ->where(function ($query) {
                $query->whereNull('next_check_at')
                      ->orWhere('next_check_at', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('pause_until')
                      ->orWhere('pause_until', '<=', now());
            })
            ->where('type', '!=', 'push')
            ->get();

        if ($monitors->isEmpty()) {
            $this->comment('[' . now()->format('H:i:s') . '] No monitors due for checking');
            return;
        }

        $this->info('[' . now()->format('H:i:s') . '] Processing ' . $monitors->count() . ' monitor(s)');

        foreach ($monitors as $monitor) {
            try {
                // Run check synchronously (no queue)
                $job = new ProcessMonitorCheck($monitor);
                $job->handle();

                $this->line("  ✓ {$monitor->name} (ID: {$monitor->id}) - {$monitor->last_status}");
            } catch (\Exception $e) {
                $this->error("  ✗ {$monitor->name} (ID: {$monitor->id}) - ERROR: {$e->getMessage()}");
                Log::error('Monitor check failed in scheduler mode', [
                    'monitor_id' => $monitor->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }
}
