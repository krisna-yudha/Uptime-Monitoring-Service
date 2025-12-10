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
        Schema::table('monitors', function (Blueprint $table) {
            $table->timestampTz('ssl_cert_expiry')->nullable()->after('pause_until');
            $table->string('ssl_cert_issuer')->nullable()->after('ssl_cert_expiry');
            $table->timestampTz('ssl_checked_at')->nullable()->after('ssl_cert_issuer');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('monitors', function (Blueprint $table) {
            $table->dropColumn(['ssl_cert_expiry', 'ssl_cert_issuer', 'ssl_checked_at']);
        });
    }
};
