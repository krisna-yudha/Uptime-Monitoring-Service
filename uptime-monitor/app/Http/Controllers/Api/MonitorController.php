<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Monitor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Exception;

class MonitorController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Monitor::with(['creator:id,name,email'])
            ->when(auth('api')->user()->role !== 'admin', function ($q) {
                return $q->where('created_by', auth('api')->id());
            });

        // Filter by status
        if ($request->has('status')) {
            $query->where('last_status', $request->status);
        }

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter by enabled status
        if ($request->has('enabled')) {
            $query->where('enabled', $request->boolean('enabled'));
        }

        // Search by name
        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        // Filter by group
        if ($request->has('group')) {
            if ($request->group === 'ungrouped') {
                $query->whereNull('group_name');
            } else {
                $query->where('group_name', $request->group);
            }
        }

        $monitors = $query->latest()->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $monitors
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'group_name' => 'nullable|string|max:255',
            'group_description' => 'nullable|string|max:500',
            'group_config' => 'nullable|array',
            'type' => 'required|in:http,https,tcp,ping,keyword,push',
            'target' => 'required|string',
            'port' => 'nullable|integer|min:1|max:65535',
            'port_number' => 'nullable|integer|min:1|max:65535',
            'interval_seconds' => 'sometimes|integer|min:1|max:3600',
            'timeout_ms' => 'sometimes|integer|min:1000|max:30000',
            'retries' => 'sometimes|integer|min:1|max:5',
            'enabled' => 'sometimes|boolean',
            'is_public' => 'sometimes|boolean',
            'config' => 'sometimes|array',
            'tags' => 'sometimes|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();
        if (!isset($data['port']) && isset($data['port_number'])) {
            $data['port'] = $data['port_number'];
        }
        unset($data['port_number']);
        $data['created_by'] = auth('api')->id();
        
        // Set default interval to 1 second for realtime monitoring if not provided
        if (!isset($data['interval_seconds'])) {
            $data['interval_seconds'] = 1;
        }

        // Coerce config/tags/notification_channels if they're provided as JSON strings
        if (isset($data['config']) && is_string($data['config'])) {
            $decoded = json_decode($data['config'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $data['config'] = $decoded;
            }
        }

        if (isset($data['tags']) && is_string($data['tags'])) {
            // Accept either JSON array string or comma-separated string
            $decoded = json_decode($data['tags'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $data['tags'] = $decoded;
            } else {
                $data['tags'] = array_filter(array_map('trim', explode(',', $data['tags'])));
            }
        }

        if (isset($data['notification_channels']) && is_string($data['notification_channels'])) {
            $decoded = json_decode($data['notification_channels'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $data['notification_channels'] = $decoded;
            }
        }
        
        // Generate heartbeat key for push monitors
        if ($data['type'] === 'push') {
            $data['heartbeat_key'] = Str::random(128);
        }

        $monitor = Monitor::create($data);
        $monitor->load('creator:id,name,email');

        // Execute initial monitor check with timeout optimization
        // Use queue with high priority for faster processing without blocking response
        try {
            // Dispatch to high-priority queue for immediate processing
            \App\Jobs\ProcessMonitorCheck::dispatch($monitor)
                ->onQueue('monitor-checks-priority')
                ->afterCommit();
            
            Log::info('Initial monitor check dispatched to priority queue', [
                'monitor_id' => $monitor->id,
                'type' => $monitor->type,
                'target' => $monitor->target
            ]);
        } catch (\Exception $e) {
            // Log error but don't fail the creation
            Log::warning('Failed to dispatch initial monitor check', [
                'monitor_id' => $monitor->id,
                'error' => $e->getMessage()
            ]);
        }

        // Return immediately without waiting for check to complete
        // Frontend will refresh to get updated status
        return response()->json([
            'success' => true,
            'message' => 'Monitor created successfully. Initial check is being processed.',
            'data' => $monitor
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Monitor $monitor): JsonResponse
    {
        // Check if user can access this monitor
        if (auth('api')->user()->role !== 'admin' && $monitor->created_by !== auth('api')->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        $monitor->load([
            'creator:id,name,email',
            'checks' => function ($query) {
                $query->latest()->limit(10);
            },
            'incidents' => function ($query) {
                $query->latest()->limit(5);
            }
        ]);

        return response()->json([
            'success' => true,
            'data' => $monitor
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Monitor $monitor): JsonResponse
    {
        // Check if user can update this monitor
        if (auth('api')->user()->role !== 'admin' && $monitor->created_by !== auth('api')->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'type' => 'sometimes|in:http,https,tcp,ping,keyword,push',
            'target' => 'sometimes|string',
            'port' => 'nullable|integer|min:1|max:65535',
            'port_number' => 'nullable|integer|min:1|max:65535',
            'interval_seconds' => 'sometimes|integer|min:1|max:3600',
            'timeout_ms' => 'sometimes|integer|min:1000|max:30000',
            'retries' => 'sometimes|integer|min:1|max:5',
            'enabled' => 'sometimes|boolean',
            'is_public' => 'sometimes|boolean',
            'config' => 'sometimes|array',
            'tags' => 'sometimes|array',
            'group_name' => 'sometimes|nullable|string|max:255',
            'group_description' => 'sometimes|nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();
        if (!isset($data['port']) && isset($data['port_number'])) {
            $data['port'] = $data['port_number'];
        }
        unset($data['port_number']);

        $monitor->update($data);
        $monitor->load('creator:id,name,email');
        // If important fields changed (target/type/config), trigger an immediate re-validation
        $importantChanged = $monitor->wasChanged('target') || $monitor->wasChanged('type') || $monitor->wasChanged('config');

        if ($importantChanged) {
            // Reset last_status so validation path runs on next check
            try {
                $monitor->update(['last_status' => null]);
            } catch (\Exception $e) {
                // Ignore update failures here
                Log::debug('Failed to reset last_status after monitor update: ' . $e->getMessage(), ['monitor_id' => $monitor->id]);
            }

            try {
                \App\Jobs\ProcessMonitorCheck::dispatch($monitor)->afterCommit();
            } catch (\Exception $e) {
                Log::warning('Failed to dispatch monitor check after update', ['monitor_id' => $monitor->id, 'error' => $e->getMessage()]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Monitor updated successfully',
            'data' => $monitor
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Monitor $monitor): JsonResponse
    {
        // Check if user can delete this monitor
        if (auth('api')->user()->role !== 'admin' && $monitor->created_by !== auth('api')->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        $monitor->delete();

        return response()->json([
            'success' => true,
            'message' => 'Monitor deleted successfully'
        ]);
    }

    /**
     * Pause monitor for specified duration
     */
    public function pause(Request $request, Monitor $monitor): JsonResponse
    {
        if (auth('api')->user()->role !== 'admin' && $monitor->created_by !== auth('api')->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'duration_minutes' => 'required|integer|min:1|max:10080' // max 7 days
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $monitor->update([
            'pause_until' => now()->addMinutes($request->duration_minutes)
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Monitor paused successfully',
            'data' => $monitor
        ]);
    }

    /**
     * Resume paused monitor
     */
    public function resume(Monitor $monitor): JsonResponse
    {
        if (auth('api')->user()->role !== 'admin' && $monitor->created_by !== auth('api')->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        $monitor->update(['pause_until' => null]);

        return response()->json([
            'success' => true,
            'message' => 'Monitor resumed successfully',
            'data' => $monitor
        ]);
    }

    /**
     * Get all monitor groups with statistics
     */
    public function groups(Request $request): JsonResponse
    {
        try {
            $groups = [];
            
            // Get distinct groups
            $distinctGroups = Monitor::whereNotNull('group_name')
                ->select('group_name', 'group_description')
                ->distinct()
                ->get();
            
            foreach ($distinctGroups as $group) {
                $monitors = Monitor::where('group_name', $group->group_name)->get();
                
                $groups[] = [
                    'group_name' => $group->group_name,
                    'group_description' => $group->group_description,
                    'monitors_count' => $monitors->count(),
                    'up_count' => $monitors->where('last_status', 'up')->count(),
                    'down_count' => $monitors->where('last_status', 'down')->count(),
                    'invalid_count' => $monitors->where('last_status', 'invalid')->count(),
                    'unknown_count' => $monitors->where('last_status', 'unknown')->count(),
                    'health_percentage' => $monitors->count() > 0 
                        ? round(($monitors->where('last_status', 'up')->count() / $monitors->count()) * 100, 1)
                        : 0
                ];
            }

            // Add ungrouped monitors count if any exist
            $ungroupedCount = Monitor::whereNull('group_name')->count();
            if ($ungroupedCount > 0) {
                $ungroupedMonitors = Monitor::whereNull('group_name')->get();
                
                $groups[] = [
                    'group_name' => 'ungrouped',
                    'group_description' => 'Monitors without group',
                    'monitors_count' => $ungroupedMonitors->count(),
                    'up_count' => $ungroupedMonitors->where('last_status', 'up')->count(),
                    'down_count' => $ungroupedMonitors->where('last_status', 'down')->count(),
                    'invalid_count' => $ungroupedMonitors->where('last_status', 'invalid')->count(),
                    'unknown_count' => $ungroupedMonitors->where('last_status', 'unknown')->count(),
                    'health_percentage' => $ungroupedMonitors->count() > 0 
                        ? round(($ungroupedMonitors->where('last_status', 'up')->count() / $ungroupedMonitors->count()) * 100, 1)
                        : 0
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $groups
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch groups: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get monitors grouped by group name
     */
    public function grouped(Request $request): JsonResponse
    {
        try {
            $grouped = [];
            
            // Get monitors with groups
            $groupedMonitors = Monitor::whereNotNull('group_name')
                ->orderBy('group_name')
                ->orderBy('name')
                ->get()
                ->groupBy('group_name');
                
            foreach ($groupedMonitors as $groupName => $monitors) {
                $firstMonitor = $monitors->first();
                $grouped[$groupName] = [
                    'name' => $groupName,
                    'description' => $firstMonitor->group_description,
                    'monitors' => $monitors->values()->toArray()
                ];
            }
            
            // Get ungrouped monitors
            $ungroupedMonitors = Monitor::whereNull('group_name')
                ->orderBy('name')
                ->get();
                
            if ($ungroupedMonitors->count() > 0) {
                $grouped['Ungrouped'] = [
                    'name' => 'Ungrouped',
                    'description' => null,
                    'monitors' => $ungroupedMonitors->toArray()
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $grouped
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch grouped monitors: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk operations on monitors
     */
    public function bulkAction(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'action' => 'required|in:enable,disable,pause,resume,delete,change_group',
            'monitor_ids' => 'required|array|min:1',
            'monitor_ids.*' => 'exists:monitors,id',
            'group_name' => 'required_if:action,change_group|nullable|string|max:255',
            'group_description' => 'nullable|string|max:500',
            'pause_duration' => 'required_if:action,pause|integer|min:1|max:10080' // max 7 days in minutes
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();
        $monitorIds = $data['monitor_ids'];
        $action = $data['action'];

        // Check authorization for all monitors
        $monitors = Monitor::whereIn('id', $monitorIds);
        if (auth('api')->user()->role !== 'admin') {
            $monitors->where('created_by', auth('api')->id());
        }
        $monitors = $monitors->get();

        if ($monitors->count() !== count($monitorIds)) {
            return response()->json([
                'success' => false,
                'message' => 'Some monitors not found or unauthorized'
            ], 403);
        }

        // Perform bulk action
        $updated = 0;
        foreach ($monitors as $monitor) {
            switch ($action) {
                case 'enable':
                    $monitor->update(['enabled' => true]);
                    $updated++;
                    break;
                case 'disable':
                    $monitor->update(['enabled' => false]);
                    $updated++;
                    break;
                case 'pause':
                    $monitor->update(['pause_until' => now()->addMinutes($data['pause_duration'])]);
                    $updated++;
                    break;
                case 'resume':
                    $monitor->update(['pause_until' => null]);
                    $updated++;
                    break;
                case 'change_group':
                    $monitor->update([
                        'group_name' => $data['group_name'],
                        'group_description' => $data['group_description'] ?? null
                    ]);
                    $updated++;
                    break;
                case 'delete':
                    $monitor->delete();
                    $updated++;
                    break;
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Successfully {$action}d {$updated} monitors",
            'updated_count' => $updated
        ]);
    }
}
