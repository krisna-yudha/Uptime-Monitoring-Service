@echo off
title Notification Queue Worker
color 0E
echo.
echo ============================================
echo    NOTIFICATION QUEUE WORKER
echo ============================================
echo.
echo Starting notification worker...
echo This worker will process notification jobs
echo and send alerts to configured channels.
echo.
echo Press Ctrl+C to stop the worker
echo.

cd /d "%~dp0"
php artisan worker:notifications --verbose

pause
