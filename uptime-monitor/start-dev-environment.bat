@echo off
REM ====================================================================
REM   DEVELOPMENT ENVIRONMENT - COMPLETE SETUP
REM   Starts all required services for monitoring system
REM ====================================================================

echo ╔═══════════════════════════════════════════════════════════╗
echo ║   UPTIME MONITORING - DEVELOPMENT ENVIRONMENT            ║
echo ╚═══════════════════════════════════════════════════════════╝
echo.

REM Check if already running
tasklist /FI "WINDOWTITLE eq Queue Worker*" 2>NUL | find /I /N "php.exe">NUL
if "%ERRORLEVEL%"=="0" (
    echo ✓ Queue worker already running
) else (
    echo [1/2] Starting Queue Worker...
    start "Queue Worker - Uptime Monitor" cmd /k "cd /d %~dp0 && start-queue-worker.bat"
    timeout /t 2 /nobreak >nul
    echo      ✓ Queue worker started
)

echo.
echo [2/2] Checking queue status...
php artisan queue:monitor-health
echo.

echo ╔═══════════════════════════════════════════════════════════╗
echo ║  SYSTEM READY                                            ║
echo ╚═══════════════════════════════════════════════════════════╝
echo.
echo ✅ Queue worker: RUNNING
echo ✅ Observer: ACTIVE (auto-dispatch on monitor create)
echo ✅ Scheduler: Will run via 'php artisan schedule:run'
echo.
echo 📝 What happens when you create new monitor:
echo    1. Observer dispatches job to priority queue
echo    2. Worker processes job immediately
echo    3. Job auto-deletes after completion
echo    4. Scheduler will create next job after interval
echo.
echo 🔍 Monitor queue: monitor-temporary-jobs.bat
echo 📊 Check status: php artisan queue:monitor-health
echo.
pause
