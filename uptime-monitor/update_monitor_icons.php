<?php

require 'vendor/autoload.php';

$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Monitor;
use App\Jobs\ProcessMonitorCheck;

echo "🔄 Updating icons for all HTTP/HTTPS monitors...\n\n";

$monitors = Monitor::whereIn('type', ['http', 'https', 'keyword'])
    ->whereNull('icon_url')
    ->get();

if ($monitors->isEmpty()) {
    echo "✅ All monitors already have icons!\n";
    exit(0);
}

echo "Found " . $monitors->count() . " monitors without icons\n\n";

$updated = 0;
$failed = 0;

foreach ($monitors as $monitor) {
    try {
        echo "Processing: {$monitor->name} ({$monitor->target})... ";
        
        $iconUrl = ProcessMonitorCheck::getFaviconUrl($monitor->target);
        
        if ($iconUrl) {
            $monitor->update(['icon_url' => $iconUrl]);
            echo "✅ Icon set: $iconUrl\n";
            $updated++;
        } else {
            echo "⚠️  No icon found\n";
            $failed++;
        }
        
        // Small delay to avoid rate limiting
        usleep(500000); // 0.5 second
        
    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
        $failed++;
    }
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "Summary:\n";
echo "  ✅ Updated: $updated\n";
echo "  ❌ Failed: $failed\n";
echo "  📊 Total: " . $monitors->count() . "\n";
echo str_repeat("=", 60) . "\n";
