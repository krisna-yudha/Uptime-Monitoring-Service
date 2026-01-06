<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== MONITORING STATUS CHECK ===\n\n";

// Check recent monitor checks
$recentChecks = DB::table('monitor_checks')
    ->orderBy('id', 'desc')
    ->limit(10)
    ->get(['id', 'monitor_id', 'status', 'checked_at', 'latency_ms']);

if ($recentChecks->count() > 0) {
    echo "📊 Recent Monitor Checks (Last 10):\n";
    foreach ($recentChecks as $check) {
        $monitor = DB::table('monitors')->where('id', $check->monitor_id)->value('name');
        $time = date('H:i:s', strtotime($check->checked_at));
        $latency = $check->latency_ms ? $check->latency_ms . 'ms' : 'N/A';
        echo "  - {$monitor}: {$check->status} at {$time} ({$latency})\n";
    }
} else {
    echo "⚠️ No monitor checks found!\n";
}

echo "\n";

// Check monitors configuration
$monitors = DB::table('monitors')
    ->where('enabled', true)
    ->get(['id', 'name', 'interval_seconds', 'next_check_at', 'last_checked_at']);

echo "📋 Enabled Monitors Configuration:\n";
foreach ($monitors as $monitor) {
    $nextCheck = $monitor->next_check_at ? date('Y-m-d H:i:s', strtotime($monitor->next_check_at)) : 'Not scheduled';
    $lastCheck = $monitor->last_checked_at ? date('Y-m-d H:i:s', strtotime($monitor->last_checked_at)) : 'Never';
    $now = date('Y-m-d H:i:s');
    $isDue = $monitor->next_check_at && strtotime($monitor->next_check_at) <= strtotime($now);
    
    echo "  - {$monitor->name}:\n";
    echo "    Interval: {$monitor->interval_seconds}s\n";
    echo "    Last check: {$lastCheck}\n";
    echo "    Next check: {$nextCheck}\n";
    echo "    Status: " . ($isDue ? "🔴 DUE NOW" : "🟢 Scheduled") . "\n";
    echo "\n";
}

// Check current time
echo "🕐 Current Time: " . date('Y-m-d H:i:s') . "\n";
