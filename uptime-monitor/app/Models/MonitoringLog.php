<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonitoringLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'monitor_id',
        'event_type',
        'status',
        'log_data',
        'response_time',
        'error_message',
        'logged_at',
    ];

    protected $casts = [
        'log_data' => 'array',
        'logged_at' => 'datetime',
        'response_time' => 'decimal:3',
    ];

    public function monitor(): BelongsTo
    {
        return $this->belongsTo(Monitor::class);
    }

    /**
     * Scope to filter by event type
     */
    public function scopeByEventType($query, string $eventType)
    {
        return $query->where('event_type', $eventType);
    }

    /**
     * Scope to filter by status
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to filter by date range
     */
    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('logged_at', [$startDate, $endDate]);
    }

    /**
     * Scope to get recent logs
     */
    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('logged_at', '>=', now()->subHours($hours));
    }

    /**
     * Get formatted log data for display
     */
    public function getFormattedLogDataAttribute()
    {
        $data = $this->log_data;
        
        // Add common fields if not present
        if (!isset($data['timestamp'])) {
            $data['timestamp'] = $this->logged_at->toISOString();
        }
        
        if (!isset($data['event'])) {
            $data['event'] = $this->event_type;
        }
        
        return $data;
    }

    /**
     * Get severity level based on event type and status
     */
    public function getSeverityAttribute()
    {
        switch ($this->event_type) {
            case 'check_failed':
            case 'status_down':
                return 'error';
            case 'status_unknown':
            case 'timeout':
                return 'warning';
            case 'status_up':
            case 'check_complete':
                return 'info';
            case 'check_start':
                return 'debug';
            default:
                return 'info';
        }
    }

    /**
     * Log a monitoring event with JSON formatted data
     */
    public static function logEvent(
        int $monitorId,
        string $eventType,
        ?string $status = null,
        array $logData = [],
        ?float $responseTime = null,
        ?string $errorMessage = null
    ): ?self {
        // Guard: ensure the monitor exists to avoid foreign key violations
        try {
            if (!\App\Models\Monitor::where('id', $monitorId)->exists()) {
                \Illuminate\Support\Facades\Log::warning('MonitoringLog skipped - monitor not found', ['monitor_id' => $monitorId, 'event' => $eventType]);
                return null;
            }

            return self::create([
                'monitor_id' => $monitorId,
                'event_type' => $eventType,
                'status' => $status,
                'log_data' => array_merge([
                    'timestamp' => now()->toISOString(),
                    'server_time' => microtime(true),
                    'memory_usage' => memory_get_usage(true),
                    'php_version' => PHP_VERSION,
                ], $logData),
                'response_time' => $responseTime,
                'error_message' => $errorMessage,
                'logged_at' => now(),
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            // Log DB errors (e.g., FK violations) and continue without throwing
            \Illuminate\Support\Facades\Log::error('Failed to write MonitoringLog', ['monitor_id' => $monitorId, 'event' => $eventType, 'error' => $e->getMessage()]);
            return null;
        }
    }
}
