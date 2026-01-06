<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Monitor;
use App\Models\Incident;
use App\Jobs\SendNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;

echo "=== TEST TELEGRAM INCIDENT NOTIFICATION ===\n\n";

// Get first enabled monitor
$monitor = Monitor::where('enabled', true)->first();

if (!$monitor) {
    echo "❌ No enabled monitor found!\n";
    exit(1);
}

echo "📊 Monitor: {$monitor->name}\n";
echo "🎯 Target: {$monitor->target}\n\n";

// Check Telegram channel
$telegramChannel = DB::table('notification_channels')
    ->where('type', 'telegram')
    ->where('is_enabled', true)
    ->first();

if (!$telegramChannel) {
    echo "❌ No enabled Telegram channel found!\n";
    echo "Please enable Telegram channel in dashboard first.\n";
    exit(1);
}

echo "✅ Telegram Channel: {$telegramChannel->name}\n\n";

// Create test incident
$incident = Incident::create([
    'monitor_id' => $monitor->id,
    'status' => 'open',
    'started_at' => now(),
    'error_message' => 'TEST: Simulated incident for Telegram notification test - ' . now(),
    'failure_count' => 1,
]);

echo "🚨 Created test incident ID: {$incident->id}\n\n";

// Dispatch notification
echo "📤 Dispatching DOWN notification to Telegram...\n";

SendNotification::dispatch(
    $monitor,
    'down',
    $incident
);

echo "✅ Notification job dispatched to queue!\n\n";

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "⏳ Processing queue...\n\n";
// Process the queue
$exitCode = Artisan::call('queue:work', [
    'connection' => 'database',
    '--queue' => 'notifications',
    '--once' => true,
    '--verbose' => true,
]);

if ($exitCode === 0) {
    echo "\n✅ Queue processed successfully!\n";
    echo "📱 Check your Telegram for the notification\n\n";
} else {
    echo "\n❌ Queue processing failed!\n";
    echo "Check logs: storage/logs/laravel.log\n\n";
}

// Resolve the incident
echo "Resolving test incident...\n";
$incident->update([
    'status' => 'resolved',
    'resolved_at' => now()
]);

echo "✅ Test complete!\n";
