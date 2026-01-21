./@echo off
title Uptime Monitor - All Workers
color 0B

echo.
echo ============================================
echo    UPTIME MONITOR - STARTING ALL WORKERS
echo ============================================
echo.
echo This will start multiple worker windows:
echo  - Monitor Checks Worker (for service monitoring)
echo  - Notification Worker (for sending alerts)
echo.
echo Each worker will run in a separate window.
echo Close each window individually to stop workers.
echo.
pause

cd /d "%~dp0"

echo.
echo Starting Monitor Checks Worker...
start "Monitor Checks Worker" cmd /k "php artisan worker:monitor-checks --verbose"

timeout /t 2 /nobreak > nul

echo Starting Notification Worker...
start "Notification Worker" cmd /k "php artisan worker:notifications --verbose"

echo.
echo ============================================
echo All workers started successfully!
echo ============================================
echo.
echo Check the individual worker windows for logs.
echo Close this window safely - workers will continue running.
echo.
pause
