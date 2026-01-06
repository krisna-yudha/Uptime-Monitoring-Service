<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$latest = DB::table('monitors')->orderBy('id','desc')->first();
if ($latest) {
    echo "Latest monitor ID: {$latest->id}\n";
    echo "Name: {$latest->name}\n";
    echo "Target: {$latest->target}\n";
    echo "Type: {$latest->type}\n";
    echo "Created at: {$latest->created_at}\n";
} else {
    echo "No monitors found\n";
}
