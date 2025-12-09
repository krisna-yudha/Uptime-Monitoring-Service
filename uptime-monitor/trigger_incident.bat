@echo off
echo ============================================
echo    TRIGGER INCIDENT - Test Notifikasi Bot
echo ============================================
echo.
echo Script ini akan:
echo 1. Memaksa salah satu monitor down
echo 2. Menjalankan monitor check
echo 3. Incident akan terbuat
echo 4. Notifikasi akan masuk queue
echo 5. Worker akan kirim ke bot Discord
echo.
echo Pastikan kedua worker sudah berjalan:
echo   - Monitor Checks Worker (worker_manager.bat)
echo   - Notification Worker (run_notification_worker.bat)
echo.
pause

echo.
echo [1/5] Memilih monitor untuk test...
php -r "require 'vendor/autoload.php'; $app = require 'bootstrap/app.php'; $app->make('Illuminate\Contracts\Console\Kernel')->bootstrap(); $monitors = App\Models\Monitor::where('enabled', true)->get(); echo 'Available Monitors:' . PHP_EOL; foreach($monitors as $m) { echo '  [' . $m->id . '] ' . $m->name . ' - ' . $m->target . ' (Status: ' . $m->last_status . ')' . PHP_EOL; }"

echo.
set /p MONITOR_ID="Masukkan ID monitor yang ingin di-test (pilih yang sedang UP): "

echo.
echo [2/5] Mengambil data monitor #%MONITOR_ID%...
php -r "require 'vendor/autoload.php'; $app = require 'bootstrap/app.php'; $app->make('Illuminate\Contracts\Console\Kernel')->bootstrap(); $m = App\Models\Monitor::find(%MONITOR_ID%); if(!$m) { echo 'Monitor tidak ditemukan!'; exit(1); } echo 'Monitor: ' . $m->name . PHP_EOL; echo 'Target: ' . $m->target . PHP_EOL; echo 'Status: ' . $m->last_status . PHP_EOL; echo 'Channels: ' . count($m->notification_channels ?? []) . PHP_EOL;"

echo.
echo [3/5] Membuat target sementara TIDAK REACHABLE...
echo (Kita akan ubah target ke URL yang pasti down)
php -r "require 'vendor/autoload.php'; $app = require 'bootstrap/app.php'; $app->make('Illuminate\Contracts\Console\Kernel')->bootstrap(); $m = App\Models\Monitor::find(%MONITOR_ID%); $m->update(['target' => 'http://localhost:99999/test-down']); echo 'Target diubah ke: ' . $m->target . PHP_EOL;"

echo.
echo [4/5] Menjalankan monitor check (ini akan buat incident)...
php artisan queue:work database --queue=monitor-checks --once --verbose

echo.
echo [5/5] Cek apakah ada job notifikasi di queue...
php -r "require 'vendor/autoload.php'; $app = require 'bootstrap/app.php'; $app->make('Illuminate\Contracts\Console\Kernel')->bootstrap(); $jobs = DB::table('jobs')->where('queue', 'notifications')->count(); echo 'Notification jobs in queue: ' . $jobs . PHP_EOL; if($jobs > 0) { echo 'BERHASIL! Job notifikasi sudah masuk queue.' . PHP_EOL; echo 'Worker akan memproses dan mengirim ke bot Discord.' . PHP_EOL; } else { echo 'GAGAL! Tidak ada job notifikasi.' . PHP_EOL; }"

echo.
echo [RESTORE] Mengembalikan target monitor ke semula...
echo Silakan restore manual di UI: http://localhost:5173/monitors
echo Atau jalankan: php artisan tinker
echo   lalu: $m = App\Models\Monitor::find(%MONITOR_ID%); $m->update(['target' => 'TARGET_ASLI']);
echo.
pause
