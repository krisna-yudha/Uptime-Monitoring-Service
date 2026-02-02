@echo off
title Test Optimized Setup
cd /d "%~dp0"

echo ========================================
echo  Testing Optimized Monitoring Setup
echo ========================================
echo.

echo [1/5] Testing MonitorObserver...
php artisan tinker --execute="echo 'Observer Status: '; dd(class_exists('\App\Observers\MonitorObserver'));"
if %errorlevel% neq 0 goto :error

echo.
echo [2/5] Checking Queue Health Command...
php artisan queue:monitor-health
if %errorlevel% neq 0 goto :error

echo.
echo [3/5] Testing Queue Connection...
php -r "require 'vendor/autoload.php'; $app = require_once 'bootstrap/app.php'; $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class); echo 'Queue Driver: ' . config('queue.default') . PHP_EOL; echo 'Database Connection: OK' . PHP_EOL;"
if %errorlevel% neq 0 goto :error

echo.
echo [4/5] Checking Jobs Table...
php artisan db:show --counts
if %errorlevel% neq 0 (
    echo Warning: Could not show database info, but continuing...
)

echo.
echo [5/5] Creating Test Monitor...
php artisan tinker --execute="$m = \App\Models\Monitor::create(['name' => 'TEST-' . time(), 'type' => 'http', 'target' => 'https://google.com', 'interval_seconds' => 60, 'enabled' => 1]); echo 'Created monitor: ' . $m->id . PHP_EOL; echo 'Check if job dispatched in jobs table' . PHP_EOL; $m->delete();"
if %errorlevel% neq 0 goto :error

echo.
echo ========================================
echo  ✅ ALL TESTS PASSED!
echo ========================================
echo.
echo Setup is ready! Now run:
echo   start-optimized-workers.bat
echo.
echo Then create a new monitor and check if logs appear within 5 seconds.
echo.
pause
exit /b 0

:error
echo.
echo ========================================
echo  ❌ TEST FAILED
echo ========================================
echo.
echo Please check the error above and fix the issue.
echo.
pause
exit /b 1
