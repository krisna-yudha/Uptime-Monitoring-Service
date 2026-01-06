<?php

require 'vendor/autoload.php';

$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

// Check table structure first
echo "=== Failed Jobs Table Structure ===\n";
try {
    $columns = DB::select("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'failed_jobs'");
    foreach ($columns as $column) {
        echo $column->column_name . ' - ' . $column->data_type . "\n";
    }
} catch (Exception $e) {
    echo "Error checking table structure: " . $e->getMessage() . "\n";
}

echo "\n=== Failed Jobs ===\n";
try {
    $failed = DB::table('failed_jobs')->orderBy('failed_at', 'desc')->first();

    if ($failed) {
        echo "=== Failed Job Details ===\n";
        echo "ID: " . $failed->id . "\n";
        echo "Connection: " . $failed->connection . "\n";
        echo "Queue: " . $failed->queue . "\n";
        echo "Failed At: " . $failed->failed_at . "\n";
        echo "\n=== Payload ===\n";
        $payload = json_decode($failed->payload, true);
        print_r($payload);
        echo "\n=== Exception ===\n";
        echo $failed->exception . "\n";
    } else {
        echo "No failed jobs found.\n";
    }
} catch (Exception $e) {
    echo "Error reading failed jobs: " . $e->getMessage() . "\n";
}