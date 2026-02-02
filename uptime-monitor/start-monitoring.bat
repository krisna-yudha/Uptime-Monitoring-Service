@echo off
echo ============================================
echo    UPTIME MONITOR - AUTO START SERVICES
echo ============================================
echo.

REM Check if we're in the right directory
if not exist "artisan" (
    echo ERROR: artisan file not found!
    echo Make sure you're running this from the Laravel project directory.
    pause
    exit /b 1
)

echo [%time%] Starting Laravel Uptime Monitor Services...
echo.

REM Start Laravel Server in background
echo [%time%] 1/7 Starting Laravel Server (http://localhost:8000)...
start "Laravel Server" /min cmd /c "php artisan serve --host=0.0.0.0 --port=8000"
timeout /t 3 /nobreak >nul

REM Start Priority Queue Worker (for NEW monitors - fast response)
echo [%time%] 2/7 Starting Priority Queue Worker (monitor-checks-priority)...
start "Priority Queue Worker" cmd /c "color 0A && php artisan queue:work database --queue=monitor-checks-priority --sleep=1 --tries=3 --timeout=300 --verbose"
timeout /t 2 /nobreak >nul

REM Start Regular Queue Worker (for EXISTING monitors)
echo [%time%] 3/7 Starting Regular Queue Worker (monitor-checks)...
start "Regular Queue Worker" cmd /c "color 0B && php artisan queue:work database --queue=monitor-checks --sleep=3 --tries=3 --timeout=300 --verbose"
timeout /t 2 /nobreak >nul

REM Start Notification Worker
echo [%time%] 4/7 Starting Notification Worker...
start "Notification Worker" cmd /c "color 0E && php artisan worker:notifications --verbose"
timeout /t 2 /nobreak >nul

REM Start Queue Health Monitor (auto-check every 5 minutes + auto-cleanup)
echo [%time%] 5/7 Starting Queue Health Monitor...
start "Queue Health Monitor" /min cmd /c "color 0D && php artisan queue:monitor-health --watch --interval=300"
timeout /t 2 /nobreak >nul

REM Start Monitor Checks Scheduler (if using scheduler mode)
REM Uncomment if you want to use scheduler instead of queue
REM echo [%time%] 6/7 Starting Monitor Checks Scheduler...
REM start "Monitor Checks Scheduler" /min cmd /c "php artisan monitor:check --loop"
REM timeout /t 2 /nobreak >nul

REM Start Frontend Dev Server (optional - comment out if not needed)
echo [%time%] 6/7 Starting Frontend Dev Server (http://localhost:5173)...
cd ..\uptime-frontend
if exist "package.json" (
    start "Frontend Server" /min cmd /c "npm run dev"
    cd ..\uptime-monitor
    timeout /t 2 /nobreak >nul
) else (
    echo WARNING: Frontend not found, skipping...
    cd ..\uptime-monitor
)

REM Start Laravel Scheduler (runs cron jobs)
echo [%time%] 7/7 Starting Laravel Scheduler...
start "Laravel Scheduler" /min cmd /c "php artisan schedule:work"
timeout /t 1 /nobreak >nul

echo.
echo ============================================
echo   ALL SERVICES STARTED SUCCESSFULLY! 
echo ============================================
echo.
echo Services running:
echo  ^> Laravel Server: http://localhost:8000
echo  ^> Priority Queue Worker: Queue 'monitor-checks-priority' (NEW monitors - sleep 1s)
echo  ^> Regular Queue Worker: Queue 'monitor-checks' (existing monitors - sleep 3s)
echo  ^> Notification Worker: Queue 'notifications' (auto send to bots)
echo  ^> Queue Health Monitor: Auto-check every 5 min + auto-cleanup
echo  ^> Laravel Scheduler: Background task scheduler
echo  ^> Frontend Server: http://localhost:5173
echo.
echo ============================================
echo   OPTIMIZED SETUP ACTIVE!
echo ============================================
echo  Monitor baru ^> log muncul dalam 2-5 detik (priority queue)
echo  Jobs auto-cleanup setiap 30 menit (queue health monitor)
echo  Alert otomatis jika queue overflow
echo.
echo Check running services:
echo   tasklist /fi "imagename eq php.exe"
echo.
echo To view worker logs:
echo   Open the colored terminal windows
echo   Green  = Priority Queue (new monitors)
echo   Cyan   = Regular Queue (existing monitors)
echo   Yellow = Notifications
echo   Pink   = Queue Health Monitor
echo.
echo To stop all services:
echo   run "stop-monitoring.bat"
echo.
echo ============================================
echo   SISTEM NOTIFIKASI BOT AKTIF!
echo ============================================
echo Bot akan otomatis mengirim notifikasi ke Discord/Telegram/Slack
echo saat ada incident (service down).
echo.
echo Buka UI: http://localhost:5173
echo.
echo Services will keep running in background...
timeout /t 3 /nobreak >nul