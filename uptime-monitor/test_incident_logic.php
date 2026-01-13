<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Monitor;
use App\Models\Incident;

echo "=== Testing Incident Logic ===\n\n";

$monitor = Monitor::find(49);

if (!$monitor) {
    echo "Monitor ID 49 not found!\n";
    exit(1);
}

echo "Monitor: {$monitor->name}\n";
echo "Target: {$monitor->target}\n";
echo "Current Status: {$monitor->last_status}\n";
echo "Consecutive Failures: {$monitor->consecutive_failures}\n";
echo "\n";

// Get all incidents
$allIncidents = Incident::where('monitor_id', 49)
    ->orderBy('started_at', 'desc')
    ->get();

echo "=== All Incidents (Total: {$allIncidents->count()}) ===\n\n";

foreach ($allIncidents as $incident) {
    echo "Incident #{$incident->id}:\n";
    echo "  Started: {$incident->started_at}\n";
    echo "  Ended: " . ($incident->ended_at ?? 'null') . "\n";
    echo "  Resolved: " . ($incident->resolved ? 'YES' : 'NO') . "\n";
    echo "  Status: {$incident->status}\n";
    echo "  Alert Status: {$incident->alert_status}\n";
    echo "  Description: {$incident->description}\n";
    echo "\n";
}

// Check for open incidents
$openIncident = Incident::where('monitor_id', 49)
    ->where('resolved', false)
    ->latest('started_at')
    ->first();

echo "=== Current Open Incident ===\n\n";
if ($openIncident) {
    echo "✅ There IS an open incident:\n";
    echo "  Incident ID: {$openIncident->id}\n";
    echo "  Started: {$openIncident->started_at}\n";
    echo "  Status: {$openIncident->status}\n";
} else {
    echo "❌ NO open incident found\n";
    if ($monitor->last_status === 'down') {
        echo "⚠️  WARNING: Monitor is DOWN but no open incident!\n";
        echo "   This means the incident logic should create a new incident on next check.\n";
    }
}

echo "\n=== Simulation ===\n\n";

if (!$openIncident && $monitor->last_status === 'down') {
    echo "Scenario: Monitor is DOWN but no open incident exists\n";
    echo "Expected behavior: Next check should create a new incident\n";
    echo "\nTo trigger the check, run:\n";
    echo "php artisan queue:work --once\n";
} elseif ($openIncident && $monitor->last_status === 'down') {
    echo "Scenario: Monitor is DOWN and incident #{$openIncident->id} is open\n";
    echo "Expected behavior: No new incident will be created\n";
    echo "\nTo test resolution logic:\n";
    echo "1. Manually resolve incident #{$openIncident->id}\n";
    echo "2. Run: php artisan queue:work --once\n";
    echo "3. A new incident should be created because monitor is still DOWN\n";
} elseif ($monitor->last_status === 'up') {
    echo "Scenario: Monitor is UP\n";
    echo "Expected behavior: Any open incidents should be auto-resolved\n";
}

echo "\n";
