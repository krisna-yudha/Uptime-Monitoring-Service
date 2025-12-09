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
        $query = MonitorCheck::query();

        // Filter by monitor_id
        if ($request->has('monitor_id')) {
            $query->where('monitor_id', $request->monitor_id);
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

        // Sorting
        $sortBy = $request->get('sort', 'checked_at');
        $order = $request->get('order', 'desc');
        $query->orderBy($sortBy, $order);

        // Pagination
        $perPage = $request->get('per_page', 15);
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
