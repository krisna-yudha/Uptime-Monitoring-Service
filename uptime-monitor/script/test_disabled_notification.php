<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Monitor;
use App\Models\NotificationChannel;
use App\Models\Incident;
use App\Jobs\SendNotification;
use Illuminate\Support\Facades\DB;

echo "=== Test Notification with Disabled Channel ===\n\n";

// Get monitor and channel
$monitor = Monitor::first();
$channel = NotificationChannel::first();

if (!$monitor) {
    echo "❌ No monitor found!\n";
    exit(1);
}

if (!$channel) {
    echo "❌ No channel found!\n";
    exit(1);
}

echo "Monitor: {$monitor->name}\n";
echo "Monitor notification_channels: " . json_encode($monitor->notification_channels) . "\n";
echo "Channel: {$channel->name} ({$channel->type})\n";
echo "Channel Status: " . ($channel->is_enabled ? '✅ ENABLED' : '❌ DISABLED') . "\n\n";

// Create real incident in database for testing
$incident = Incident::create([
    'monitor_id' => $monitor->id,
    'status' => 'open',
    'started_at' => now(),
    'error_message' => 'Test notification with disabled channel - ' . now()->toDateTimeString(),
    'failure_count' => 1,
]);

echo "Created test incident ID: {$incident->id}\n\n";

echo "Dispatching notification job...\n";

// Dispatch notification
SendNotification::dispatch($monitor, 'down', $incident);

echo "✅ Job dispatched to queue 'notifications'\n\n";

// Check queue
$jobsCount = DB::table('jobs')->where('queue', 'notifications')->count();
echo "Jobs in queue: {$jobsCount}\n\n";

if ($jobsCount > 0) {
    echo "Now run the worker to process:\n";
    echo "  php artisan queue:work database --queue=notifications --once --verbose\n\n";
    echo "Check the log for 'Skipping disabled notification channel' message.\n";
}
