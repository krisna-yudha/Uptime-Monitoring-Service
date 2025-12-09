<?php

namespace App\Console\Commands;

use App\Models\Incident;
use App\Models\Monitor;
use Illuminate\Console\Command;

class ManageIncidents extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'incidents:manage {action} {incident_id?} {--show-all} {--status=} {--note=}';

    /**
     * The console command description.
     */
    protected $description = 'Manage incidents - mark as pending/done, view status and logs';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $action = $this->argument('action');

        switch ($action) {
            case 'list':
                $this->listIncidents();
                break;
            case 'show':
                $this->showIncident();
                break;
            case 'pending':
                $this->markIncident('pending');
                break;
            case 'done':
                $this->markIncident('done');
                break;
            case 'log':
                $this->showIncidentLog();
                break;
            default:
                $this->error("Unknown action: {$action}");
                $this->showHelp();
                return 1;
        }

        return 0;
    }

    private function listIncidents(): void
    {
        $query = Incident::with('monitor');

        if ($this->option('status')) {
            $query->where('status', $this->option('status'));
        }

        if (!$this->option('show-all')) {
            $query->where('resolved', false);
        }

        $incidents = $query->orderBy('started_at', 'desc')->get();

        if ($incidents->isEmpty()) {
            $this->info('No incidents found.');
            return;
        }

        $this->info('=== INCIDENTS ===');
        $this->table(
            ['ID', 'Monitor', 'Status', 'Alert Status', 'Started', 'Duration', 'Resolved'],
            $incidents->map(function ($incident) {
                return [
                    $incident->id,
                    $incident->monitor->name ?? 'Unknown',
                    $incident->status ?? 'unknown',
                    $incident->alert_status ?? 'none',
                    $incident->started_at->format('Y-m-d H:i:s'),
                    $incident->resolved 
                        ? ($incident->getDurationAttribute() . ' sec') 
                        : 'Ongoing',
                    $incident->resolved ? 'Yes' : 'No'
                ];
            })
        );
    }

    private function showIncident(): void
    {
        $incidentId = $this->argument('incident_id');
        if (!$incidentId) {
            $this->error('Incident ID is required for show action');
            return;
        }

        $incident = Incident::with('monitor')->find($incidentId);
        if (!$incident) {
            $this->error("Incident {$incidentId} not found");
            return;
        }

        $this->info("=== INCIDENT #{$incident->id} ===");
        $this->line("Monitor: {$incident->monitor->name}");
        $this->line("Status: {$incident->status}");
        $this->line("Alert Status: {$incident->alert_status}");
        $this->line("Started: {$incident->started_at->format('Y-m-d H:i:s')}");
        
        if ($incident->ended_at) {
            $this->line("Ended: {$incident->ended_at->format('Y-m-d H:i:s')}");
            $this->line("Duration: {$incident->getDurationAttribute()} seconds");
        } else {
            $this->line("Ongoing since: " . $incident->started_at->diffForHumans());
        }
        
        $this->line("Resolved: " . ($incident->resolved ? 'Yes' : 'No'));
        
        if ($incident->acknowledged_by) {
            $this->line("Acknowledged by: {$incident->acknowledged_by}");
            $this->line("Acknowledged at: {$incident->acknowledged_at->format('Y-m-d H:i:s')}");
        }
        
        $this->newLine();
        $this->line("Description: {$incident->description}");
    }

    private function markIncident(string $status): void
    {
        $incidentId = $this->argument('incident_id');
        if (!$incidentId) {
            $this->error('Incident ID is required');
            return;
        }

        $incident = Incident::find($incidentId);
        if (!$incident) {
            $this->error("Incident {$incidentId} not found");
            return;
        }

        $note = $this->option('note') ?? "Marked as {$status} via CLI command";

        if ($status === 'pending') {
            $incident->update([
                'status' => 'pending',
                'alert_status' => 'acknowledged',
                'acknowledged_at' => now(),
                'acknowledged_by' => 'CLI Admin'
            ]);

            $incident->logAlert('incident_marked_pending', 'Incident marked as pending via CLI', [
                'acknowledged_by' => 'CLI Admin',
                'note' => $note
            ]);

            $this->info("Incident #{$incident->id} marked as PENDING");
        } 
        else if ($status === 'done') {
            $incident->update([
                'status' => 'resolved',
                'resolved' => true,
                'ended_at' => now(),
                'acknowledged_by' => 'CLI Admin'
            ]);

            $incident->logAlert('incident_marked_resolved', 'Incident manually resolved via CLI', [
                'resolved_by' => 'CLI Admin',
                'resolution_note' => $note,
                'resolution_method' => 'manual_cli'
            ]);

            $this->info("Incident #{$incident->id} marked as DONE (resolved)");
        }

        $this->showIncident();
    }

    private function showIncidentLog(): void
    {
        $incidentId = $this->argument('incident_id');
        if (!$incidentId) {
            $this->error('Incident ID is required for log action');
            return;
        }

        $incident = Incident::find($incidentId);
        if (!$incident) {
            $this->error("Incident {$incidentId} not found");
            return;
        }

        $this->info("=== INCIDENT #{$incident->id} ALERT LOG ===");
        
        if (!$incident->alert_log || empty($incident->alert_log)) {
            $this->line('No alert logs recorded.');
            return;
        }

        foreach ($incident->alert_log as $log) {
            $this->line("📅 {$log['timestamp']}");
            $this->line("🔔 {$log['type']}: {$log['message']}");
            
            if (!empty($log['metadata'])) {
                $this->line("   Metadata: " . json_encode($log['metadata'], JSON_PRETTY_PRINT));
            }
            
            $this->newLine();
        }
    }

    private function showHelp(): void
    {
        $this->info('Available actions:');
        $this->line('  list                     - List incidents');
        $this->line('  show {incident_id}       - Show incident details');
        $this->line('  pending {incident_id}    - Mark incident as pending');
        $this->line('  done {incident_id}       - Mark incident as done');
        $this->line('  log {incident_id}        - Show incident alert log');
        $this->newLine();
        $this->info('Options:');
        $this->line('  --show-all              - Include resolved incidents in list');
        $this->line('  --status={status}       - Filter by status (open, pending, resolved)');
        $this->line('  --note={note}           - Add note when marking incident');
        $this->newLine();
        $this->info('Examples:');
        $this->line('  php artisan incidents:manage list');
        $this->line('  php artisan incidents:manage show 1');
        $this->line('  php artisan incidents:manage pending 1 --note="Investigating server issues"');
        $this->line('  php artisan incidents:manage done 1 --note="Server fixed, monitoring resumed"');
    }
}
