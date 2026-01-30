@echo off
echo ╔═══════════════════════════════════════════════════════════╗
echo ║     QUEUE WORKER - AUTO START (DEVELOPMENT)              ║
echo ╚═══════════════════════════════════════════════════════════╝
echo.
echo Starting queue worker...
echo This will process jobs automatically.
echo.
echo ⚠️  Keep this window open while developing
echo 🛑 Press Ctrl+C to stop
echo.
echo ═══════════════════════════════════════════════════════════
echo.

REM Start queue worker with increased memory limit and automatic restart
php -d memory_limit=512M artisan queue:work --queue=monitor-checks-priority,monitor-checks --tries=3 --timeout=300 --sleep=1 --max-jobs=100
