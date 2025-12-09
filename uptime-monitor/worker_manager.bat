@echo off
setlocal enabledelayedexpansion

:menu
cls
echo.
echo ============================================
echo    UPTIME MONITOR - WORKER MANAGER
echo ============================================
echo.
echo  1. Start Monitor Checks Worker
echo  2. Start Notification Worker
echo  3. Start ALL Workers
echo  4. View Queue Status
echo  5. View Failed Jobs
echo  6. Retry Failed Jobs
echo  7. Clear All Jobs
echo  0. Exit
echo.
echo ============================================
echo.

set /p choice="Select option (0-7): "

if "%choice%"=="1" goto start_monitor
if "%choice%"=="2" goto start_notification
if "%choice%"=="3" goto start_all
if "%choice%"=="4" goto queue_status
if "%choice%"=="5" goto failed_jobs
if "%choice%"=="6" goto retry_failed
if "%choice%"=="7" goto clear_jobs
if "%choice%"=="0" goto end

echo Invalid choice!
timeout /t 2 /nobreak > nul
goto menu

:start_monitor
echo.
echo Starting Monitor Checks Worker...
start "Monitor Checks Worker" cmd /k "color 0A && php artisan worker:monitor-checks --verbose"
echo Worker started in new window.
timeout /t 2 /nobreak > nul
goto menu

:start_notification
echo.
echo Starting Notification Worker...
start "Notification Worker" cmd /k "color 0E && php artisan worker:notifications --verbose"
echo Worker started in new window.
timeout /t 2 /nobreak > nul
goto menu

:start_all
echo.
echo Starting all workers...
start "Monitor Checks Worker" cmd /k "color 0A && php artisan worker:monitor-checks --verbose"
timeout /t 1 /nobreak > nul
start "Notification Worker" cmd /k "color 0E && php artisan worker:notifications --verbose"
echo.
echo All workers started!
timeout /t 3 /nobreak > nul
goto menu

:queue_status
echo.
echo ============================================
echo    QUEUE STATUS
echo ============================================
echo.
php artisan queue:monitor monitor-checks notifications
echo.
pause
goto menu

:failed_jobs
echo.
echo ============================================
echo    FAILED JOBS
echo ============================================
echo.
php artisan queue:failed
echo.
pause
goto menu

:retry_failed
echo.
echo Retrying all failed jobs...
php artisan queue:retry all
echo.
echo Done!
timeout /t 2 /nobreak > nul
goto menu

:clear_jobs
echo.
echo WARNING: This will clear all pending jobs!
set /p confirm="Are you sure? (y/n): "
if /i "%confirm%"=="y" (
    php artisan queue:clear
    echo All jobs cleared!
) else (
    echo Cancelled.
)
timeout /t 2 /nobreak > nul
goto menu

:end
echo.
echo Goodbye!
timeout /t 1 /nobreak > nul
exit
