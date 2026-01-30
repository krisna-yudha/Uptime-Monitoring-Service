@echo off
echo ╔═══════════════════════════════════════════════════════════╗
echo ║  TEST: MONITOR BARU AUTO-DISPATCH                        ║
echo ╚═══════════════════════════════════════════════════════════╝
echo.

REM Step 1: Start worker in background
echo [1/4] Starting queue worker...
start /B php artisan queue:work --once --queue=monitor-checks-priority,monitor-checks >nul 2>&1
timeout /t 1 /nobreak >nul
echo      ✓ Worker ready

REM Step 2: Check queue before
echo.
echo [2/4] Queue status BEFORE creating monitor:
php artisan queue:monitor-health

REM Step 3: Simulate creating a new monitor (Observer will fire)
echo.
echo [3/4] Simulating new monitor creation...
php -r "require 'vendor/autoload.php'; $app = require 'bootstrap/app.php'; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); $monitor = App\Models\Monitor::create(['name' => 'Test Auto Dispatch', 'type' => 'http', 'target' => 'http://example.com', 'interval_seconds' => 60, 'enabled' => true]); echo 'Monitor created: ID ' . $monitor->id . PHP_EOL;"
echo      ✓ Monitor created (Observer should auto-dispatch job)

REM Step 4: Check queue after (should have +1 job from Observer)
echo.
timeout /t 2 /nobreak >nul
echo [4/4] Queue status AFTER creating monitor:
php artisan queue:monitor-health

echo.
echo ╔═══════════════════════════════════════════════════════════╗
echo ║  EXPECTED RESULT                                         ║
echo ╚═══════════════════════════════════════════════════════════╝
echo.
echo ✅ Priority queue should have +1 job (from Observer)
echo ✅ Observer auto-dispatched first check
echo ✅ Worker will process it automatically
echo.
echo Run worker to process: php artisan queue:work --once
echo.
pause
