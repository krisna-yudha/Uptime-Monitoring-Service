<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== MONITORS & NOTIFICATION CHANNELS ===\n\n";

$monitors = DB::table('monitors')->get();

foreach ($monitors as $monitor) {
    echo "📊 Monitor: {$monitor->name}\n";
    echo "   Enabled: " . ($monitor->enabled ? 'YES' : 'NO') . "\n";
    
    $channelIds = json_decode($monitor->notification_channels, true) ?? [];
    
    if (empty($channelIds)) {
        echo "   Channels: ❌ NONE\n\n";
        continue;
    }
    
    echo "   Channels:\n";
    
    $channels = DB::table('notification_channels')
        ->whereIn('id', $channelIds)
        ->get();
    
    foreach ($channels as $channel) {
        $enabled = $channel->is_enabled ? '✅' : '❌';
        echo "      {$enabled} {$channel->name} ({$channel->type})\n";
    }
    
    echo "\n";
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "AVAILABLE NOTIFICATION CHANNELS:\n\n";

$allChannels = DB::table('notification_channels')->get();

foreach ($allChannels as $channel) {
    $enabled = $channel->is_enabled ? '✅ ENABLED' : '❌ DISABLED';
    echo "{$channel->id}. {$channel->name} ({$channel->type}) - {$enabled}\n";
}
