@echo off
echo ╔═══════════════════════════════════════════════════════════╗
echo ║  TEMPORARY JOB SYSTEM - TEST & VERIFICATION              ║
echo ╚═══════════════════════════════════════════════════════════╝
echo.

REM Step 1: Clear old jobs for clean test
echo [1/5] Clearing queue for clean test...
php artisan queue:flush
echo      ✓ Queue cleared
echo.

REM Step 2: Dispatch one test job
echo [2/5] Dispatching one test job...
php artisan monitor:check --monitor-id=1
echo      ✓ Job dispatched
echo.

REM Step 3: Check queue before processing
echo [3/5] Queue status BEFORE processing:
php artisan queue:monitor-health
echo.

REM Step 4: Process the job
echo [4/5] Processing job (will auto-delete after completion)...
php artisan queue:work --once --queue=monitor-checks-priority,monitor-checks
echo      ✓ Job processed
echo.

REM Step 5: Check queue after processing
echo [5/5] Queue status AFTER processing:
php artisan queue:monitor-health
echo.

echo ╔═══════════════════════════════════════════════════════════╗
echo ║  EXPECTED RESULT                                         ║
echo ╚═══════════════════════════════════════════════════════════╝
echo.
echo ✅ Job should be GONE from queue after processing
echo ✅ No auto-requeue (job does NOT create another job)
echo ✅ Scheduler (cron) will create new jobs every 10 seconds
echo.
echo ═══════════════════════════════════════════════════════════
pause
