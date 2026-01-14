<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Monitor;
use App\Models\MonitorCheck;
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
            'tags' => 'sometimes|array',
            'notification_channel_ids' => 'sometimes|array',
            'notification_channel_ids.*' => 'integer|exists:notification_channels,id'
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
        
        // Merge port into target if provided
        if (isset($data['port']) && $data['port']) {
            $target = $data['target'];
            $port = $data['port'];
            
            if ($data['type'] === 'tcp' || $data['type'] === 'port') {
                // For TCP: format as host:port
                $targetParts = explode(':', $target);
                $host = $targetParts[0];
                $data['target'] = $host . ':' . $port;
            } elseif ($data['type'] === 'http' || $data['type'] === 'https') {
                // For HTTP/HTTPS: ensure URL has port
                // Parse URL to add port properly
                if (!preg_match('/^https?:\/\//', $target)) {
                    // Add protocol if missing
                    $target = ($data['type'] === 'https' ? 'https://' : 'http://') . $target;
                }
                
                $urlParts = parse_url($target);
                if ($urlParts) {
                    $scheme = $urlParts['scheme'] ?? ($data['type'] === 'https' ? 'https' : 'http');
                    $host = $urlParts['host'] ?? $target;
                    $path = $urlParts['path'] ?? '';
                    $query = isset($urlParts['query']) ? '?' . $urlParts['query'] : '';
                    
                    $data['target'] = $scheme . '://' . $host . ':' . $port . $path . $query;
                }
            }
            
            Log::info('Merged port into target', [
                'type' => $data['type'],
                'port' => $port,
                'original_target' => $target,
                'merged_target' => $data['target']
            ]);
        }
        
        // Handle admin shared ownership
        $currentUser = auth('api')->user();
        if ($currentUser->role === 'admin') {
            // For admin users, set created_by to ID 1 (shared ownership)
            // But keep actual_created_by as the real admin who created it
            $data['created_by'] = 1; // Shared admin ownership
            $data['actual_created_by'] = $currentUser->id; // Real creator
        } else {
            // For regular users, they own their monitors
            $data['created_by'] = $currentUser->id;
            $data['actual_created_by'] = null;
        }
        
        // Set default interval to 1 second for realtime monitoring if not provided
        if (!isset($data['interval_seconds'])) {
            $data['interval_seconds'] = 1;
        }

        // Auto-fetch favicon/icon for HTTP/HTTPS monitors if not provided
        if (!isset($data['icon_url']) && in_array($data['type'], ['http', 'https', 'keyword'])) {
            try {
                $iconUrl = \App\Jobs\ProcessMonitorCheck::getFaviconUrl($data['target']);
                if ($iconUrl) {
                    $data['icon_url'] = $iconUrl;
                }
            } catch (\Exception $e) {
                // Continue without icon if fetch fails
                Log::debug('Failed to auto-fetch icon for monitor', ['error' => $e->getMessage()]);
            }
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

        // Handle notification_channel_ids from frontend (array format)
        if (isset($data['notification_channel_ids'])) {
            $data['notification_channels'] = $data['notification_channel_ids'];
            unset($data['notification_channel_ids']);
        } elseif (isset($data['notification_channels']) && is_string($data['notification_channels'])) {
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
        $currentUser = auth('api')->user();
        
        // Admin can access all monitors (including orphaned ones with created_by = null)
        // Regular users can only access their own monitors
        if ($currentUser->role !== 'admin' && 
            $monitor->created_by !== null && 
            $monitor->created_by !== $currentUser->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        $monitor->load([
            'creator:id,name,email',
            'actualCreator:id,name,email',
            'checks' => function ($query) {
                $query->latest()->limit(10);
            },
            'incidents' => function ($query) {
                $query->latest()->limit(5);
            }
        ]);

        // Extract config fields to top-level for frontend compatibility
        $monitorData = $monitor->toArray();
        
        if (!empty($monitor->config)) {
            // HTTP specific fields
            $monitorData['http_method'] = $monitor->config['http_method'] ?? 'GET';
            $monitorData['http_headers'] = $monitor->config['http_headers'] ?? null;
            $monitorData['http_body'] = $monitor->config['http_body'] ?? null;
            $monitorData['http_expected_status_codes'] = $monitor->config['http_expected_status_codes'] ?? '';
            $monitorData['http_follow_redirects'] = $monitor->config['http_follow_redirects'] ?? true;
            $monitorData['http_verify_ssl'] = $monitor->config['http_verify_ssl'] ?? true;
            
            // Keyword specific fields
            $monitorData['keyword_text'] = $monitor->config['keyword_text'] ?? '';
            $monitorData['keyword_case_sensitive'] = $monitor->config['keyword_case_sensitive'] ?? false;
            
            // Heartbeat specific fields
            $monitorData['heartbeat_grace_period_minutes'] = $monitor->config['heartbeat_grace_period_minutes'] ?? 5;
        }
        
        // Convert timeout_ms to timeout_seconds for frontend
        if (isset($monitorData['timeout_ms'])) {
            $monitorData['timeout_seconds'] = intval($monitorData['timeout_ms'] / 1000);
        }
        
        // Map enabled to is_enabled for frontend
        $monitorData['is_enabled'] = $monitor->enabled ?? true;
        
        // Map retries to retry_count for frontend
        $monitorData['retry_count'] = $monitor->retries ?? 3;

        // Calculate average response time for different periods from monitor creation time
        $createdAt = $monitor->created_at;
        $now = now();
        
        // 1 hour average
        $avg1h = MonitorCheck::where('monitor_id', $monitor->id)
            ->where('checked_at', '>=', max($createdAt, $now->copy()->subHour()))
            ->where('status', 'up')
            ->whereNotNull('latency_ms')
            ->avg('latency_ms');
        $monitorData['avg_response_1h'] = $avg1h ? round($avg1h, 2) : null;
        
        // 24 hour average
        $avg24h = MonitorCheck::where('monitor_id', $monitor->id)
            ->where('checked_at', '>=', max($createdAt, $now->copy()->subDay()))
            ->where('status', 'up')
            ->whereNotNull('latency_ms')
            ->avg('latency_ms');
        $monitorData['avg_response_24h'] = $avg24h ? round($avg24h, 2) : null;
        
        // 7 days average
        $avg7d = MonitorCheck::where('monitor_id', $monitor->id)
            ->where('checked_at', '>=', max($createdAt, $now->copy()->subDays(7)))
            ->where('status', 'up')
            ->whereNotNull('latency_ms')
            ->avg('latency_ms');
        $monitorData['avg_response_7d'] = $avg7d ? round($avg7d, 2) : null;
        
        // 30 days average
        $avg30d = MonitorCheck::where('monitor_id', $monitor->id)
            ->where('checked_at', '>=', max($createdAt, $now->copy()->subDays(30)))
            ->where('status', 'up')
            ->whereNotNull('latency_ms')
            ->avg('latency_ms');
        $monitorData['avg_response_30d'] = $avg30d ? round($avg30d, 2) : null;
        
        // All-time average (from creation)
        $avgAllTime = MonitorCheck::where('monitor_id', $monitor->id)
            ->where('status', 'up')
            ->whereNotNull('latency_ms')
            ->avg('latency_ms');
        $monitorData['avg_response_all_time'] = $avgAllTime ? round($avgAllTime, 2) : null;

        return response()->json([
            'success' => true,
            'data' => $monitorData
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Monitor $monitor): JsonResponse
    {
        // Check if user can update this monitor
        $currentUser = auth('api')->user();
        
        // Admin can update all monitors (including orphaned ones with created_by = null)
        // Regular users can only update their own monitors
        if ($currentUser->role !== 'admin' && 
            $monitor->created_by !== null && 
            $monitor->created_by !== $currentUser->id) {
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
            'timeout_seconds' => 'sometimes|integer|min:1|max:300',
            'retries' => 'sometimes|integer|min:1|max:5',
            'retry_count' => 'sometimes|integer|min:0|max:5',
            'enabled' => 'sometimes|boolean',
            'is_enabled' => 'sometimes|boolean',
            'is_public' => 'sometimes|boolean',
            'config' => 'sometimes|array',
            'tags' => 'sometimes|array',
            'group_name' => 'sometimes|nullable|string|max:255',
            'group_description' => 'sometimes|nullable|string',
            'notification_channel_ids' => 'sometimes|array',
            'notification_channel_ids.*' => 'integer|exists:notification_channels,id',
            // HTTP specific fields
            'http_method' => 'sometimes|in:GET,POST,PUT,DELETE,HEAD,PATCH',
            'http_headers' => 'sometimes|nullable',
            'http_body' => 'sometimes|nullable|string',
            'http_expected_status_codes' => 'sometimes|nullable|string',
            'http_follow_redirects' => 'sometimes|boolean',
            'http_verify_ssl' => 'sometimes|boolean',
            // Keyword specific fields
            'keyword_text' => 'sometimes|nullable|string',
            'keyword_case_sensitive' => 'sometimes|boolean',
            // Heartbeat specific fields
            'heartbeat_grace_period_minutes' => 'sometimes|integer|min:1'
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
        
        // Handle field name conversions from frontend
        if (isset($data['is_enabled'])) {
            $data['enabled'] = $data['is_enabled'];
            unset($data['is_enabled']);
        }
        
        if (isset($data['timeout_seconds'])) {
            $data['timeout_ms'] = $data['timeout_seconds'] * 1000;
            unset($data['timeout_seconds']);
        }
        
        if (isset($data['retry_count'])) {
            $data['retries'] = $data['retry_count'];
            unset($data['retry_count']);
        }
        
        // Handle notification_channel_ids if provided
        if (isset($data['notification_channel_ids'])) {
            $data['notification_channels'] = $data['notification_channel_ids'];
            unset($data['notification_channel_ids']);
        }
        
        // Build config array from HTTP/Keyword/Heartbeat specific fields
        $config = $monitor->config ?? [];
        
        // HTTP specific fields
        if (isset($data['http_method'])) {
            $config['http_method'] = $data['http_method'];
            unset($data['http_method']);
        }
        if (isset($data['http_headers'])) {
            // Parse if it's a JSON string, otherwise use as-is
            if (is_string($data['http_headers']) && !empty($data['http_headers'])) {
                $parsed = json_decode($data['http_headers'], true);
                $config['http_headers'] = (json_last_error() === JSON_ERROR_NONE) ? $parsed : [];
            } elseif (is_array($data['http_headers'])) {
                $config['http_headers'] = $data['http_headers'];
            }
            unset($data['http_headers']);
        }
        if (isset($data['http_body'])) {
            $config['http_body'] = $data['http_body'];
            unset($data['http_body']);
        }
        if (isset($data['http_expected_status_codes'])) {
            $config['http_expected_status_codes'] = $data['http_expected_status_codes'];
            unset($data['http_expected_status_codes']);
        }
        if (isset($data['http_follow_redirects'])) {
            $config['http_follow_redirects'] = $data['http_follow_redirects'];
            unset($data['http_follow_redirects']);
        }
        if (isset($data['http_verify_ssl'])) {
            $config['http_verify_ssl'] = $data['http_verify_ssl'];
            unset($data['http_verify_ssl']);
        }
        
        // Keyword specific fields
        if (isset($data['keyword_text'])) {
            $config['keyword_text'] = $data['keyword_text'];
            unset($data['keyword_text']);
        }
        if (isset($data['keyword_case_sensitive'])) {
            $config['keyword_case_sensitive'] = $data['keyword_case_sensitive'];
            unset($data['keyword_case_sensitive']);
        }
        
        // Heartbeat specific fields
        if (isset($data['heartbeat_grace_period_minutes'])) {
            $config['heartbeat_grace_period_minutes'] = $data['heartbeat_grace_period_minutes'];
            unset($data['heartbeat_grace_period_minutes']);
        }
        
        // Update config if any specific fields were set
        if (!empty($config)) {
            $data['config'] = $config;
        }
        
        // Merge port into target if provided
        if (isset($data['port']) && $data['port']) {
            $monitorType = $data['type'] ?? $monitor->type;
            $target = $data['target'] ?? $monitor->target;
            $port = $data['port'];
            
            if ($monitorType === 'tcp' || $monitorType === 'port') {
                // For TCP: format as host:port
                $targetParts = explode(':', $target);
                $host = $targetParts[0];
                $data['target'] = $host . ':' . $port;
            } elseif ($monitorType === 'http' || $monitorType === 'https') {
                // For HTTP/HTTPS: ensure URL has port
                // Parse URL to add port properly
                if (!preg_match('/^https?:\/\//', $target)) {
                    // Add protocol if missing
                    $target = ($monitorType === 'https' ? 'https://' : 'http://') . $target;
                }
                
                $urlParts = parse_url($target);
                if ($urlParts) {
                    $scheme = $urlParts['scheme'] ?? ($monitorType === 'https' ? 'https' : 'http');
                    $host = $urlParts['host'] ?? $target;
                    $path = $urlParts['path'] ?? '';
                    $query = isset($urlParts['query']) ? '?' . $urlParts['query'] : '';
                    
                    $data['target'] = $scheme . '://' . $host . ':' . $port . $path . $query;
                }
            }
            
            Log::info('Merged port into target on update', [
                'monitor_id' => $monitor->id,
                'type' => $monitorType,
                'port' => $port,
                'original_target' => $target,
                'merged_target' => $data['target']
            ]);
        }

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
        $currentUser = auth('api')->user();
        
        // Admin can delete all monitors (including orphaned ones with created_by = null)
        // Regular users can only delete their own monitors
        if ($currentUser->role !== 'admin' && 
            $monitor->created_by !== null && 
            $monitor->created_by !== $currentUser->id) {
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
     * Trigger immediate check for a monitor
     */
    public function triggerCheck(Monitor $monitor): JsonResponse
    {
        if (auth('api')->user()->role !== 'admin' && $monitor->created_by !== auth('api')->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        try {
            // Dispatch job to queue for immediate processing
            \App\Jobs\ProcessMonitorCheck::dispatch($monitor)->onQueue('high-priority');

            return response()->json([
                'success' => true,
                'message' => 'Check triggered successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to trigger monitor check', [
                'monitor_id' => $monitor->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to trigger check: ' . $e->getMessage()
            ], 500);
        }
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

    /**
     * Bulk assign notification channels to monitors
     */
    public function bulkAssignNotifications(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'monitor_ids' => 'required|array|min:1',
            'monitor_ids.*' => 'exists:monitors,id',
            'notification_channel_ids' => 'required|array|min:1',
            'notification_channel_ids.*' => 'exists:notification_channels,id',
            'mode' => 'in:replace,append', // replace = overwrite existing, append = add to existing
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
        $notificationChannelIds = $data['notification_channel_ids'];
        $mode = $data['mode'] ?? 'replace';

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

        // Update notification channels for each monitor
        $updated = 0;
        foreach ($monitors as $monitor) {
            if ($mode === 'replace') {
                // Replace all existing notification channels
                $monitor->update([
                    'notification_channels' => $notificationChannelIds
                ]);
            } else {
                // Append to existing notification channels
                $existing = $monitor->notification_channels ?? [];
                $merged = array_unique(array_merge($existing, $notificationChannelIds));
                $monitor->update([
                    'notification_channels' => $merged
                ]);
            }
            $updated++;
        }

        return response()->json([
            'success' => true,
            'message' => "Successfully assigned notifications to {$updated} monitor(s)",
            'updated_count' => $updated,
            'mode' => $mode
        ]);
    }
}
