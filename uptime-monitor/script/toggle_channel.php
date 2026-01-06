<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\NotificationChannel;

echo "=== Toggle Notification Channel ===\n\n";

$channel = NotificationChannel::first();

if (!$channel) {
    echo "❌ No channels found!\n";
    exit(1);
}

echo "Channel: {$channel->name} ({$channel->type})\n";
echo "Current Status: " . ($channel->is_enabled ? '✅ ENABLED' : '❌ DISABLED') . "\n\n";

// Toggle
$newStatus = !$channel->is_enabled;
$channel->is_enabled = $newStatus;
$channel->save();

echo "Status changed to: " . ($newStatus ? '✅ ENABLED' : '❌ DISABLED') . "\n\n";

echo "Verifikasi:\n";
$channel->refresh();
echo "  Database value: " . ($channel->is_enabled ? 'true' : 'false') . "\n";
echo "  Type: " . gettype($channel->is_enabled) . "\n";
echo "  Raw: " . var_export($channel->is_enabled, true) . "\n";
