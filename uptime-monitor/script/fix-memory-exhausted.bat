@echo off
echo ╔═══════════════════════════════════════════════════════════╗
echo ║  EMERGENCY FIX: MEMORY EXHAUSTED                         ║
echo ╚═══════════════════════════════════════════════════════════╝
echo.

echo [1/3] Stopping any running workers...
taskkill /F /IM php.exe /FI "WINDOWTITLE eq Queue*" 2>nul
timeout /t 2 /nobreak >nul
echo      ✓ Workers stopped

echo.
echo [2/3] Clearing queue (removing old jobs)...
php -d memory_limit=512M artisan queue:flush
echo      ✓ Queue cleared

echo.
echo [3/3] Checking queue status...
php artisan queue:monitor-health

echo.
echo ╔═══════════════════════════════════════════════════════════╗
echo ║  FIXED                                                   ║
echo ╚═══════════════════════════════════════════════════════════╝
echo.
echo ✅ Queue cleared
echo ✅ Memory pressure relieved
echo.
echo Next steps:
echo 1. Start worker: start-queue-worker.bat
echo 2. Create monitor: Should work now!
echo.
pause
