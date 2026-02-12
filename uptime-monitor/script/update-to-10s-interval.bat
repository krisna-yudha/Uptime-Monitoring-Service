@echo off
echo ============================================
echo    UPDATING MONITORING INTERVAL TO 10 SECONDS
echo ============================================
echo.

echo This script will update the monitoring system to check every 10 seconds instead of 1 minute.
echo.
echo Changes made:
echo  - Scheduler now runs every 10 seconds
echo  - Minimum interval validation changed from 30s to 10s
echo  - Default monitor interval set to 10 seconds
echo.

set /p continue="Continue with update? (Y/N): "
if /i "%continue%" neq "Y" goto exit

echo.
echo [%time%] Stopping current monitoring services...
call stop-monitoring.bat >nul 2>&1

echo [%time%] Running database migration to update default intervals...
php artisan migrate --force

echo [%time%] Updating existing monitors to use 10-second intervals (if desired)...
set /p update_existing="Update all existing monitors to 10-second intervals? (Y/N): "
if /i "%update_existing%"=="Y" (
    php artisan tinker --execute="App\Models\Monitor::where('interval_seconds', '>', 10)->update(['interval_seconds' => 10]); echo 'Updated existing monitors to 10-second intervals';" 2>nul
    echo Existing monitors updated to 10-second intervals.
) else (
    echo Existing monitors kept with their current intervals.
)

echo [%time%] Clearing application cache...
php artisan cache:clear >nul 2>&1
php artisan config:clear >nul 2>&1

echo [%time%] Restarting monitoring services with new configuration...
call start-monitoring.bat

echo.
echo ============================================
echo    UPDATE COMPLETED SUCCESSFULLY!
echo ============================================
echo.
echo New monitoring configuration:
echo  - Scheduler runs every 10 seconds
echo  - Minimum monitor interval: 10 seconds
echo  - Default new monitor interval: 10 seconds
echo.
echo Monitor checks will now run much more frequently for faster detection.
echo.
echo To verify the new settings:
echo  1. Create a new monitor and see 10-second default interval
echo  2. Check logs: php artisan logs:monitoring --limit=10
echo  3. Monitor status: call status-monitoring.bat
echo.
pause