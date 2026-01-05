<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('monitor_metrics_aggregated', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monitor_id')->constrained('monitors')->cascadeOnDelete();
            $table->enum('interval', ['minute', 'hour', 'day']); // Granularity level
            $table->timestamp('period_start'); // Start of aggregation period
            $table->timestamp('period_end'); // End of aggregation period
            
            // Aggregated metrics
            $table->integer('total_checks')->default(0); // Total number of checks
            $table->integer('successful_checks')->default(0); // UP checks
            $table->integer('failed_checks')->default(0); // DOWN checks
            $table->decimal('uptime_percentage', 5, 2)->nullable(); // Uptime %
            
            // Response time metrics
            $table->decimal('avg_response_time', 8, 3)->nullable(); // Average latency
            $table->decimal('min_response_time', 8, 3)->nullable(); // Minimum latency
            $table->decimal('max_response_time', 8, 3)->nullable(); // Maximum latency
            $table->decimal('median_response_time', 8, 3)->nullable(); // Median latency
            
            // Additional stats
            $table->integer('incident_count')->default(0); // Number of incidents
            $table->decimal('total_downtime_seconds', 10, 2)->default(0); // Total downtime
            
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['monitor_id', 'interval', 'period_start']);
            $table->index(['period_start', 'period_end']);
            $table->unique(['monitor_id', 'interval', 'period_start'], 'unique_monitor_period');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('monitor_metrics_aggregated');
    }
};
