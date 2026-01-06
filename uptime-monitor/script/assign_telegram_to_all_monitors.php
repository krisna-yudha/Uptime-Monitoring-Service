<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== AUTO-ASSIGN TELEGRAM CHANNEL TO ALL MONITORS ===\n\n";

// Get Telegram channel
$telegramChannel = DB::table('notification_channels')
    ->where('type', 'telegram')
    ->where('is_enabled', true)
    ->first();

if (!$telegramChannel) {
    echo "❌ No enabled Telegram channel found!\n";
    exit(1);
}

echo "📱 Telegram Channel: {$telegramChannel->name} (ID: {$telegramChannel->id})\n\n";

// Get all enabled monitors
$monitors = DB::table('monitors')
    ->where('enabled', true)
    ->get();

echo "📊 Found {$monitors->count()} enabled monitors\n\n";

$updated = 0;

foreach ($monitors as $monitor) {
    $currentChannels = json_decode($monitor->notification_channels, true) ?? [];
    
    // Check if Telegram channel already assigned
    if (in_array($telegramChannel->id, $currentChannels)) {
        echo "⏭️  {$monitor->name} - Already has Telegram channel\n";
        continue;
    }
    
    // Add Telegram channel
    $currentChannels[] = $telegramChannel->id;
    
    DB::table('monitors')
        ->where('id', $monitor->id)
        ->update([
            'notification_channels' => json_encode($currentChannels)
        ]);
    
    echo "✅ {$monitor->name} - Added Telegram channel\n";
    $updated++;
}

echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "✅ Updated {$updated} monitor(s)\n";
echo "📱 All monitors will now send notifications to Telegram!\n";
