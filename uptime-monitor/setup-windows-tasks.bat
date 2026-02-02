@echo off
REM Run this script as Administrator

echo ========================================
echo  Windows Scheduled Tasks Setup
echo  for Uptime Monitor
echo ========================================
echo.
echo This will create scheduled tasks for:
echo  - Laravel Scheduler (every minute)
echo  - Queue Health Check (every 5 minutes)
echo  - Queue Cleanup (every 30 minutes)
echo.
echo Press Ctrl+C to cancel...
pause
echo.

SET PROJECT_PATH=%~dp0
SET PHP_PATH=c:\xampp\php\php.exe

echo Using PHP: %PHP_PATH%
echo Project Path: %PROJECT_PATH%
echo.

REM Check if running as administrator
net session >nul 2>&1
if %errorlevel% neq 0 (
    echo ERROR: This script must be run as Administrator!
    echo Right-click the file and select "Run as administrator"
    pause
    exit /b 1
)

echo Creating scheduled tasks...
echo.

REM Laravel Scheduler - runs every minute
echo [1/3] Creating Laravel Scheduler task...
schtasks /create /tn "UptimeMonitor_Scheduler" /tr "\"%PHP_PATH%\" \"%PROJECT_PATH%artisan\" schedule:run" /sc minute /mo 1 /f /rl HIGHEST
if %errorlevel% equ 0 (
    echo  [OK] Laravel Scheduler created
) else (
    echo  [FAIL] Failed to create Laravel Scheduler
)
echo.

REM Queue Health Check - every 5 minutes
echo [2/3] Creating Queue Health Check task...
schtasks /create /tn "UptimeMonitor_QueueHealth" /tr "\"%PHP_PATH%\" \"%PROJECT_PATH%artisan\" queue:monitor-health" /sc minute /mo 5 /f /rl HIGHEST
if %errorlevel% equ 0 (
    echo  [OK] Queue Health Check created
) else (
    echo  [FAIL] Failed to create Queue Health Check
)
echo.

REM Queue Cleanup - every 30 minutes
echo [3/3] Creating Queue Cleanup task...
schtasks /create /tn "UptimeMonitor_QueueCleanup" /tr "\"%PHP_PATH%\" \"%PROJECT_PATH%artisan\" queue:monitor-health --cleanup --max-age=3600" /sc minute /mo 30 /f /rl HIGHEST
if %errorlevel% equ 0 (
    echo  [OK] Queue Cleanup created
) else (
    echo  [FAIL] Failed to create Queue Cleanup
)
echo.

echo ========================================
echo  Setup Complete!
echo ========================================
echo.
echo Tasks created:
schtasks /query /tn "UptimeMonitor*" /fo LIST
echo.

echo ========================================
echo  Management Commands:
echo ========================================
echo.
echo View all tasks:
echo   schtasks /query /tn "UptimeMonitor*"
echo.
echo Run task manually:
echo   schtasks /run /tn "UptimeMonitor_Scheduler"
echo.
echo Delete all tasks:
echo   schtasks /delete /tn "UptimeMonitor_Scheduler" /f
echo   schtasks /delete /tn "UptimeMonitor_QueueHealth" /f
echo   schtasks /delete /tn "UptimeMonitor_QueueCleanup" /f
echo.
echo ========================================
echo.
pause
