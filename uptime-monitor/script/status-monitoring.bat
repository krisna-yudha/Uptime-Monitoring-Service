@echo off
echo ============================================
echo      UPTIME MONITOR - SERVICE STATUS
echo ============================================
echo.

echo [%time%] Checking service status...
echo.

REM Check Laravel Server
echo === LARAVEL SERVER ===
tasklist /fi "windowtitle eq Laravel Server*" | find "php.exe" >nul 2>&1
if %errorlevel% equ 0 (
    echo Status: RUNNING ✓
    netstat -an | find ":8000" | find "LISTENING" >nul 2>&1
    if %errorlevel% equ 0 (
        echo Port 8000: LISTENING ✓
        echo URL: http://localhost:8000
    ) else (
        echo Port 8000: NOT LISTENING ✗
    )
) else (
    echo Status: NOT RUNNING ✗
)
echo.

REM Check Queue Worker
echo === QUEUE WORKER ===
tasklist /fi "windowtitle eq Queue Worker*" | find "php.exe" >nul 2>&1
if %errorlevel% equ 0 (
    echo Status: RUNNING ✓
) else (
    echo Status: NOT RUNNING ✗
)
echo.

REM Check Scheduler
echo === MONITOR CHECKS ===
tasklist /fi "windowtitle eq Monitor Checks*" | find "php.exe" >nul 2>&1
if %errorlevel% equ 0 (
    echo Status: RUNNING ✓
) else (
    echo Status: NOT RUNNING ✗
)
echo.

echo === DATABASE STATUS ===
php artisan tinker --execute="try { DB::connection()->getPdo(); echo 'Database: CONNECTED ✓' . PHP_EOL; } catch(Exception \$e) { echo 'Database: FAILED ✗ - ' . \$e->getMessage() . PHP_EOL; }" 2>nul
echo.

echo === QUICK COMMANDS ===
echo  - Start all services: start-monitoring.bat
echo  - Stop all services: stop-monitoring.bat  
echo  - View logs: php artisan logs:monitoring --limit=5
echo  - Manual check: php artisan monitor:check
echo.

pause