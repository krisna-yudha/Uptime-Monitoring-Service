<?php

require __DIR__.'/vendor/autoload.php';

use Illuminate\Support\Facades\Artisan;

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🧪 Testing Auto SSL Check on New Monitor Creation\n";
echo "==================================================\n\n";

// Create a test HTTPS monitor
$monitor = App\Models\Monitor::create([
    'name' => 'Test SSL Auto Check',
    'type' => 'https',
    'target' => 'https://www.google.com',
    'interval_seconds' => 60,
    'timeout_ms' => 5000,
    'retries' => 3,
    'enabled' => true,
    'created_by' => 1
]);

echo "✅ Monitor created: #{$monitor->id} - {$monitor->name}\n";
echo "   Target: {$monitor->target}\n";
echo "   Type: {$monitor->type}\n\n";

// Manually dispatch job (simulating what controller should do)
echo "📤 Dispatching initial monitor check job...\n";
App\Jobs\ProcessMonitorCheck::dispatch($monitor);

echo "✅ Job dispatched to queue\n\n";

echo "🔄 Processing queue job...\n";
// Process the queue
Artisan::call('queue:work', [
    '--queue' => 'monitor-checks',
    '--once' => true
]);

echo Artisan::output();

// Refresh monitor data from database
$monitor->refresh();

echo "\n📊 Monitor Status After Check:\n";
echo "================================\n";
echo "Status: {$monitor->last_status}\n";
echo "Checked At: {$monitor->last_checked_at}\n";

if ($monitor->type === 'https') {
    echo "\n🔐 SSL Certificate Info:\n";
    if ($monitor->ssl_cert_expiry) {
        $expiryDate = new DateTime($monitor->ssl_cert_expiry);
        $now = new DateTime();
        $interval = $now->diff($expiryDate);
        $daysRemaining = $interval->days * ($interval->invert ? -1 : 1);
        
        echo "  ✅ Expiry Date: {$monitor->ssl_cert_expiry}\n";
        echo "  📋 Issuer: {$monitor->ssl_cert_issuer}\n";
        echo "  🕐 Checked At: {$monitor->ssl_checked_at}\n";
        echo "  📊 Days Remaining: {$daysRemaining} days\n";
        
        if ($daysRemaining > 30) {
            echo "  ✓  STATUS: VALID\n";
        }
    } else {
        echo "  ❌ SSL data not found (check may have failed)\n";
    }
}

echo "\n🧹 Cleaning up test monitor...\n";
$monitor->delete();
echo "✅ Test monitor deleted\n";
