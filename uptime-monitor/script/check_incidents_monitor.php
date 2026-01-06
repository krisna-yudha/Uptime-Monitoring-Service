<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "\n=== Monitors List ===\n\n";
$monitors = DB::table('monitors')->get(['id', 'name', 'type']);
foreach ($monitors as $monitor) {
    echo sprintf("ID: %d | Name: %-25s | Type: %s\n", $monitor->id, $monitor->name, $monitor->type);
}

echo "\n=== Incidents per Monitor ===\n\n";
$incidents = DB::table('incidents')
    ->select('monitor_id', DB::raw('count(*) as total'))
    ->groupBy('monitor_id')
    ->get();

foreach ($incidents as $incident) {
    $monitor = DB::table('monitors')->where('id', $incident->monitor_id)->first();
    echo sprintf("Monitor #%d (%-25s): %d incidents\n", 
        $incident->monitor_id, 
        $monitor ? $monitor->name : 'Unknown',
        $incident->total
    );
}

// Cek apakah ada monitor dengan nama "ping"
echo "\n=== Search for 'ping' monitor ===\n\n";
$pingMonitors = DB::table('monitors')->where('name', 'like', '%ping%')->get();
if ($pingMonitors->isEmpty()) {
    echo "No monitor found with 'ping' in the name.\n";
} else {
    foreach ($pingMonitors as $pm) {
        echo sprintf("Found: ID=%d, Name=%s\n", $pm->id, $pm->name);
        $count = DB::table('incidents')->where('monitor_id', $pm->id)->count();
        echo sprintf("  -> Has %d incidents\n", $count);
    }
}

echo "\n";
