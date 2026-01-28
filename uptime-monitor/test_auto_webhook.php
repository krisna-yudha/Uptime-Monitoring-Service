<?php

/**
 * Test auto webhook setup
 * This script will trigger the observer by updating the NotificationChannel
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "╔════════════════════════════════════════════════════════╗\n";
echo "║  TEST AUTO WEBHOOK SETUP                               ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

// Get Telegram channel
$channel = App\Models\NotificationChannel::where('type', 'telegram')->first();

if (!$channel) {
    echo "❌ No Telegram channel found!\n";
    echo "   Create a Telegram notification channel in the dashboard first.\n";
    exit(1);
}

echo "Found channel: {$channel->name}\n";
echo "Current status: " . ($channel->is_enabled ? 'Enabled ✅' : 'Disabled ❌') . "\n";
echo "APP_URL: " . config('app.url') . "\n\n";

// Check if APP_URL is valid for webhook
$appUrl = config('app.url');
if (strpos($appUrl, 'localhost') !== false && strpos($appUrl, 'https://') !== 0) {
    echo "⚠️  WARNING: APP_URL is localhost without HTTPS\n";
    echo "   Auto webhook setup will be skipped.\n";
    echo "   Update APP_URL to ngrok URL or production domain.\n\n";
    
    echo "Do you want to update APP_URL now? (yes/no): ";
    $handle = fopen("php://stdin", "r");
    $response = trim(fgets($handle));
    
    if (strtolower($response) === 'yes') {
        echo "\nEnter new APP_URL (e.g., https://your-ngrok-url.ngrok.io): ";
        $newUrl = trim(fgets($handle));
        
        if (!empty($newUrl)) {
            // Update .env file
            $envFile = __DIR__ . '/.env';
            $envContent = file_get_contents($envFile);
            $envContent = preg_replace('/^APP_URL=.*/m', "APP_URL={$newUrl}", $envContent);
            file_put_contents($envFile, $envContent);
            
            // Clear config cache
            \Illuminate\Support\Facades\Artisan::call('config:clear');
            
            echo "\n✅ APP_URL updated to: {$newUrl}\n";
            echo "   Config cache cleared.\n\n";
        }
    }
    
    fclose($handle);
}

echo "Triggering observer by touching the channel...\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Touch the channel to trigger observer
$channel->touch();

echo "✅ Channel updated. Observer should have been triggered.\n\n";

echo "Check the logs for webhook setup:\n";
echo "  tail -f storage/logs/laravel.log | grep -i 'telegram webhook'\n\n";

// Verify webhook
$config = is_string($channel->config) ? json_decode($channel->config, true) : $channel->config;
$botToken = $config['bot_token'] ?? '';

if (!empty($botToken)) {
    echo "Verifying webhook status...\n";
    
    try {
        $response = file_get_contents(
            "https://api.telegram.org/bot{$botToken}/getWebhookInfo",
            false,
            stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ]
            ])
        );
        
        $result = json_decode($response, true);
        
        if ($result && isset($result['result'])) {
            $info = $result['result'];
            echo "\n📊 Current Webhook Status:\n";
            echo "   URL: " . ($info['url'] ?? 'NOT SET') . "\n";
            echo "   Pending updates: " . ($info['pending_update_count'] ?? 0) . "\n";
            
            if (isset($info['last_error_message'])) {
                echo "   ⚠️  Last error: " . $info['last_error_message'] . "\n";
            } else {
                echo "   ✅ No errors\n";
            }
            
            if (!empty($info['url'])) {
                echo "\n✅ Webhook is configured!\n";
                echo "   Bot commands should work now.\n";
                echo "   Try sending /start to your bot.\n";
            } else {
                echo "\n⚠️  Webhook is not set yet.\n";
                echo "   This usually means APP_URL is localhost.\n";
            }
        }
    } catch (Exception $e) {
        echo "⚠️  Could not verify webhook: " . $e->getMessage() . "\n";
    }
}

echo "\n╔════════════════════════════════════════════════════════╗\n";
echo "║  TEST COMPLETE                                         ║\n";
echo "╚════════════════════════════════════════════════════════╝\n";
