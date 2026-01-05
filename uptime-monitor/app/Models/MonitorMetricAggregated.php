<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonitorMetricAggregated extends Model
{
    protected $table = 'monitor_metrics_aggregated';
    
    protected $fillable = [
        'monitor_id',
        'interval',
        'period_start',
        'period_end',
        'total_checks',
        'successful_checks',
        'failed_checks',
        'uptime_percentage',
        'avg_response_time',
        'min_response_time',
        'max_response_time',
        'median_response_time',
        'incident_count',
        'total_downtime_seconds',
    ];

    protected $casts = [
        'period_start' => 'datetime',
        'period_end' => 'datetime',
        'uptime_percentage' => 'decimal:2',
        'avg_response_time' => 'decimal:3',
        'min_response_time' => 'decimal:3',
        'max_response_time' => 'decimal:3',
        'median_response_time' => 'decimal:3',
        'total_downtime_seconds' => 'decimal:2',
    ];

    public function monitor(): BelongsTo
    {
        return $this->belongsTo(Monitor::class);
    }

    /**
     * Scope to filter by interval type
     */
    public function scopeInterval($query, string $interval)
    {
        return $query->where('interval', $interval);
    }

    /**
     * Scope to filter by date range
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('period_start', [$startDate, $endDate]);
    }

    /**
     * Scope to get recent aggregated data
     */
    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('period_start', '>=', now()->subHours($hours));
    }
}
