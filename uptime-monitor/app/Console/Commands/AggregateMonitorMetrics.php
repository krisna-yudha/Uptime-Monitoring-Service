<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Monitor;
use App\Models\MonitorCheck;
use App\Models\MonitorMetricAggregated;
use App\Models\Incident;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AggregateMonitorMetrics extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'metrics:aggregate 
                            {--interval=minute : Interval type (minute, hour, day)}
                            {--monitor= : Specific monitor ID (optional)}
                            {--date= : Specific date to aggregate (YYYY-MM-DD)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Aggregate monitor metrics to reduce database size and improve performance';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $interval = $this->option('interval');
        $monitorId = $this->option('monitor');
        $date = $this->option('date');

        $this->info("Starting metrics aggregation for interval: {$interval}");

        // Get monitors to process
        $monitors = $monitorId 
            ? Monitor::where('id', $monitorId)->get()
            : Monitor::all();

        if ($monitors->isEmpty()) {
            $this->warn('No monitors found to aggregate');
            return 0;
        }

        $this->info("Processing {$monitors->count()} monitor(s)");

        $totalAggregated = 0;

        foreach ($monitors as $monitor) {
            $count = match($interval) {
                'minute' => $this->aggregateMinutes($monitor, $date),
                'hour' => $this->aggregateHours($monitor, $date),
                'day' => $this->aggregateDays($monitor, $date),
                default => 0
            };

            $totalAggregated += $count;
            $this->info("Monitor #{$monitor->id} ({$monitor->name}): {$count} periods aggregated");
        }

        $this->info("✓ Completed! Total aggregated periods: {$totalAggregated}");

        return 0;
    }

    /**
     * Aggregate data per minute
     */
    protected function aggregateMinutes(Monitor $monitor, ?string $date): int
    {
        if ($date) {
            // Manual run: aggregate entire day
            $targetDate = Carbon::parse($date);
            $periodStart = $targetDate->copy()->startOfDay();
            $periodEnd = $targetDate->copy()->endOfDay();
            
            $this->line("  Aggregating minutes for {$targetDate->format('Y-m-d')}...");
            
            $count = 0;
            $current = $periodStart->copy();
            
            while ($current < $periodEnd) {
                $minuteStart = $current->copy();
                $minuteEnd = $current->copy()->addMinute();
                
                // Skip if already aggregated
                $exists = MonitorMetricAggregated::where('monitor_id', $monitor->id)
                    ->where('interval', 'minute')
                    ->where('period_start', $minuteStart)
                    ->exists();
                    
                if (!$exists) {
                    $aggregated = $this->aggregatePeriod($monitor, $minuteStart, $minuteEnd, 'minute');
                    if ($aggregated) {
                        $count++;
                    }
                }
                
                $current->addMinute();
            }
            
            return $count;
        } else {
            // Scheduled run: aggregate the last completed minute
            $minuteEnd = Carbon::now()->startOfMinute();
            $minuteStart = $minuteEnd->copy()->subMinute();
            
            $this->line("  Aggregating minute: {$minuteStart->format('Y-m-d H:i')}");
            
            // Check if already aggregated
            $exists = MonitorMetricAggregated::where('monitor_id', $monitor->id)
                ->where('interval', 'minute')
                ->where('period_start', $minuteStart)
                ->exists();
                
            if ($exists) {
                $this->line("  Already aggregated, skipping...");
                return 0;
            }
            
            return $this->aggregatePeriod($monitor, $minuteStart, $minuteEnd, 'minute') ? 1 : 0;
        }
    }

    /**
     * Aggregate data per hour
     */
    protected function aggregateHours(Monitor $monitor, ?string $date): int
    {
        if ($date) {
            // Manual run: aggregate entire day
            $targetDate = Carbon::parse($date);
            $periodStart = $targetDate->copy()->startOfDay();
            $periodEnd = $targetDate->copy()->endOfDay();
            
            $this->line("  Aggregating hours for {$targetDate->format('Y-m-d')}...");
            
            $count = 0;
            $current = $periodStart->copy();
            
            while ($current < $periodEnd) {
                $hourStart = $current->copy();
                $hourEnd = $current->copy()->addHour();
                
                // Skip if already aggregated
                $exists = MonitorMetricAggregated::where('monitor_id', $monitor->id)
                    ->where('interval', 'hour')
                    ->where('period_start', $hourStart)
                    ->exists();
                    
                if (!$exists) {
                    $aggregated = $this->aggregatePeriod($monitor, $hourStart, $hourEnd, 'hour');
                    if ($aggregated) {
                        $count++;
                    }
                }
                
                $current->addHour();
            }
            
            return $count;
        } else {
            // Scheduled run: aggregate the last completed hour
            $hourEnd = Carbon::now()->startOfHour();
            $hourStart = $hourEnd->copy()->subHour();
            
            $this->line("  Aggregating hour: {$hourStart->format('Y-m-d H:00')}");
            
            // Check if already aggregated
            $exists = MonitorMetricAggregated::where('monitor_id', $monitor->id)
                ->where('interval', 'hour')
                ->where('period_start', $hourStart)
                ->exists();
                
            if ($exists) {
                $this->line("  Already aggregated, skipping...");
                return 0;
            }
            
            return $this->aggregatePeriod($monitor, $hourStart, $hourEnd, 'hour') ? 1 : 0;
        }
    }

    /**
     * Aggregate data per day
     */
    protected function aggregateDays(Monitor $monitor, ?string $date): int
    {
        // Default: aggregate yesterday's data (for scheduled runs)
        $targetDate = $date ? Carbon::parse($date) : Carbon::yesterday();
        
        $dayStart = $targetDate->copy()->startOfDay();
        $dayEnd = $targetDate->copy()->endOfDay();

        $this->line("  Aggregating day for {$targetDate->format('Y-m-d')}...");
        
        // Check if already aggregated
        $exists = MonitorMetricAggregated::where('monitor_id', $monitor->id)
            ->where('interval', 'day')
            ->where('period_start', $dayStart)
            ->exists();
            
        if ($exists) {
            $this->line("  Already aggregated, skipping...");
            return 0;
        }

        return $this->aggregatePeriod($monitor, $dayStart, $dayEnd, 'day') ? 1 : 0;
    }

    /**
     * Aggregate metrics for a specific period
     */
    protected function aggregatePeriod(Monitor $monitor, Carbon $periodStart, Carbon $periodEnd, string $interval): bool
    {
        // Get all checks in this period
        $checks = MonitorCheck::where('monitor_id', $monitor->id)
            ->whereBetween('checked_at', [$periodStart, $periodEnd])
            ->get();

        // Skip if no data
        if ($checks->isEmpty()) {
            return false;
        }

        // Calculate metrics
        $totalChecks = $checks->count();
        $successfulChecks = $checks->where('status', 'up')->count();
        $failedChecks = $checks->where('status', 'down')->count();
        $uptimePercentage = $totalChecks > 0 ? ($successfulChecks / $totalChecks) * 100 : 0;

        // Response time calculations
        $responseTimes = $checks->whereNotNull('latency_ms')->pluck('latency_ms')->filter();
        
        $avgResponseTime = $responseTimes->isNotEmpty() ? $responseTimes->avg() : null;
        $minResponseTime = $responseTimes->isNotEmpty() ? $responseTimes->min() : null;
        $maxResponseTime = $responseTimes->isNotEmpty() ? $responseTimes->max() : null;
        $medianResponseTime = $responseTimes->isNotEmpty() ? $responseTimes->median() : null;

        // Incident count in this period
        $incidentCount = Incident::where('monitor_id', $monitor->id)
            ->whereBetween('started_at', [$periodStart, $periodEnd])
            ->count();

        // Calculate total downtime
        $downChecks = $checks->where('status', 'down');
        $totalDowntimeSeconds = 0;
        
        if ($downChecks->isNotEmpty()) {
            // Estimate downtime based on check interval
            $avgInterval = $monitor->interval_seconds ?? 60;
            $totalDowntimeSeconds = $downChecks->count() * $avgInterval;
        }

        // Insert or update aggregated data
        MonitorMetricAggregated::updateOrCreate(
            [
                'monitor_id' => $monitor->id,
                'interval' => $interval,
                'period_start' => $periodStart,
            ],
            [
                'period_end' => $periodEnd,
                'total_checks' => $totalChecks,
                'successful_checks' => $successfulChecks,
                'failed_checks' => $failedChecks,
                'uptime_percentage' => round($uptimePercentage, 2),
                'avg_response_time' => $avgResponseTime ? round($avgResponseTime, 3) : null,
                'min_response_time' => $minResponseTime ? round($minResponseTime, 3) : null,
                'max_response_time' => $maxResponseTime ? round($maxResponseTime, 3) : null,
                'median_response_time' => $medianResponseTime ? round($medianResponseTime, 3) : null,
                'incident_count' => $incidentCount,
                'total_downtime_seconds' => round($totalDowntimeSeconds, 2),
            ]
        );

        return true;
    }
}
