<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MonitorCheck;
use Illuminate\Http\Request;

class MonitorCheckController extends Controller
{
    /**
     * Display a listing of monitor checks.
     */
    public function index(Request $request)
    {
        // Optimize: Only select needed columns to reduce memory usage
        $query = MonitorCheck::select('id', 'monitor_id', 'checked_at', 'status', 'latency_ms', 'error_message', 'http_status');

        // Filter by monitor_id (required for performance)
        if ($request->has('monitor_id')) {
            $query->where('monitor_id', $request->monitor_id);
        } else {
            // If no monitor_id, limit to prevent huge queries
            $query->where('checked_at', '>=', now()->subDays(7));
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by date range
        if ($request->has('from_date')) {
            $query->where('checked_at', '>=', $request->from_date);
        }
        if ($request->has('to_date')) {
            $query->where('checked_at', '<=', $request->to_date);
        }

        // Sorting - optimize with index
        $sortBy = $request->get('sort', 'checked_at');
        $order = $request->get('order', 'desc');
        
        // Ensure sort column is valid
        $allowedSorts = ['checked_at', 'status', 'latency_ms', 'id'];
        if (!in_array($sortBy, $allowedSorts)) {
            $sortBy = 'checked_at';
        }
        
        $query->orderBy($sortBy, $order);

        // Pagination with reasonable limits
        $perPage = min($request->get('per_page', 15), 500); // Max 500 per page
        $checks = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Monitor checks retrieved successfully',
            'data' => $checks
        ]);
    }

    /**
     * Display the specified monitor check.
     */
    public function show(string $id)
    {
        $check = MonitorCheck::with('monitor')->find($id);

        if (!$check) {
            return response()->json([
                'success' => false,
                'message' => 'Monitor check not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Monitor check retrieved successfully',
            'data' => $check
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
