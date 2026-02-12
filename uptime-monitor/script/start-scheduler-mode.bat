@echo off
echo ================================================
echo   UPTIME MONITOR - Scheduler Mode (No Queue)
echo ================================================
echo.
echo Starting monitoring loop...
echo Press Ctrl+C to stop
echo.

:loop
php artisan monitor:check
timeout /t 5 /nobreak >nul
goto loop
