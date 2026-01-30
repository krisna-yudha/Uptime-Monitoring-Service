<?php

require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Monitor;

echo "=== CHECKING MONITOR ID 54 ===\n\n";

$monitor = Monitor::find(54);

if (!$monitor) {
    echo "Monitor not found!\n";
    exit;
}

echo "Monitor Details:\n";
echo "  ID: {$monitor->id}\n";
echo "  Name: {$monitor->name}\n";
echo "  Type: {$monitor->type}\n";
echo "  Target: {$monitor->target}\n";
echo "  Enabled: " . ($monitor->enabled ? 'YES' : 'NO') . "\n";
echo "  Last Status: {$monitor->last_status}\n";
echo "  Last Checked At: " . ($monitor->last_checked_at ?? 'NULL') . "\n";
echo "  Next Check At: " . ($monitor->next_check_at ?? 'NULL') . "\n";
echo "  Interval: {$monitor->interval_seconds}s\n";
echo "  Priority: {$monitor->priority}\n";
echo "  Created At: {$monitor->created_at}\n";
echo "  Updated At: {$monitor->updated_at}\n";
echo "\n";

// Try to manually dispatch a check
echo "Dispatching manual check...\n";
try {
    \App\Jobs\ProcessMonitorCheck::dispatch($monitor)->onQueue('monitor-checks-priority');
    echo "✓ Check dispatched to queue\n";
} catch (\Exception $e) {
    echo "✗ Failed to dispatch: " . $e->getMessage() . "\n";
}
