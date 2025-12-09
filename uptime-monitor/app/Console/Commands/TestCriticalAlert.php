<?php

namespace App\Console\Commands;

use App\Models\Monitor;
use App\Models\MonitorCheck;
use App\Models\Incident;
use App\Jobs\ProcessMonitorCheck;
use App\Jobs\SendNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestCriticalAlert extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:critical-alert {monitor_id?} {--simulate-20-failures}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test critical alert functionality by simulating 20 consecutive failures';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $monitorId = $this->argument('monitor_id');
        
        if (!$monitorId) {
            // Show available monitors
            $monitors = Monitor::select('id', 'name', 'target', 'consecutive_failures')->get();
            
            if ($monitors->isEmpty()) {
                $this->error('No monitors found. Please create a monitor first.');
                return 1;
            }

            $this->info('Available monitors:');
            foreach ($monitors as $monitor) {
                $this->line("ID: {$monitor->id} | Name: {$monitor->name} | Target: {$monitor->target} | Consecutive Failures: {$monitor->consecutive_failures}");
            }
            
            $monitorId = $this->ask('Enter monitor ID to test');
        }

        $monitor = Monitor::find($monitorId);
        if (!$monitor) {
            $this->error("Monitor with ID {$monitorId} not found.");
            return 1;
        }

        $this->info("Testing critical alert for monitor: {$monitor->name}");

        if ($this->option('simulate-20-failures')) {
            $this->simulateFailures($monitor);
        } else {
            $this->testSingleFailure($monitor);
        }

        return 0;
    }

    private function simulateFailures(Monitor $monitor): void
    {
        $this->info('Simulating 20 consecutive failures...');
        
        // Set monitor to have 19 failures, so next check triggers critical alert
        $monitor->update([
            'consecutive_failures' => 19,
            'last_status' => 'down',
            'last_error' => 'Simulated failure for testing critical alert',
            'last_error_at' => now()
        ]);

        $this->info("Set monitor consecutive_failures to 19. Next check will trigger critical alert.");
        
        // Simulate one more failure to trigger critical alert
        $this->info('Processing monitor check to trigger 20th failure...');
        
        // Mock the target to always fail
        $originalTarget = $monitor->target;
        $monitor->update(['target' => 'https://this-will-definitely-fail-12345.invalid']);
        
        try {
            $job = new ProcessMonitorCheck($monitor);
            $job->handle();
            
            $monitor->refresh();
            
            $this->info("Monitor check completed. Consecutive failures: {$monitor->consecutive_failures}");
            
            if ($monitor->consecutive_failures >= 20) {
                $this->warn('🚨 CRITICAL ALERT TRIGGERED! 🚨');
                $this->info('Critical down alert should have been sent to notification channels.');
                
                if ($monitor->last_critical_alert_sent) {
                    $this->info("Last critical alert sent: {$monitor->last_critical_alert_sent}");
                } else {
                    $this->warn("Warning: last_critical_alert_sent was not updated.");
                }
            }
            
        } catch (\Exception $e) {
            $this->error("Error during monitor check: " . $e->getMessage());
        } finally {
            // Restore original target
            $monitor->update(['target' => $originalTarget]);
        }
    }

    private function testSingleFailure(Monitor $monitor): void
    {
        $this->info("Current consecutive failures: {$monitor->consecutive_failures}");
        
        if ($monitor->consecutive_failures >= 20) {
            $this->info('Monitor already has 20+ consecutive failures.');
            
            // Test direct critical alert
            $incident = Incident::where('monitor_id', $monitor->id)
                ->where('resolved', false)
                ->latest()
                ->first();
                
            if (!$incident) {
                $incident = Incident::create([
                    'monitor_id' => $monitor->id,
                    'type' => 'down',
                    'status' => 'open',
                    'alert_status' => 'none',
                    'started_at' => now(),
                    'title' => 'Test Critical Alert',
                    'description' => 'Testing critical alert functionality'
                ]);
                
                $this->info("Created test incident ID: {$incident->id}");
            } else {
                $this->info("Using existing incident ID: {$incident->id} (Status: {$incident->status})");
            }

            $this->info('Sending test critical alert...');
            SendNotification::dispatch($monitor, 'critical_down', null, $incident);
            
            // Update incident status to demonstrate workflow
            $incident->updateAlertStatus('critical_sent', [
                'test_mode' => true,
                'consecutive_failures' => $monitor->consecutive_failures
            ]);
            
            if ($incident->status === 'open') {
                $incident->update(['status' => 'pending']);
                $incident->logAlert('incident_escalated', 'Test: Incident escalated to pending due to critical alert');
                $this->warn("Incident status updated to PENDING");
            }
            
            $this->info('Test critical alert dispatched and incident updated.');
            
            // Show incident log
            $this->newLine();
            $this->info("=== INCIDENT ALERT LOG ===");
            if ($incident->alert_log) {
                foreach ($incident->alert_log as $log) {
                    $this->line("• [{$log['timestamp']}] {$log['type']}: {$log['message']}");
                }
            } else {
                $this->line("No alert logs yet.");
            }
            
        } else {
            $this->info('Monitor has less than 20 consecutive failures. Use --simulate-20-failures to test critical alert.');
        }
    }
}
