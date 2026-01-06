<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$monitorId = $argv[1] ?? null;
if (!$monitorId) {
    echo "Usage: php get_monitor_counts.php {monitor_id}\n";
    exit(1);
}

$checks = DB::table('monitor_checks')->where('monitor_id', $monitorId)->count();
$logs = DB::table('monitoring_logs')->where('monitor_id', $monitorId)->count();

echo "Monitor ID: $monitorId\n";
echo "MonitorChecks count: $checks\n";
echo "MonitoringLogs count: $logs\n";

$latestChecks = DB::table('monitor_checks')->where('monitor_id', $monitorId)->orderBy('checked_at','desc')->limit(5)->get();
$latestLogs = DB::table('monitoring_logs')->where('monitor_id', $monitorId)->orderBy('logged_at','desc')->limit(10)->get();

echo "\nLatest checks:\n";
foreach ($latestChecks as $c) {
    echo "- id={$c->id} status={$c->status} checked_at={$c->checked_at} latency_ms={$c->latency_ms} error={$c->error_message}\n";
}

echo "\nLatest logs:\n";
foreach ($latestLogs as $l) {
    echo "- id={$l->id} event={$l->event_type} status={$l->status} logged_at={$l->logged_at} error={$l->error_message}\n";
}
