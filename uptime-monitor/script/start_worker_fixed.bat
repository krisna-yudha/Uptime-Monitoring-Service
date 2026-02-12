@echo off
title Queue Worker - Monitor Checks (with timeout fix)
color 0A

echo.
echo ============================================================
echo    UPTIME MONITOR - QUEUE WORKER (TIMEOUT FIX APPLIED)
echo ============================================================
echo.
echo KONFIGURASI:
echo  - Queue Connection: database
echo  - Timeout: 300 seconds (5 minutes)
echo  - Max Tries: 3
echo  - Sleep: 1 second
echo  - Queue: monitor-checks
echo.
echo Fix yang diterapkan:
echo  [✓] Timeout property ditambahkan ke ProcessMonitorCheck
echo  [✓] Queue connection diubah dari sync ke database
echo  [✓] Retry after dinaikkan ke 300 detik
echo.
echo ============================================================
echo.

cd /d "%~dp0"

echo Starting queue worker with extended timeout...
echo.

php artisan queue:work ^
  --queue=monitor-checks ^
  --sleep=1 ^
  --tries=3 ^
  --timeout=300 ^
  --verbose

echo.
echo Worker stopped.
pause
