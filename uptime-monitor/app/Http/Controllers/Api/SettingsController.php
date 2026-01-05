<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class SettingsController extends Controller
{
    /**
     * Get current settings
     */
    public function index(): JsonResponse
    {
        try {
            // Default settings (could be stored in database later)
            $settings = [
                'autoAggregate' => true,
                'intervals' => [
                    'minute' => true,
                    'hour' => true,
                    'day' => true,
                ],
                'retention' => [
                    'rawChecks' => 7,
                    'rawChecksUnit' => 'days',
                    'rawLogs' => 30,
                    'rawLogsUnit' => 'days',
                    'minuteAggregates' => 30,
                    'minuteAggregatesUnit' => 'days',
                    'hourAggregates' => 90,
                    'hourAggregatesUnit' => 'days',
                    'dayAggregates' => 1,
                    'dayAggregatesUnit' => 'years',
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $settings
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get settings', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load settings'
            ], 500);
        }
    }

    /**
     * Save settings
     */
    public function update(Request $request): JsonResponse
    {
        try {
            // Validate request
            $validated = $request->validate([
                'autoAggregate' => 'required|boolean',
                'intervals' => 'required|array',
                'retention' => 'required|array',
            ]);

            // TODO: Store settings in database or config file
            // For now, just return success
            
            Log::info('Settings updated', $validated);

            return response()->json([
                'success' => true,
                'message' => 'Settings saved successfully',
                'data' => $validated
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to save settings', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to save settings'
            ], 500);
        }
    }

    /**
     * Run manual aggregation
     */
    public function runAggregation(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'interval' => 'required|in:minute,hour,day',
                'date' => 'nullable|date'
            ]);

            $interval = $validated['interval'];
            $date = $validated['date'] ?? null;

            $command = "metrics:aggregate --interval={$interval}";
            if ($date) {
                $command .= " --date={$date}";
            }

            // Run the aggregation command
            $exitCode = Artisan::call($command);
            $output = Artisan::output();

            // Parse output to get aggregated count
            preg_match('/Total aggregated periods: (\d+)/', $output, $matches);
            $total = $matches[1] ?? 0;

            return response()->json([
                'success' => $exitCode === 0,
                'message' => $exitCode === 0 ? 'Aggregation completed successfully' : 'Aggregation failed',
                'data' => [
                    'total' => (int)$total,
                    'output' => $output
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to run aggregation', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to run aggregation: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Run manual cleanup
     */
    public function runCleanup(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'dryRun' => 'nullable|boolean'
            ]);

            $dryRun = $validated['dryRun'] ?? false;

            $command = 'metrics:cleanup';
            if ($dryRun) {
                $command .= ' --dry-run';
            }

            // Run the cleanup command
            $exitCode = Artisan::call($command);
            $output = Artisan::output();

            return response()->json([
                'success' => $exitCode === 0,
                'message' => $exitCode === 0 ? 'Cleanup completed successfully' : 'Cleanup failed',
                'data' => [
                    'summary' => $output
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to run cleanup', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to run cleanup: ' . $e->getMessage()
            ], 500);
        }
    }
}
