<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== RESETTING MONITOR SCHEDULES ===\n\n";

// Reset all enabled monitors to check now (set to null for immediate check)
$updated = DB::table('monitors')
    ->where('enabled', true)
    ->update([
        'next_check_at' => null
    ]);

echo "✅ Reset {$updated} monitor(s) to check immediately\n\n";

// Show updated status
$monitors = DB::table('monitors')
    ->where('enabled', true)
    ->get(['name', 'next_check_at']);

echo "📋 Updated Monitor Schedules:\n";
foreach ($monitors as $monitor) {
    $nextCheck = date('Y-m-d H:i:s', strtotime($monitor->next_check_at));
    echo "  - {$monitor->name}: {$nextCheck}\n";
}

echo "\n✅ All monitors reset! They will be checked immediately.\n";
