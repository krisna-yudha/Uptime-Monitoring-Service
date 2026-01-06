<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Monitor;
use App\Jobs\ProcessMonitorCheck;

// Find first HTTPS monitor
$monitor = Monitor::where('type', 'https')->first();

if (!$monitor) {
    echo "No HTTPS monitor found\n";
    exit;
}

echo "Testing SSL certificate check for: {$monitor->name}\n";
echo "Target: {$monitor->target}\n\n";

// Run the check job
$job = new ProcessMonitorCheck($monitor);
$job->handle();

// Refresh monitor data
$monitor = $monitor->fresh();

echo "\n=== SSL Certificate Info ===\n";
echo "SSL Cert Expiry: " . ($monitor->ssl_cert_expiry ?? 'Not checked') . "\n";
echo "SSL Cert Issuer: " . ($monitor->ssl_cert_issuer ?? 'Not checked') . "\n";
echo "SSL Checked At: " . ($monitor->ssl_checked_at ?? 'Never') . "\n";

if ($monitor->ssl_cert_expiry) {
    $expiryDate = \Carbon\Carbon::parse($monitor->ssl_cert_expiry);
    $daysRemaining = $expiryDate->diffInDays(now(), false); // false = signed diff
    echo "Days Remaining: " . abs($daysRemaining) . " days\n";
    
    if ($daysRemaining < 0) {
        echo "Status: ✅ Valid (expires in " . abs($daysRemaining) . " days)\n";
    } else {
        echo "Status: ❌ EXPIRED " . $daysRemaining . " days ago\n";
    }
}

echo "\nDone!\n";
