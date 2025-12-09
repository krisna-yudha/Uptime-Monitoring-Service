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
echo [%time%] 1/4 Starting Laravel Server (http://localhost:8000)...
start "Laravel Server" /min cmd /c "php artisan serve --host=0.0.0.0 --port=8000"
timeout /t 3 /nobreak >nul

REM Start Monitor Checks Worker in background
echo [%time%] 2/4 Starting Monitor Checks Worker...
start "Monitor Checks Worker" /min cmd /c "php artisan worker:monitor-checks --verbose"
timeout /t 2 /nobreak >nul

REM Start Notification Worker in background
echo [%time%] 3/4 Starting Notification Worker...
start "Notification Worker" /min cmd /c "php artisan worker:notifications --verbose"
timeout /t 2 /nobreak >nul

REM Start Frontend Dev Server (optional - comment out if not needed)
echo [%time%] 4/4 Starting Frontend Dev Server (http://localhost:5173)...
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
echo  ^> Monitor Checks Worker: Queue 'monitor-checks' (realtime monitoring)
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
echo Press any key to keep this window open...
pause