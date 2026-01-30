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
     * Immediately dispatch first check job for new monitor.
     */
    public function created(Monitor $monitor): void
    {
        // Set next_check_at using saveQuietly to prevent triggering Observer again
        $monitor->next_check_at = now();
        $monitor->saveQuietly();
        
        try {
            // Dispatch first check immediately to priority queue
            // This ensures new monitor gets checked right away
            ProcessMonitorCheck::dispatch($monitor)
                ->onQueue('monitor-checks-priority');
            
            Log::info('[Observer] New monitor - first check dispatched', [
                'monitor_id' => $monitor->id,
                'monitor_name' => $monitor->name,
                'type' => $monitor->type,
                'target' => $monitor->target,
                'queue' => 'monitor-checks-priority'
            ]);
        } catch (\Exception $e) {
            Log::error('[Observer] Failed to dispatch initial check', [
                'monitor_id' => $monitor->id,
                'error' => $e->getMessage()
            ]);
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
            
            // Update next_check_at using saveQuietly to prevent infinite loop
            $monitor->next_check_at = now();
            $monitor->saveQuietly();
        }
    }
}
