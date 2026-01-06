<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== GET TELEGRAM BOT TOKEN ===\n\n";

$channel = DB::table('notification_channels')
    ->where('type', 'telegram')
    ->where('is_enabled', true)
    ->first();

if (!$channel) {
    echo "❌ No active Telegram channel found!\n";
    echo "Please setup Telegram channel in dashboard first.\n";
    exit(1);
}

$config = json_decode($channel->config, true);
$botToken = $config['bot_token'] ?? '';
$chatId = $config['chat_id'] ?? '';

if (empty($botToken)) {
    echo "❌ Bot token not configured!\n";
    exit(1);
}

echo "✅ Telegram Channel Found:\n";
echo "   Name: {$channel->name}\n";
echo "   Bot Token: {$botToken}\n";
echo "   Chat ID: {$chatId}\n\n";

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Run polling with this command:\n\n";
echo "php telegram_bot_polling.php \"{$botToken}\"\n";
