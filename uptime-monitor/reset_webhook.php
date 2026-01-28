<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Http;
use App\Models\NotificationChannel;

echo "=== RESET TELEGRAM WEBHOOK ===\n\n";

$channel = NotificationChannel::where('type', 'telegram')
    ->where('is_enabled', 1)
    ->first();

if (!$channel) {
    echo "❌ No enabled Telegram channel found\n";
    exit(1);
}

$config = is_string($channel->config) ? json_decode($channel->config, true) : $channel->config;
$botToken = $config['bot_token'] ?? null;

if (empty($botToken)) {
    echo "❌ Bot token not found\n";
    exit(1);
}

echo "Channel: {$channel->name}\n";
echo "Bot Token: " . substr($botToken, 0, 10) . "...\n\n";

// Step 1: Delete current webhook
echo "1. Deleting current webhook...\n";
$deleteResponse = Http::withOptions(['verify' => false])
    ->timeout(30)
    ->post("https://api.telegram.org/bot{$botToken}/deleteWebhook", [
        'drop_pending_updates' => true
    ])
    ->json();

if ($deleteResponse['ok'] ?? false) {
    echo "   ✅ Webhook deleted\n";
} else {
    echo "   ⚠️  Error: " . ($deleteResponse['description'] ?? 'Unknown error') . "\n";
}

// Step 2: Get APP_URL
$appUrl = config('app.url');
echo "\n2. Using APP_URL: {$appUrl}\n";

// Step 3: Set new webhook
$webhookUrl = rtrim($appUrl, '/') . '/api/telegram/webhook';
echo "\n3. Setting new webhook: {$webhookUrl}\n";

$setResponse = Http::withOptions(['verify' => false])
    ->timeout(30)
    ->post("https://api.telegram.org/bot{$botToken}/setWebhook", [
        'url' => $webhookUrl,
        'allowed_updates' => ['message', 'callback_query'],
        'drop_pending_updates' => true
    ])
    ->json();

if ($setResponse['ok'] ?? false) {
    echo "   ✅ Webhook set successfully!\n";
    echo "   📝 " . ($setResponse['description'] ?? '') . "\n";
} else {
    echo "   ❌ Error: " . ($setResponse['description'] ?? 'Unknown error') . "\n";
    exit(1);
}

// Step 4: Verify webhook
echo "\n4. Verifying webhook info...\n";
$infoResponse = Http::withOptions(['verify' => false])
    ->timeout(30)
    ->get("https://api.telegram.org/bot{$botToken}/getWebhookInfo")
    ->json();

if ($infoResponse['ok'] ?? false) {
    $info = $infoResponse['result'];
    echo "   Current URL: " . ($info['url'] ?? 'NOT SET') . "\n";
    echo "   Pending updates: " . ($info['pending_update_count'] ?? 0) . "\n";
    
    if (!empty($info['last_error_message'])) {
        echo "   ⚠️  Last error: " . $info['last_error_message'] . "\n";
        echo "      At: " . date('Y-m-d H:i:s', $info['last_error_date']) . "\n";
    } else {
        echo "   ✅ No errors\n";
    }
}

// Step 5: Send test message
echo "\n5. Sending test message...\n";
$chatId = $config['chat_id'] ?? null;

if ($chatId) {
    $message = "✅ Webhook reset successfully!\n\n";
    $message .= "URL: {$webhookUrl}\n";
    $message .= "Time: " . date('Y-m-d H:i:s') . "\n\n";
    $message .= "Bot is ready! Try these commands:\n";
    $message .= "/start - Get started\n";
    $message .= "/status - Check system status\n";
    $message .= "/uptime - View uptime stats\n";
    
    $sendResponse = Http::withOptions(['verify' => false])
        ->post("https://api.telegram.org/bot{$botToken}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'HTML'
        ])
        ->json();
    
    if ($sendResponse['ok'] ?? false) {
        echo "   ✅ Test message sent! Check your Telegram\n";
    } else {
        echo "   ❌ Failed to send test message\n";
    }
}

echo "\n=== DONE ===\n";
echo "\n🎉 Bot is now ready! Send /start in Telegram to test\n\n";
