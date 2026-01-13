<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\Monitor;

echo "=== FULL QUEUE RESET ===\n\n";

// Delete ALL jobs
$deleted = DB::table('jobs')->delete();
echo "✅ Deleted all jobs: {$deleted}\n\n";

// Get all enabled monitors
$monitors = Monitor::where('enabled', true)
    ->whereNull('pause_until')
    ->orWhere('pause_until', '<', now())
    ->get();

echo "=== Re-dispatching monitors ===\n";
echo "Total enabled monitors: {$monitors->count()}\n\n";

$dispatched = 0;
foreach ($monitors as $monitor) {
    if ($monitor->type !== 'push') {
        try {
            \App\Jobs\ProcessMonitorCheck::dispatch($monitor)
                ->onQueue('monitor-checks-priority');
            $dispatched++;
            echo "✅ Dispatched: #{$monitor->id} - {$monitor->name} ({$monitor->target})\n";
        } catch (\Exception $e) {
            echo "❌ Failed: #{$monitor->id} - {$e->getMessage()}\n";
        }
    }
}

echo "\n=== Summary ===\n";
echo "Dispatched: {$dispatched}\n";
echo "Jobs in queue: " . DB::table('jobs')->count() . "\n";
echo "\nNow start queue worker:\n";
echo "php artisan queue:work --queue=monitor-checks-priority,monitor-checks,default\n";
