<?php


use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "========================================\n";
echo "CHECKING INCIDENTS DATA\n";
echo "========================================\n\n";

$total = App\Models\Incident::count();
echo "Total Incidents: $total\n\n";

if ($total > 0) {
    echo "Latest 10 Incidents:\n";
    echo str_repeat("-", 100) . "\n";
    
    $incidents = App\Models\Incident::with('monitor')
        ->orderBy('id', 'desc')
        ->limit(10)
        ->get();
    
    foreach ($incidents as $incident) {
        $monitorName = $incident->monitor ? $incident->monitor->name : 'N/A';
        echo sprintf(
            "ID: %-5d | Monitor: %-30s | Status: %-10s | Started: %s\n",
            $incident->id,
            substr($monitorName, 0, 30),
            $incident->status,
            $incident->started_at
        );
    }
    
    echo str_repeat("-", 100) . "\n\n";
    
    // Count by status
    echo "Incidents by Status:\n";

    $statuses = App\Models\Incident::select('status', DB::raw('count(*) as total'))
        ->groupBy('status')
        ->get();
    
    foreach ($statuses as $status) {
        echo sprintf("  %s: %d\n", strtoupper($status->status), $status->total);
    }
} else {
    echo "No incidents found in database.\n";
}

echo "\n========================================\n";
