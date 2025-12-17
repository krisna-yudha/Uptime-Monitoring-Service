<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Monitor;

$id = 26;
$monitor = Monitor::find($id);
if (!$monitor) {
    echo "Monitor with ID $id not found\n";
    exit(1);
}

$old = $monitor->target;
$monitor->update(['target' => 'https://absen.semarangkota.go.id/login']);
echo "Monitor ID $id target updated from $old to {$monitor->target}\n";
