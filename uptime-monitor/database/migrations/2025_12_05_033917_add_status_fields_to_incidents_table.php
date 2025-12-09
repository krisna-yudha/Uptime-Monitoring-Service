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
        Schema::table('incidents', function (Blueprint $table) {
            $table->enum('status', ['open', 'investigating', 'pending', 'resolved'])->default('open')->after('resolved');
            $table->enum('alert_status', ['none', 'notified', 'critical_sent', 'acknowledged', 'escalated'])->default('none')->after('status');
            $table->timestamp('acknowledged_at')->nullable()->after('alert_status');
            $table->string('acknowledged_by')->nullable()->after('acknowledged_at');
            $table->json('alert_log')->nullable()->after('acknowledged_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            $table->dropColumn(['status', 'alert_status', 'acknowledged_at', 'acknowledged_by', 'alert_log']);
        });
    }
};
