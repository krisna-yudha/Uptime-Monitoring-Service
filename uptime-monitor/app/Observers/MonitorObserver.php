<?php

namespace App\Observers;

use App\Models\Monitor;
use App\Jobs\ProcessMonitorCheck;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MonitorObserver
{
    /**
     * Handle the Monitor "created" event.
     */
    public function created(Monitor $monitor): void
    {
        // Execute initial monitor check after monitor creation
        // Strategy: Use synchronous check if queue is overloaded, otherwise dispatch
        
        try {
            $totalJobs = DB::table('jobs')->count();
            
            // If queue is overloaded (>15000 jobs), run synchronously for immediate feedback
            if ($totalJobs > 15000) {
                Log::warning('[Observer] Queue overloaded - running synchronous initial check', [
                    'monitor_id' => $monitor->id,
                    'total_jobs' => $totalJobs,
                    'mode' => 'synchronous'
                ]);
                
                $job = new ProcessMonitorCheck($monitor);
                $job->handle();
            } else {
                // Dispatch to high-priority queue for immediate processing
                ProcessMonitorCheck::dispatch($monitor)
                    ->onQueue('monitor-checks-priority');
                
                Log::info('[Observer] Initial monitor check dispatched to priority queue', [
                    'monitor_id' => $monitor->id,
                    'type' => $monitor->type,
                    'target' => $monitor->target,
                    'total_jobs' => $totalJobs
                ]);
            }
        } catch (\Exception $e) {
            // Fallback: always try synchronous execution if anything fails
            Log::warning('[Observer] Failed to process initial check - trying synchronous fallback', [
                'monitor_id' => $monitor->id,
                'error' => $e->getMessage()
            ]);
            
            try {
                $job = new ProcessMonitorCheck($monitor);
                $job->handle();
            } catch (\Exception $syncError) {
                Log::error('[Observer] Synchronous check failed', [
                    'monitor_id' => $monitor->id,
                    'error' => $syncError->getMessage()
                ]);
            }
        }
    }

    /**
     * Handle the Monitor "updated" event.
     */
    public function updated(Monitor $monitor): void
    {
        // If monitor was just enabled, schedule immediate check
        if ($monitor->isDirty('enabled') && $monitor->enabled) {
            Log::info('[Observer] Monitor re-enabled - scheduling immediate check', [
                'monitor_id' => $monitor->id
            ]);
            
            // Update next_check_at to now so it gets picked up immediately
            $monitor->update(['next_check_at' => now()]);
        }
    }
}
