@echo off
echo ================================================
echo   QUEUE HEALTH MONITOR - Development Mode
echo ================================================
echo.

:loop
echo [%date% %time%] Running queue health check...
php artisan queue:monitor-health

echo [%date% %time%] Waiting 5 minutes...
timeout /t 300 /nobreak >nul

goto loop
