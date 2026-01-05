<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Monitor;
use App\Models\MonitorMetricAggregated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PublicMonitorController extends Controller
{
    /**
     * Get all public monitors with their current status
     */
    public function index()
    {
        try {
            $monitors = Monitor::where('is_public', true)
                ->where('enabled', true)
                ->select('id', 'name', 'target', 'type', 'group_name', 'last_status', 'last_checked_at', 'created_at')
                ->orderBy('group_name')
                ->orderBy('name')
                ->get();

            // Group monitors by group name
            $grouped = $monitors->groupBy('group_name')->map(function ($items, $groupName) {
                return [
                    'group' => $groupName ?: 'Default',
                    'monitors' => $items->map(function ($monitor) {
                        return [
                            'id' => $monitor->id,
                            'name' => $monitor->name,
                            'target' => $monitor->target,
                            'type' => $monitor->type,
                            'status' => $monitor->last_status,
                            'last_check_at' => $monitor->last_checked_at,
                            'uptime_percentage' => 99.9, // TODO: Calculate from metrics
                        ];
                    })->values()
                ];
            })->values();

            return response()->json([
                'success' => true,
                'data' => $grouped
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch public monitors: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get public monitor details with statistics
     */
    public function show($id)
    {
        try {
            $monitor = Monitor::where('is_public', true)
                ->where('id', $id)
                ->first();

            if (!$monitor) {
                return response()->json([
                    'success' => false,
                    'message' => 'Monitor not found or not public'
                ], 404);
            }

            // Get 24-hour statistics
            $stats24h = MonitorMetricAggregated::where('monitor_id', $id)
                ->where('period', 'hour')
                ->where('period_start', '>=', now()->subHours(24))
                ->orderBy('period_start', 'desc')
                ->get();

            // Calculate uptime percentage
            $totalChecks = $stats24h->sum('total_checks');
            $successfulChecks = $stats24h->sum('successful_checks');
            $uptimePercentage = $totalChecks > 0 
                ? round(($successfulChecks / $totalChecks) * 100, 2)
                : 0;

            // Get average response time
            $avgResponseTime = $stats24h->avg('avg_response_time');

            // Get recent check history (last 50 checks)
            $recentChecks = DB::table('monitor_checks')
                ->where('monitor_id', $id)
                ->where('checked_at', '>=', now()->subHours(24))
                ->orderBy('checked_at', 'desc')
                ->limit(50)
                ->select('status', 'response_time', 'checked_at')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'monitor' => [
                        'id' => $monitor->id,
                        'name' => $monitor->name,
                        'target' => $monitor->target,
                        'type' => $monitor->type,
                        'group' => $monitor->group_name,
                        'status' => $monitor->last_status,
                        'last_check_at' => $monitor->last_checked_at,
                    ],
                    'statistics' => [
                        'uptime_24h' => $uptimePercentage,
                        'avg_response_time' => round($avgResponseTime, 2),
                        'total_checks' => $totalChecks,
                        'successful_checks' => $successfulChecks,
                        'failed_checks' => $totalChecks - $successfulChecks,
                    ],
                    'hourly_stats' => $stats24h->map(function ($stat) {
                        return [
                            'period_start' => $stat->period_start,
                            'avg_response_time' => round($stat->avg_response_time, 2),
                            'uptime_percentage' => $stat->total_checks > 0 
                                ? round(($stat->successful_checks / $stat->total_checks) * 100, 2)
                                : 0,
                        ];
                    }),
                    'recent_checks' => $recentChecks
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch monitor details: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get overall statistics for all public monitors
     */
    public function statistics()
    {
        try {
            $totalMonitors = Monitor::where('is_public', true)
                ->where('enabled', true)
                ->count();

            $onlineMonitors = Monitor::where('is_public', true)
                ->where('enabled', true)
                ->where('last_status', 'up')
                ->count();

            $offlineMonitors = Monitor::where('is_public', true)
                ->where('enabled', true)
                ->where('last_status', 'down')
                ->count();

            $overallUptime = $totalMonitors > 0 
                ? round(($onlineMonitors / $totalMonitors) * 100, 2)
                : 0;

            return response()->json([
                'success' => true,
                'data' => [
                    'total_monitors' => $totalMonitors,
                    'online' => $onlineMonitors,
                    'offline' => $offlineMonitors,
                    'overall_uptime' => $overallUptime
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch statistics: ' . $e->getMessage()
            ], 500);
        }
    }
}
