<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Monitor;
use App\Models\MonitorCheck;
use Illuminate\Support\Facades\DB;

echo "╔══════════════════════════════════════════════════╗\n";
echo "║     UPTIME MONITORING - SYSTEM STATUS           ║\n";
echo "╚══════════════════════════════════════════════════╝\n\n";

// 1. Queue Status
echo "📊 QUEUE STATUS\n";
echo "─────────────────────────────────────────────────\n";
$jobsCount = DB::table('jobs')->count();
$failedCount = DB::table('failed_jobs')->count();
$queueHealth = $jobsCount < 5000 ? '✅ Healthy' : '⚠️  High';

echo "Active Jobs: {$jobsCount} / 5000 (Limit)\n";
echo "Failed Jobs: {$failedCount}\n";
echo "Status: {$queueHealth}\n\n";

// 2. Monitors Status
echo "🖥️  MONITORS STATUS\n";
echo "─────────────────────────────────────────────────\n";
$totalMonitors = Monitor::count();
$enabledMonitors = Monitor::where('enabled', true)->count();
$upMonitors = Monitor::where('last_status', 'up')->count();
$downMonitors = Monitor::where('last_status', 'down')->count();
$unknownMonitors = Monitor::where('last_status', 'unknown')->count();

echo "Total Monitors: {$totalMonitors}\n";
echo "Enabled: {$enabledMonitors}\n";
echo "├─ UP: {$upMonitors}\n";
echo "├─ DOWN: {$downMonitors}\n";
echo "└─ UNKNOWN: {$unknownMonitors}\n\n";

// 3. Data Collection Status
echo "💾 DATA COLLECTION\n";
echo "─────────────────────────────────────────────────\n";
$totalChecks = MonitorCheck::count();
$checksLast5Min = MonitorCheck::where('checked_at', '>=', now()->subMinutes(5))->count();
$checksLastHour = MonitorCheck::where('checked_at', '>=', now()->subHour())->count();
$checksToday = MonitorCheck::whereDate('checked_at', today())->count();

echo "Total Checks: " . number_format($totalChecks) . "\n";
echo "Last 5 min: {$checksLast5Min}\n";
echo "Last hour: {$checksLastHour}\n";
echo "Today: " . number_format($checksToday) . "\n\n";

// 4. Active Monitoring (Port-specific)
echo "🔌 PORT MONITORING\n";
echo "─────────────────────────────────────────────────\n";
$portMonitors = Monitor::where('target', 'LIKE', '%:%')
    ->where('enabled', true)
    ->get(['id', 'name', 'target', 'last_status', 'last_checked_at']);

foreach ($portMonitors as $monitor) {
    $status = match($monitor->last_status) {
        'up' => '✅',
        'down' => '❌',
        default => '❓'
    };
    $lastCheck = $monitor->last_checked_at 
        ? $monitor->last_checked_at->diffForHumans()
        : 'Never';
    
    echo "{$status} #{$monitor->id} - {$monitor->name}\n";
    echo "   {$monitor->target}\n";
    echo "   Last check: {$lastCheck}\n";
}

if ($portMonitors->isEmpty()) {
    echo "No monitors with custom ports\n";
}

echo "\n";

// 5. System Health
echo "🏥 SYSTEM HEALTH\n";
echo "─────────────────────────────────────────────────\n";

$issues = [];

if ($jobsCount > 5000) {
    $issues[] = "Queue jobs exceeding limit ({$jobsCount}/5000)";
}

if ($failedCount > 100) {
    $issues[] = "High failed jobs count ({$failedCount})";
}

if ($checksLast5Min === 0 && $enabledMonitors > 0) {
    $issues[] = "No checks in last 5 minutes (worker may be down)";
}

if (empty($issues)) {
    echo "✅ All systems operational\n";
} else {
    echo "⚠️  Issues detected:\n";
    foreach ($issues as $issue) {
        echo "   • {$issue}\n";
    }
}

echo "\n";
echo "Last update: " . now()->format('Y-m-d H:i:s') . "\n";
echo "\n╔══════════════════════════════════════════════════╗\n";
echo "║  Run: php artisan queue:cleanup --max-jobs=5000  ║\n";
echo "╚══════════════════════════════════════════════════╝\n";
