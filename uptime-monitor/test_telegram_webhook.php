<?php

/**
 * Script untuk test koneksi Telegram Webhook
 * Usage: php test_telegram_webhook.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== TELEGRAM WEBHOOK DIAGNOSTIC ===\n\n";

// 1. Check NotificationChannel
echo "1. Checking Telegram Notification Channels...\n";
$channels = App\Models\NotificationChannel::where('type', 'telegram')->get();

if ($channels->isEmpty()) {
    echo "   ❌ NO TELEGRAM CHANNELS FOUND!\n";
    echo "   → Buat channel Telegram di dashboard terlebih dahulu.\n\n";
} else {
    echo "   ✅ Found " . $channels->count() . " Telegram channel(s):\n\n";
    foreach ($channels as $channel) {
        $config = is_string($channel->config) ? json_decode($channel->config, true) : $channel->config;
        $botToken = $config['bot_token'] ?? 'NOT SET';
        $chatId = $config['chat_id'] ?? 'NOT SET';
        
        echo "   Channel: {$channel->name}\n";
        echo "   Enabled: " . ($channel->is_enabled ? 'YES ✅' : 'NO ❌') . "\n";
        echo "   Bot Token: " . (strlen($botToken) > 10 ? substr($botToken, 0, 10) . '...' : $botToken) . "\n";
        echo "   Chat ID: {$chatId}\n\n";
        
        if ($channel->is_enabled && !empty($botToken) && $botToken !== 'NOT SET') {
            // Test webhook info
            echo "   Testing webhook info...\n";
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
                    $webhookInfo = $result['result'];
                    echo "   Webhook URL: " . ($webhookInfo['url'] ?? 'NOT SET') . "\n";
                    echo "   Pending updates: " . ($webhookInfo['pending_update_count'] ?? 0) . "\n";
                    
                    if (isset($webhookInfo['last_error_message'])) {
                        echo "   ❌ Last Error: " . $webhookInfo['last_error_message'] . "\n";
                        echo "   Error Date: " . date('Y-m-d H:i:s', $webhookInfo['last_error_date']) . "\n";
                    } else {
                        echo "   ✅ No webhook errors\n";
                    }
                } else {
                    echo "   ❌ Failed to get webhook info\n";
                }
            } catch (Exception $e) {
                echo "   ❌ Error: " . $e->getMessage() . "\n";
            }
            echo "\n";
        }
    }
}

// 2. Check route
echo "2. Checking webhook route...\n";
echo "   Expected URL: " . url('/api/telegram/webhook') . "\n";
echo "   For production, use: https://your-domain.com/api/telegram/webhook\n\n";

// 3. Test sending message
echo "3. Testing message send capability...\n";
$activeChannel = App\Models\NotificationChannel::where('type', 'telegram')
    ->where('is_enabled', true)
    ->first();

if (!$activeChannel) {
    echo "   ⚠️ No active Telegram channel to test\n\n";
} else {
    $config = is_string($activeChannel->config) ? json_decode($activeChannel->config, true) : $activeChannel->config;
    $botToken = $config['bot_token'] ?? '';
    $chatId = $config['chat_id'] ?? '';
    
    if (empty($botToken) || empty($chatId)) {
        echo "   ❌ Bot token or Chat ID not configured\n\n";
    } else {
        echo "   Sending test message to Chat ID: {$chatId}...\n";
        
        try {
            $testMessage = "🧪 *Test Message*\n\n";
            $testMessage .= "This is a test from webhook diagnostic script.\n";
            $testMessage .= "Time: " . date('Y-m-d H:i:s') . "\n\n";
            $testMessage .= "If you receive this, the bot can send messages! ✅";
            
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => 'Content-Type: application/json',
                    'content' => json_encode([
                        'chat_id' => $chatId,
                        'text' => $testMessage,
                        'parse_mode' => 'Markdown'
                    ]),
                    'ignore_errors' => true
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ]
            ]);
            
            $response = file_get_contents(
                "https://api.telegram.org/bot{$botToken}/sendMessage",
                false,
                $context
            );
            
            $result = json_decode($response, true);
            
            if ($result && isset($result['ok']) && $result['ok']) {
                echo "   ✅ Message sent successfully!\n";
                echo "   Check your Telegram chat.\n\n";
            } else {
                echo "   ❌ Failed to send message\n";
                echo "   Response: " . json_encode($result, JSON_PRETTY_PRINT) . "\n\n";
            }
        } catch (Exception $e) {
            echo "   ❌ Error: " . $e->getMessage() . "\n\n";
        }
    }
}

echo "=== TROUBLESHOOTING TIPS ===\n\n";
echo "If webhook is not working:\n";
echo "1. Make sure you have set the webhook URL properly\n";
echo "2. For local development, use ngrok or similar tool\n";
echo "3. Run: php setup_telegram_webhook.php <BOT_TOKEN>\n";
echo "4. Make sure Laravel server is running (php artisan serve)\n";
echo "5. Check firewall settings\n";
echo "6. Webhook URL must be HTTPS in production\n\n";

echo "If bot is not responding to commands:\n";
echo "1. Check if NotificationChannel exists and is enabled\n";
echo "2. Verify bot token is correct\n";
echo "3. Check storage/logs/laravel.log for errors\n";
echo "4. Test with /start command in Telegram\n\n";
