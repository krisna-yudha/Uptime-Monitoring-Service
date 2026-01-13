<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Incident;

echo "=== Resolve Incident for Testing ===\n\n";

$incident = Incident::where('monitor_id', 49)
    ->where('resolved', false)
    ->latest('started_at')
    ->first();

if (!$incident) {
    echo "No open incident found for monitor ID 49\n";
    exit(1);
}

echo "Found open incident:\n";
echo "  ID: {$incident->id}\n";
echo "  Started: {$incident->started_at}\n";
echo "  Status: {$incident->status}\n";
echo "\n";

echo "Resolving incident manually (simulating user clicking 'Selesai')...\n";

$incident->update([
    'resolved' => true,
    'ended_at' => now(),
    'status' => 'resolved',
    'description' => ($incident->description ?? '') . " | Manually resolved by user for testing"
]);

echo "✅ Incident #{$incident->id} has been resolved!\n";
echo "\n";
echo "Now the monitor is still DOWN but no open incident exists.\n";
echo "Next check should create a new incident.\n";
echo "\n";
echo "Run: php artisan queue:work --once\n";
echo "Then run: php test_incident_logic.php\n";
