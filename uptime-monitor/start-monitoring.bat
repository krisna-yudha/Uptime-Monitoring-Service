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
echo [%time%] 1/5 Starting Laravel Server (http://localhost:8000)...
start "Laravel Server" /min cmd /c "php artisan serve --host=0.0.0.0 --port=8000"
timeout /t 3 /nobreak >nul

REM Start Monitor Checks Scheduler in background
echo [%time%] 2/5 Starting Monitor Checks Scheduler...
start "Monitor Checks Scheduler" cmd /c "php artisan monitor:check --loop"
timeout /t 2 /nobreak >nul

REM Start Monitor Checks Queue Worker in background
echo [%time%] 3/5 Starting Monitor Checks Queue Worker...
start "Monitor Checks Worker" cmd /c "php artisan queue:work database --queue=high-priority,monitor-checks --sleep=3 --tries=3 --verbose"
timeout /t 2 /nobreak >nul

REM Start Notification Worker in background
echo [%time%] 4/5 Starting Notification Worker...
start "Notification Worker" /min cmd /c "php artisan worker:notifications --verbose"
timeout /t 2 /nobreak >nul

REM Start Frontend Dev Server (optional - comment out if not needed)
echo [%time%] 5/5 Starting Frontend Dev Server (http://localhost:5173)...
cd ..\uptime-frontend
start "Frontend Server" /min cmd /c "npm run dev"
cd ..\uptime-monitor
timeout /t 2 /nobreak >nul

echo.
echo ============================================
echo   ALL SERVICES STARTED SUCCESSFULLY! 
echo ============================================
echo.
echo Services running:
echo  ^> Laravel Server: http://localhost:8000
echo  ^> Monitor Checks Scheduler: Continuous loop every 1 second
echo  ^> Monitor Checks Worker: Queue 'monitor-checks' (process monitoring jobs)
echo  ^> Notification Worker: Queue 'notifications' (auto send to Discord/Telegram/Slack)
echo  ^> Frontend Server: http://localhost:5173
echo.
echo Check running services: tasklist /fi "windowtitle eq Laravel*" /fi "windowtitle eq Monitor*" /fi "windowtitle eq Notification*"
echo.
echo To view worker logs: Open the minimized windows
echo To stop all services: run "stop-monitoring.bat"
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