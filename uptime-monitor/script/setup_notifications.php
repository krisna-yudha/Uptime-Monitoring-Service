<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Monitor;
use App\Models\NotificationChannel;

echo "=== Setup Notification Channels untuk Monitors ===\n\n";

// 1. Tampilkan notification channels yang tersedia
echo "Notification Channels yang tersedia:\n";
$channels = NotificationChannel::all();
foreach ($channels as $channel) {
    echo "  [{$channel->id}] {$channel->name} ({$channel->type})\n";
}

if ($channels->isEmpty()) {
    echo "\n⚠️  Tidak ada notification channel!\n";
    echo "Silakan buat notification channel dulu di UI: http://localhost:5173/notifications\n";
    exit(1);
}

echo "\n";

// 2. Ambil semua ID channels
$channelIds = $channels->pluck('id')->toArray();
echo "Channel IDs: [" . implode(', ', $channelIds) . "]\n\n";

// 3. Update semua monitors
echo "Updating monitors...\n";
$monitors = Monitor::all();

if ($monitors->isEmpty()) {
    echo "\n⚠️  Tidak ada monitor!\n";
    echo "Silakan buat monitor dulu di UI: http://localhost:5173/monitors/create\n";
    exit(1);
}

foreach ($monitors as $monitor) {
    // Set notification channels
    $monitor->notification_channels = $channelIds;
    
    // Set notify_after_retries ke 1 untuk instant notification
    if ($monitor->notify_after_retries > 1 || $monitor->notify_after_retries === null) {
        $monitor->notify_after_retries = 1;
    }
    
    $monitor->save();
    
    echo "  ✓ Monitor #{$monitor->id} '{$monitor->name}' - linked to " . count($channelIds) . " channels\n";
}

echo "\n=== Verifikasi Setup ===\n\n";

// 4. Verifikasi hasil
$monitors = Monitor::all();
foreach ($monitors as $monitor) {
    $channels = NotificationChannel::whereIn('id', $monitor->notification_channels ?? [])->get();
    
    echo "Monitor #{$monitor->id}: {$monitor->name}\n";
    echo "  Status: {$monitor->last_status}\n";
    echo "  Consecutive Failures: {$monitor->consecutive_failures}\n";
    echo "  Notify After Retries: {$monitor->notify_after_retries}\n";
    echo "  Linked Channels: ";
    
    if ($channels->isEmpty()) {
        echo "❌ NONE\n";
    } else {
        echo "\n";
        foreach ($channels as $ch) {
            echo "    - [{$ch->id}] {$ch->name} ({$ch->type})\n";
        }
    }
    echo "\n";
}

echo "=== Setup Complete! ===\n";
echo "\n📋 Langkah selanjutnya:\n";
echo "1. Pastikan worker berjalan: run_notification_worker.bat\n";
echo "2. Pastikan monitor checks worker berjalan: worker_manager.bat\n";
echo "3. Test dengan mematikan salah satu service yang di-monitor\n";
echo "4. Tunggu beberapa detik, bot akan mengirim notifikasi!\n";
