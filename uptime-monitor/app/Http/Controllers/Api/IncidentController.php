<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Incident;
use App\Models\Monitor;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class IncidentController extends Controller
{
    /**
     * Display a listing of incidents with filters
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Incident::with('monitor')
                ->orderBy('started_at', 'desc');

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter by alert_status
            if ($request->has('alert_status')) {
                $query->where('alert_status', $request->alert_status);
            }

            // Filter by monitor
            if ($request->has('monitor_id')) {
                $query->where('monitor_id', $request->monitor_id);
            }

            // Filter by resolved status
            if ($request->has('resolved')) {
                $query->where('resolved', $request->boolean('resolved'));
            }

            $incidents = $query->paginate($request->get('per_page', 20));

            // Transform the data to include additional fields needed by frontend
            $incidents->getCollection()->transform(function ($incident) {
                return [
                    'id' => $incident->id,
                    'monitor_id' => $incident->monitor_id,
                    'monitor_name' => $incident->monitor?->name,
                    'monitor' => [
                        'id' => $incident->monitor?->id,
                        'name' => $incident->monitor?->name,
                        'target' => $incident->monitor?->target,
                        'type' => $incident->monitor?->type,
                    ],
                    'started_at' => $incident->started_at,
                    'ended_at' => $incident->ended_at,
                    'resolved_at' => $incident->ended_at,
                    'acknowledged_at' => $incident->acknowledged_at,
                    'acknowledged_by' => $incident->acknowledged_by,
                    'status' => $incident->status ?: 'open',
                    'alert_status' => $incident->alert_status,
                    'resolved' => $incident->resolved,
                    'description' => $incident->description,
                    'error_message' => $incident->monitor?->last_error,
                    'failure_count' => $incident->monitor?->consecutive_failures,
                    'last_check_at' => $incident->monitor?->last_check_at,
                    'duration' => $incident->getDurationAttribute(),
                    'notes' => [], // Notes functionality can be added later
                    'alert_log' => $incident->alert_log ?? []
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $incidents
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch incidents', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch incidents'
            ], 500);
        }
    }

    /**
     * Display the specified incident
     */
    public function show(Incident $incident): JsonResponse
    {
        try {
            $incident->load('monitor');

            return response()->json([
                'success' => true,
                'data' => $incident
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Incident not found'
            ], 404);
        }
    }

    /**
     * Mark incident as pending (acknowledged but under investigation)
     */
    public function markPending(Incident $incident, Request $request): JsonResponse
    {
        try {
            // Load monitor relationship if not already loaded
            if (!$incident->relationLoaded('monitor')) {
                $incident->load('monitor');
            }
            
            $user = Auth::user();
            
            $incident->update([
                'status' => 'pending',
                'alert_status' => 'acknowledged',
                'acknowledged_at' => now(),
                'acknowledged_by' => $user->name ?? 'System'
            ]);

            // Only log alert if monitor is loaded
            try {
                $incident->logAlert('incident_marked_pending', 'Incident marked as pending for investigation', [
                    'acknowledged_by' => $user->name ?? 'System',
                    'acknowledged_at' => now()->toISOString(),
                    'note' => $request->get('note', 'No additional notes')
                ]);
            } catch (\Exception $logError) {
                Log::warning('Failed to log alert for pending incident', [
                    'incident_id' => $incident->id,
                    'error' => $logError->getMessage()
                ]);
            }

            Log::info('Incident marked as pending', [
                'incident_id' => $incident->id,
                'monitor_id' => $incident->monitor_id,
                'acknowledged_by' => $user->name ?? 'System'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Incident marked as pending',
                'data' => $incident->fresh(['monitor'])
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to mark incident as pending', [
                'incident_id' => $incident->id ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update incident status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark incident as done (resolved)
     */
    public function markDone(Incident $incident, Request $request): JsonResponse
    {
        try {
            // Load monitor relationship if not already loaded
            if (!$incident->relationLoaded('monitor')) {
                $incident->load('monitor');
            }
            
            $user = Auth::user();
            
            $incident->update([
                'status' => 'resolved',  // Changed from 'solved' to 'resolved' to match database enum
                'resolved' => true,
                'ended_at' => now(),
                'acknowledged_by' => $user->name ?? 'System'
            ]);

            // Only log alert if monitor is loaded
            try {
                $incident->logAlert('incident_marked_solved', 'Incident manually solved', [
                    'solved_by' => $user->name ?? 'System',
                    'solved_at' => now()->toISOString(),
                    'resolution_note' => $request->get('note', 'No resolution notes'),
                    'resolution_method' => 'manual'
                ]);
            } catch (\Exception $logError) {
                Log::warning('Failed to log alert for solved incident', [
                    'incident_id' => $incident->id,
                    'error' => $logError->getMessage()
                ]);
            }

            Log::info('Incident marked as solved', [
                'incident_id' => $incident->id,
                'monitor_id' => $incident->monitor_id,
                'solved_by' => $user->name ?? 'System'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Incident marked as solved',
                'data' => $incident->fresh(['monitor'])
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to mark incident as solved', [
                'incident_id' => $incident->id ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to solve incident: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get incident alert log
     */
    public function getAlertLog(Incident $incident): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => [
                    'incident_id' => $incident->id,
                    'alert_log' => $incident->alert_log ?? [],
                    'status' => $incident->status,
                    'alert_status' => $incident->alert_status
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch alert log'
            ], 500);
        }
    }

    /**
     * Acknowledge incident (frontend compatibility)
     */
    public function acknowledge(Incident $incident, Request $request): JsonResponse
    {
        return $this->markPending($incident, $request);
    }

    /**
     * Resolve incident (frontend compatibility)
     */
    public function resolve(Incident $incident, Request $request): JsonResponse
    {
        return $this->markDone($incident, $request);
    }

    /**
     * Reopen incident
     */
    public function reopen(Incident $incident, Request $request): JsonResponse
    {
        try {
            // Load monitor relationship if not already loaded
            if (!$incident->relationLoaded('monitor')) {
                $incident->load('monitor');
            }
            
            $user = Auth::user();
            
            $incident->update([
                'status' => 'open',
                'resolved' => false,
                'ended_at' => null,
                'acknowledged_at' => null,
                'acknowledged_by' => null
            ]);

            // Only log alert if monitor is loaded
            try {
                $incident->logAlert('incident_reopened', 'Incident manually reopened', [
                    'reopened_by' => $user->name ?? 'System',
                    'reopened_at' => now()->toISOString(),
                    'reason' => $request->get('reason', 'No reason specified')
                ]);
            } catch (\Exception $logError) {
                Log::warning('Failed to log alert for reopened incident', [
                    'incident_id' => $incident->id,
                    'error' => $logError->getMessage()
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Incident reopened',
                'data' => $incident->fresh(['monitor'])
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to reopen incident', [
                'incident_id' => $incident->id ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to reopen incident: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add note to incident
     */
    public function addNote(Incident $incident, Request $request): JsonResponse
    {
        try {
            // Load monitor relationship if not already loaded
            if (!$incident->relationLoaded('monitor')) {
                $incident->load('monitor');
            }
            
            $content = $request->get('content');
            $user = Auth::user();
            
            if (empty($content)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Note content is required'
                ], 400);
            }
            
            // Only log alert if monitor is loaded
            try {
                $incident->logAlert('note_added', 'Note added to incident', [
                    'note_content' => $content,
                    'added_by' => $user->name ?? 'System',
                    'added_at' => now()->toISOString()
                ]);
            } catch (\Exception $logError) {
                Log::warning('Failed to log alert for note', [
                    'incident_id' => $incident->id,
                    'error' => $logError->getMessage()
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Note added successfully',
                'data' => $incident->fresh(['monitor'])
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to add note', [
                'incident_id' => $incident->id ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to add note: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete an incident
     */
    public function destroy(Incident $incident): JsonResponse
    {
        try {
            Log::info('Deleting incident', [
                'incident_id' => $incident->id,
                'monitor_id' => $incident->monitor_id,
                'status' => $incident->status
            ]);

            // Delete the incident
            $incident->delete();

            return response()->json([
                'success' => true,
                'message' => 'Incident deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to delete incident', [
                'incident_id' => $incident->id ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete incident: ' . $e->getMessage()
            ], 500);
        }
    }
}
