<?php

require __DIR__.'/vendor/autoload.php';

use Illuminate\Support\Facades\Artisan;

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🧪 Testing SSL Check with Detailed Logging\n";
echo "==========================================\n\n";

// Use existing monitor #1 (rpjmd)
$monitor = App\Models\Monitor::find(1);

echo "Monitor: #{$monitor->id} - {$monitor->name}\n";
echo "Target: {$monitor->target}\n";
echo "Type: {$monitor->type}\n\n";

echo "📊 SSL Status BEFORE check:\n";
echo "  Expiry: " . ($monitor->ssl_cert_expiry ?? 'NULL') . "\n";
echo "  Issuer: " . ($monitor->ssl_cert_issuer ?? 'NULL') . "\n";
echo "  Checked: " . ($monitor->ssl_checked_at ?? 'NULL') . "\n\n";

echo "🔄 Dispatching check job...\n";
App\Jobs\ProcessMonitorCheck::dispatch($monitor);

echo "⏳ Processing queue (this will take a few seconds)...\n";
Artisan::call('queue:work', [
    '--queue' => 'monitor-checks',
    '--once' => true
]);

echo Artisan::output();

// Refresh from database
$monitor = App\Models\Monitor::find(1);

echo "\n📊 SSL Status AFTER check:\n";
echo "  Expiry: " . ($monitor->ssl_cert_expiry ?? 'NULL') . "\n";
echo "  Issuer: " . ($monitor->ssl_cert_issuer ?? 'NULL') . "\n";
echo "  Checked: " . ($monitor->ssl_checked_at ?? 'NULL') . "\n";

if ($monitor->ssl_cert_expiry) {
    $expiryDate = new DateTime($monitor->ssl_cert_expiry);
    $now = new DateTime();
    $interval = $now->diff($expiryDate);
    $daysRemaining = $interval->days * ($interval->invert ? -1 : 1);
    
    echo "\n✅ SSL Certificate is VALID\n";
    echo "   Expires in {$daysRemaining} days\n";
} else {
    echo "\n❌ SSL data still NULL after check\n";
}
