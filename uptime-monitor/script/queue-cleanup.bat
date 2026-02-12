@echo off
echo ================================================
echo   QUEUE CLEANUP - Development Mode
echo ================================================
echo.

:loop
echo [%date% %time%] Running queue cleanup...
php artisan queue:monitor-health --cleanup --max-age=7200

echo [%date% %time%] Cleanup complete. Waiting 1 hour...
timeout /t 3600 /nobreak >nul

goto loop
