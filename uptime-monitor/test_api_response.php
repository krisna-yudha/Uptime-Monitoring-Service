<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Get monitor 1 (rpjmd)
$monitor = App\Models\Monitor::find(1);

if ($monitor) {
    echo "✅ Monitor Found: {$monitor->name}\n\n";
    
    // Show raw model attributes
    echo "🔍 Raw Model Attributes:\n";
    echo "-------------------------\n";
    print_r($monitor->getAttributes());
    
    echo "\n\n";
    
    // Show JSON serialization (what API returns)
    echo "📦 JSON Serialization (API Response):\n";
    echo "-------------------------------------\n";
    echo json_encode($monitor, JSON_PRETTY_PRINT);
    
    echo "\n\n";
    
    // Check SSL fields specifically
    echo "🔐 SSL Fields Check:\n";
    echo "-------------------\n";
    echo "ssl_cert_expiry: " . ($monitor->ssl_cert_expiry ?? 'NULL') . "\n";
    echo "ssl_cert_issuer: " . ($monitor->ssl_cert_issuer ?? 'NULL') . "\n";
    echo "ssl_checked_at: " . ($monitor->ssl_checked_at ?? 'NULL') . "\n";
    
} else {
    echo "❌ Monitor ID 1 not found\n";
}
