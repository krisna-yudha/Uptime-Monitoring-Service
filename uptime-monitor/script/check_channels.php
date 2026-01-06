<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\NotificationChannel;

echo "=== Notification Channels Status ===\n\n";

$channels = NotificationChannel::all();

foreach ($channels as $channel) {
    $status = $channel->is_enabled ? '✅ ENABLED' : '❌ DISABLED';
    echo "  [{$channel->id}] {$channel->name} ({$channel->type}) - {$status}\n";
}

echo "\n=== Total: " . $channels->count() . " channels ===\n";
