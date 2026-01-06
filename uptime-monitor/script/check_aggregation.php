<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\MonitorMetricAggregated;
use App\Models\MonitorCheck;

echo "\n=== Data Aggregation Status ===\n\n";

// Raw data
$rawCount = MonitorCheck::count();
echo "📊 Raw checks in database: " . number_format($rawCount) . "\n";

// Aggregated data
$aggCount = MonitorMetricAggregated::count();
echo "✅ Aggregated records: " . number_format($aggCount) . "\n\n";

// Reduction percentage
if ($rawCount > 0) {
    $reduction = (1 - ($aggCount / $rawCount)) * 100;
    echo "💾 Data reduction: " . number_format($reduction, 2) . "%\n\n";
}

// Latest aggregates
echo "=== Latest 5 Aggregated Records ===\n\n";
$latest = MonitorMetricAggregated::with('monitor')
    ->orderBy('period_start', 'desc')
    ->limit(5)
    ->get();

foreach ($latest as $agg) {
    printf(
        "Monitor: %-20s | Time: %s | Checks: %2d/%2d | Uptime: %5.1f%% | Avg RT: %4.0fms\n",
        substr($agg->monitor->name, 0, 20),
        $agg->period_start->format('Y-m-d H:i'),
        $agg->successful_checks,
        $agg->total_checks,
        $agg->uptime_percentage,
        $agg->avg_response_time
    );
}

echo "\n";
