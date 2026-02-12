@echo off
title Uptime Monitor - Optimized Workers
cd /d "%~dp0"

echo ========================================
echo  Uptime Monitor - Optimized Workers
echo ========================================
echo.
echo Starting multiple workers for optimal performance...
echo.

REM Priority Queue - untuk monitor baru (responsif, sleep 1s)
echo [1/4] Starting Priority Queue Worker...
start "Priority Queue [monitor-checks-priority]" cmd /k "color 0A && php artisan queue:work database --queue=monitor-checks-priority --tries=3 --timeout=300 --sleep=1 --verbose"
timeout /t 2 /nobreak > nul

REM Regular Queue - untuk monitor existing (sleep 3s)
echo [2/4] Starting Regular Queue Worker...
start "Regular Queue [monitor-checks]" cmd /k "color 0B && php artisan queue:work database --queue=monitor-checks --tries=3 --timeout=300 --sleep=3 --verbose"
timeout /t 2 /nobreak > nul

REM Notification Worker
echo [3/4] Starting Notification Worker...
start "Notification Worker" cmd /k "color 0E && php artisan worker:notifications --verbose"
timeout /t 2 /nobreak > nul

REM Queue Health Monitor with Auto-Cleanup
echo [4/4] Starting Queue Health Monitor...
start "Queue Monitor & Auto-Cleanup" cmd /k "color 0D && php artisan queue:monitor-health --watch --interval=300"

echo.
echo ========================================
echo  All Workers Started Successfully!
echo ========================================
echo.
echo Priority Queue:  Green window  (sleep 1s - fast response)
echo Regular Queue:   Cyan window   (sleep 3s - normal)
echo Notifications:   Yellow window (real-time)
echo Queue Monitor:   Pink window   (every 5 min + auto-cleanup)
echo.
echo Monitor baru akan langsung diproses oleh Priority Queue!
echo Jobs akan auto-cleanup setiap 5 menit.
echo.
echo Tekan Ctrl+C di setiap window untuk stop worker.
echo.
pause
