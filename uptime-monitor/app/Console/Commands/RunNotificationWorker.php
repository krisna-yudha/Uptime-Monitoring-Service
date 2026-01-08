<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Exception;

class RunNotificationWorker extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'worker:notifications 
                            {--queue=notifications : The queue to listen on}
                            {--sleep=0 : Number of seconds to sleep when no job is available}
                            {--tries=3 : Number of times to attempt a job before logging it failed}
                            {--timeout=120 : The number of seconds a child process can run}
                            {--verbose : Display verbose output}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run the notification queue worker to send alerts to configured channels';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔔 Starting Notification Worker...');
        $this->info('Queue: ' . $this->option('queue'));
        $this->info('Sleep: ' . $this->option('sleep') . ' seconds');
        $this->info('Max Tries: ' . $this->option('tries'));
        $this->info('Timeout: ' . $this->option('timeout') . ' seconds');
        $this->info('Press Ctrl+C to stop the worker');
        $this->newLine();

        Log::info('Notification worker started', [
            'queue' => $this->option('queue'),
            'sleep' => $this->option('sleep'),
            'tries' => $this->option('tries'),
            'timeout' => $this->option('timeout'),
        ]);

        $queue = $this->option('queue');
        $sleep = $this->option('sleep');
        $tries = $this->option('tries');
        $timeout = $this->option('timeout');

        // Run queue worker with specified options on default connection
        try {
            $this->call('queue:work', [
                'connection' => 'database',  // Use database connection
                '--queue' => $queue,          // Listen to notifications queue
                '--sleep' => $sleep,
                '--tries' => $tries,
                '--timeout' => $timeout,
                '--verbose' => $this->option('verbose'),
                '--name' => 'notification-worker',
                '--max-jobs' => 1000,        // Restart after 1000 jobs to prevent memory leak
            ]);
        } catch (Exception $e) {
            $this->error('Worker stopped with error: ' . $e->getMessage());
            Log::error('Notification worker error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }

        return 0;
    }
}
