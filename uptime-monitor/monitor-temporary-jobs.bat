@echo off
REM ====================================================================
REM   TEMPORARY JOB SYSTEM - MONITORING TOOLS
REM   Quick access untuk monitor queue health
REM ====================================================================

:menu
cls
echo ╔═══════════════════════════════════════════════════════════╗
echo ║        TEMPORARY JOB SYSTEM - MONITORING TOOLS           ║
echo ╚═══════════════════════════════════════════════════════════╝
echo.
echo  [1] Queue Health Status
echo  [2] Watch Queue (Real-time monitoring)
echo  [3] Process Single Job (Test)
echo  [4] Full Test (Clear + Dispatch + Process)
echo  [5] Clear All Jobs (Emergency)
echo  [6] Show Recent Jobs
echo  [0] Exit
echo.
echo ═══════════════════════════════════════════════════════════
set /p choice="Select option: "

if "%choice%"=="1" goto health
if "%choice%"=="2" goto watch
if "%choice%"=="3" goto process
if "%choice%"=="4" goto fulltest
if "%choice%"=="5" goto clear
if "%choice%"=="6" goto recent
if "%choice%"=="0" goto end
goto menu

:health
cls
echo ═══════════════════════════════════════════════════════════
echo   QUEUE HEALTH STATUS
echo ═══════════════════════════════════════════════════════════
echo.
php artisan queue:monitor-health
echo.
pause
goto menu

:watch
cls
echo ═══════════════════════════════════════════════════════════
echo   REAL-TIME QUEUE MONITORING (Ctrl+C to stop)
echo ═══════════════════════════════════════════════════════════
echo.
:watchloop
php artisan queue:monitor-health
timeout /t 5 /nobreak >nul
goto watchloop

:process
cls
echo ═══════════════════════════════════════════════════════════
echo   PROCESS SINGLE JOB
echo ═══════════════════════════════════════════════════════════
echo.
echo Before:
php artisan queue:monitor-health
echo.
echo Processing job...
php artisan queue:work --once --queue=monitor-checks-priority,monitor-checks
echo.
echo After:
php artisan queue:monitor-health
echo.
pause
goto menu

:fulltest
cls
.\test-temporary-job.bat
pause
goto menu

:clear
cls
echo ═══════════════════════════════════════════════════════════
echo   EMERGENCY: CLEAR ALL JOBS
echo ═══════════════════════════════════════════════════════════
echo.
set /p confirm="Are you sure? This will delete ALL jobs! (y/n): "
if /i "%confirm%"=="y" (
    php artisan queue:flush
    echo.
    echo ✓ All jobs cleared
) else (
    echo.
    echo ✗ Operation cancelled
)
echo.
pause
goto menu

:recent
cls
echo ═══════════════════════════════════════════════════════════
echo   RECENT JOBS IN QUEUE
echo ═══════════════════════════════════════════════════════════
echo.
php -r "require 'vendor/autoload.php'; $app = require 'bootstrap/app.php'; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); $jobs = DB::table('jobs')->orderBy('created_at', 'desc')->limit(10)->get(['id', 'queue', 'attempts', 'created_at']); foreach($jobs as $job) { $created = date('Y-m-d H:i:s', $job->created_at); echo \"{$job->id} | {$job->queue} | Attempts: {$job->attempts} | {$created}\n\"; }"
echo.
pause
goto menu

:end
echo.
echo Goodbye!
exit
