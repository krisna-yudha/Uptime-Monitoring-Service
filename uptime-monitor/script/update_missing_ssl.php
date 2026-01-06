<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Monitor;
use App\Jobs\ProcessMonitorCheck;

// Check all HTTPS monitors that don't have SSL data yet
$monitors = Monitor::where('type', 'https')
    ->whereNull('ssl_cert_expiry')
    ->get();

echo "=== Checking SSL for monitors without SSL data ===\n\n";

foreach ($monitors as $monitor) {
    echo "Checking: {$monitor->name} ({$monitor->target})\n";
    
    try {
        $job = new ProcessMonitorCheck($monitor);
        $job->handle();
        
        $monitor = $monitor->fresh();
        echo "✅ SSL Expiry: " . ($monitor->ssl_cert_expiry ?? 'Failed') . "\n";
        echo "   Issuer: " . ($monitor->ssl_cert_issuer ?? 'N/A') . "\n\n";
    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "\n\n";
    }
}

echo "Done!\n";
