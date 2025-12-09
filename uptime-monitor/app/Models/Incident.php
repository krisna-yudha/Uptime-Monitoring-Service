<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Incident extends Model
{
    use HasFactory;

    protected $fillable = [
        'monitor_id',
        'started_at',
        'ended_at',
        'resolved',
        'status',
        'alert_status',
        'acknowledged_at',
        'acknowledged_by',
        'alert_log',
        'description',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'resolved' => 'boolean',
        'alert_log' => 'array',
    ];

    protected $appends = [
        'monitor_name',
        'duration'
    ];

    // Relationships
    public function monitor(): BelongsTo
    {
        return $this->belongsTo(Monitor::class);
    }

    // Accessors
    public function getMonitorNameAttribute(): ?string
    {
        return $this->monitor?->name;
    }

    // Helper methods
    public function getDurationAttribute(): ?int
    {
        if (!$this->ended_at) {
            return null;
        }
        
        return $this->ended_at->diffInSeconds($this->started_at);
    }

    public function isOngoing(): bool
    {
        return !$this->resolved && !$this->ended_at;
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isDone(): bool
    {
        return $this->status === 'solved' || $this->status === 'resolved' || $this->resolved;
    }

    public function hasCriticalAlertBeenSent(): bool
    {
        return in_array($this->alert_status, ['critical_sent', 'acknowledged', 'escalated']);
    }

    public function markAsPending(): void
    {
        $this->update([
            'status' => 'pending',
            'alert_status' => 'acknowledged'
        ]);
        
        $this->logAlert('incident_marked_pending', 'Incident marked as pending for investigation');
    }

    public function markAsDone(): void
    {
        $this->update([
            'status' => 'solved',
            'resolved' => true,
            'ended_at' => now()
        ]);
        
        $this->logAlert('incident_solved', 'Incident marked as solved');
    }

    public function logAlert(string $type, string $message, array $metadata = []): void
    {
        $log = $this->alert_log ?? [];
        
        // Safely get consecutive_failures - load monitor if not loaded
        $consecutiveFailures = 0;
        if ($this->monitor) {
            $consecutiveFailures = $this->monitor->consecutive_failures ?? 0;
        } elseif ($this->monitor_id) {
            // Load monitor if needed
            $this->load('monitor');
            $consecutiveFailures = $this->monitor->consecutive_failures ?? 0;
        }
        
        $log[] = [
            'type' => $type,
            'message' => $message,
            'metadata' => $metadata,
            'timestamp' => now()->toISOString(),
            'consecutive_failures' => $consecutiveFailures
        ];
        
        $this->update(['alert_log' => $log]);
    }

    public function updateAlertStatus(string $status, array $metadata = []): void
    {
        $this->update(['alert_status' => $status]);
        $this->logAlert('alert_status_changed', "Alert status changed to: {$status}", $metadata);
    }
}
