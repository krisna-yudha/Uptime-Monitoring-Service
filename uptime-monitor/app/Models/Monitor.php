<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Log;
use Exception;

class Monitor extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'group_name',
        'group_description',
        'group_config',
        'type',
        'target',
        'config',
        'interval_seconds',
        'timeout_ms',
        'retries',
        'notify_after_retries',
        'consecutive_failures',
        'enabled',
        'tags',
        'created_by',
        'heartbeat_key',
        'last_status',
        'last_error',
        'last_checked_at',
        'next_check_at',
        'pause_until',
        'notification_channels',
        'last_notification_sent',
        'last_critical_alert_sent',
        'error_message',
        'last_error_at',
    ];

    protected $casts = [
        'config' => 'array',
        'group_config' => 'array',
        'tags' => 'array',
        'notification_channels' => 'array',
        'enabled' => 'boolean',
        'last_checked_at' => 'datetime',
        'next_check_at' => 'datetime',
        'pause_until' => 'datetime',
        'last_notification_sent' => 'datetime',
        'last_critical_alert_sent' => 'datetime',
        'last_error_at' => 'datetime',
    ];

    // Relationships
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function checks(): HasMany
    {
        return $this->hasMany(MonitorCheck::class);
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class);
    }

    public function metrics(): HasMany
    {
        return $this->hasMany(MonitorMetric::class);
    }

    // Helper methods
    public function isUp(): bool
    {
        return $this->last_status === 'up';
    }

    public function isDown(): bool
    {
        return $this->last_status === 'down';
    }

    public function isInvalid(): bool
    {
        return $this->last_status === 'invalid';
    }

    public function isValidating(): bool
    {
        return $this->last_status === 'validating';
    }

    public function isUnknown(): bool
    {
        return $this->last_status === 'unknown' || $this->last_status === null;
    }

    public function isPaused(): bool
    {
        return $this->pause_until && $this->pause_until->isFuture();
    }

    /**
     * Get the available status options
     */
    public static function getStatusOptions(): array
    {
        return [
            'unknown' => 'Unknown',
            'validating' => 'Validating',
            'up' => 'Up',
            'down' => 'Down', 
            'invalid' => 'Invalid'
        ];
    }

    /**
     * Get status color for UI
     */
    public function getStatusColor(): string
    {
        return match($this->last_status) {
            'up' => 'success',
            'down' => 'danger',
            'invalid' => 'warning',
            'validating' => 'info',
            default => 'secondary'
        };
    }

    /**
     * Scope to get monitors by group
     */
    public function scopeByGroup($query, string $groupName)
    {
        return $query->where('group_name', $groupName);
    }

    /**
     * Scope to get monitors without group
     */
    public function scopeUngrouped($query)
    {
        return $query->whereNull('group_name');
    }

    /**
     * Get all available groups with counts
     */
    public static function getGroupsWithCounts(): array
    {
        try {
            $groups = self::whereNotNull('group_name')
                ->select('group_name', 'group_description')
                ->groupBy('group_name', 'group_description')
                ->get()
                ->map(function ($group) {
                    $groupMonitors = self::where('group_name', $group->group_name)->get();
                    $totalCount = $groupMonitors->count();
                    $upCount = $groupMonitors->where('last_status', 'up')->count();
                    $downCount = $groupMonitors->where('last_status', 'down')->count();
                    $invalidCount = $groupMonitors->where('last_status', 'invalid')->count();
                    $unknownCount = $groupMonitors->where('last_status', 'unknown')->count();
                    
                    return [
                        'group_name' => $group->group_name,
                        'group_description' => $group->group_description,
                        'monitors_count' => $totalCount,
                        'up_count' => $upCount,
                        'down_count' => $downCount,
                        'invalid_count' => $invalidCount,
                        'unknown_count' => $unknownCount,
                        'health_percentage' => $totalCount > 0 ? round(($upCount / $totalCount) * 100, 1) : 0
                    ];
                })
                ->toArray();

            return $groups;
        } catch (Exception $e) {
            Log::error('Failed to get groups with counts: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get monitors grouped by group name
     */
    public static function getMonitorsGrouped(): array
    {
        $monitors = self::orderBy('group_name')->orderBy('name')->get();
        
        $grouped = [];
        foreach ($monitors as $monitor) {
            $groupName = $monitor->group_name ?: 'Ungrouped';
            if (!isset($grouped[$groupName])) {
                $grouped[$groupName] = [
                    'name' => $groupName,
                    'description' => $monitor->group_description,
                    'monitors' => []
                ];
            }
            $grouped[$groupName]['monitors'][] = $monitor;
        }

        return $grouped;
    }
}
