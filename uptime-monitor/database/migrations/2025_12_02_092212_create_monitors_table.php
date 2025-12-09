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
        Schema::create('monitors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 50); // http/tcp/ping/keyword/push
            $table->text('target'); // URL/Host/IP/Port target
            $table->json('config')->nullable(); // Konfigurasi (header, body, expected status, dsb)
            $table->integer('interval_seconds')->default(1); // Jarak pengecekan realtime (1 detik)
            $table->integer('timeout_ms')->default(5000); // Timeout Req
            $table->integer('retries')->default(3); // Jumlah retry sebelum dianggap down
            $table->boolean('enabled')->default(true); // Monitor Aktif/tidak
            $table->json('tags')->nullable(); // Tag untuk grouping/filter
            $table->foreignId('created_by')->constrained('users'); // user pembuat monitor
            $table->string('heartbeat_key', 128)->unique()->nullable(); // Key untuk push monitor
            $table->string('last_status', 20)->default('unknown'); // Status terakhir (up/down/unknown)
            $table->timestampTz('last_checked_at')->nullable(); // Waktu terakhir dicek
            $table->timestampTz('next_check_at')->nullable(); // Jadwal pengecekan berikutnya
            $table->timestampTz('pause_until')->nullable(); // Batas waktu pause
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('monitors');
    }
};
