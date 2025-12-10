<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🔧 Fixing Monitor Configuration Issues\n";
echo "=========================================\n\n";

$monitor = App\Models\Monitor::find(5);

if (!$monitor) {
    echo "❌ Monitor #5 not found\n";
    exit(1);
}

echo "Monitor: {$monitor->name}\n";
echo "Current Type: {$monitor->type}\n";
echo "Current Target: {$monitor->target}\n\n";

// Check if target URL scheme matches monitor type
$parsedUrl = parse_url($monitor->target);
$urlScheme = $parsedUrl['scheme'] ?? null;

echo "URL Scheme: " . ($urlScheme ?? 'NONE') . "\n";

if ($monitor->type === 'https' && $urlScheme === 'http') {
    echo "\n⚠️  MISMATCH DETECTED!\n";
    echo "Monitor type is 'https' but target URL uses 'http://'\n\n";
    
    // Ask what to do
    echo "Options:\n";
    echo "1. Change type to 'http'\n";
    echo "2. Change target URL to 'https://'\n";
    echo "3. Delete this monitor (localhost test)\n\n";
    
    // Since this is localhost:3005 (probably test), change to http
    echo "🔧 Auto-fixing: Changing monitor type to 'http' (localhost test monitor)\n";
    
    $monitor->type = 'http';
    $monitor->save();
    
    echo "✅ Monitor type changed to 'http'\n";
    echo "ℹ️  SSL checking will be disabled for this monitor\n";
} elseif ($monitor->type === 'https' && $urlScheme === 'https') {
    echo "\n✅ Configuration is correct\n";
    echo "Triggering manual SSL check...\n\n";
    
    // Dispatch job to check this monitor
    App\Jobs\ProcessMonitorCheck::dispatch($monitor);
    
    echo "✅ Monitor check job dispatched\n";
    echo "Run: php artisan queue:work --queue=monitor-checks --once\n";
}

echo "\n";
