<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Monitor;

echo "=== SSL Certificate Data in Database ===\n\n";

$monitors = Monitor::where('type', 'https')->get(['id', 'name', 'target', 'ssl_cert_expiry', 'ssl_cert_issuer', 'ssl_checked_at']);

foreach ($monitors as $monitor) {
    echo "ID: {$monitor->id}\n";
    echo "Name: {$monitor->name}\n";
    echo "Target: {$monitor->target}\n";
    echo "SSL Expiry: " . ($monitor->ssl_cert_expiry ?? 'NULL') . "\n";
    echo "SSL Issuer: " . ($monitor->ssl_cert_issuer ?? 'NULL') . "\n";
    echo "SSL Checked At: " . ($monitor->ssl_checked_at ?? 'NULL') . "\n";
    
    if ($monitor->ssl_cert_expiry) {
        $daysRemaining = \Carbon\Carbon::parse($monitor->ssl_cert_expiry)->diffInDays(now(), false);
        echo "Days Remaining: " . abs($daysRemaining) . " days\n";
    }
    
    echo "---\n\n";
}

if ($monitors->isEmpty()) {
    echo "No HTTPS monitors found!\n";
}
