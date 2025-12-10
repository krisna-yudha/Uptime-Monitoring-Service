<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🔍 Checking SSL Status for All Monitors\n";
echo "=========================================\n\n";

$monitors = App\Models\Monitor::all();

$stats = [
    'total' => 0,
    'https' => 0,
    'https_with_ssl' => 0,
    'https_without_ssl' => 0,
    'http' => 0,
    'other' => 0
];

foreach ($monitors as $monitor) {
    $stats['total']++;
    
    echo "Monitor #{$monitor->id}: {$monitor->name}\n";
    echo "  Type: {$monitor->type}\n";
    echo "  Target: {$monitor->target}\n";
    
    if ($monitor->type === 'https') {
        $stats['https']++;
        
        if ($monitor->ssl_cert_expiry) {
            $stats['https_with_ssl']++;
            
            $expiryDate = new DateTime($monitor->ssl_cert_expiry);
            $now = new DateTime();
            $interval = $now->diff($expiryDate);
            $daysRemaining = $interval->days * ($interval->invert ? -1 : 1);
            
            echo "  ✅ SSL Cert Expiry: {$monitor->ssl_cert_expiry}\n";
            echo "  📋 SSL Cert Issuer: {$monitor->ssl_cert_issuer}\n";
            echo "  🕐 SSL Checked At: {$monitor->ssl_checked_at}\n";
            echo "  📊 Days Remaining: {$daysRemaining} days\n";
            
            if ($daysRemaining < 0) {
                echo "  ⚠️  STATUS: EXPIRED!\n";
            } elseif ($daysRemaining <= 7) {
                echo "  🔴 STATUS: CRITICAL (expires in {$daysRemaining} days)\n";
            } elseif ($daysRemaining <= 30) {
                echo "  ⚠️  STATUS: WARNING (expires in {$daysRemaining} days)\n";
            } else {
                echo "  ✓  STATUS: VALID ({$daysRemaining} days remaining)\n";
            }
        } else {
            $stats['https_without_ssl']++;
            echo "  ❌ SSL Data: NOT CHECKED YET\n";
            echo "  💡 Action: Need to trigger SSL check\n";
        }
    } elseif ($monitor->type === 'http') {
        $stats['http']++;
        echo "  ℹ️  HTTP Monitor (SSL not applicable)\n";
    } else {
        $stats['other']++;
        echo "  ℹ️  {$monitor->type} Monitor (SSL not applicable)\n";
    }
    
    echo "\n";
}

echo "=========================================\n";
echo "📊 Summary Statistics:\n";
echo "=========================================\n";
echo "Total Monitors: {$stats['total']}\n";
echo "HTTPS Monitors: {$stats['https']}\n";
echo "  └─ With SSL Data: {$stats['https_with_ssl']}\n";
echo "  └─ Without SSL Data: {$stats['https_without_ssl']}\n";
echo "HTTP Monitors: {$stats['http']}\n";
echo "Other Monitors: {$stats['other']}\n";
echo "\n";

if ($stats['https_without_ssl'] > 0) {
    echo "⚠️  WARNING: {$stats['https_without_ssl']} HTTPS monitor(s) missing SSL data!\n";
    echo "💡 Run: php artisan queue:work --queue=monitor-checks --once\n";
} else {
    echo "✅ All HTTPS monitors have SSL certificate data!\n";
}
