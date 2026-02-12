@echo off
echo ============================================
echo    UPTIME MONITOR - DEVELOPMENT TOOLS
echo ============================================
echo.

:menu
echo Choose an action:
echo.
echo  [1] Start All Services
echo  [2] Stop All Services  
echo  [3] Check Service Status
echo  [4] View Recent Logs
echo  [5] Manual Monitor Check
echo  [6] View Dashboard Data
echo  [7] Create Test Monitor
echo  [8] Database Migration
echo  [9] Clear Cache
echo  [0] Exit
echo.
set /p choice="Enter your choice (0-9): "

if "%choice%"=="1" goto start_services
if "%choice%"=="2" goto stop_services
if "%choice%"=="3" goto check_status
if "%choice%"=="4" goto view_logs
if "%choice%"=="5" goto manual_check
if "%choice%"=="6" goto dashboard_data
if "%choice%"=="7" goto create_monitor
if "%choice%"=="8" goto migrate
if "%choice%"=="9" goto clear_cache
if "%choice%"=="0" goto exit
goto menu

:start_services
echo.
echo Starting all services...
call start-monitoring.bat
goto menu

:stop_services
echo.
echo Stopping all services...
call stop-monitoring.bat
goto menu

:check_status
echo.
call status-monitoring.bat
goto menu

:view_logs
echo.
echo Recent monitoring logs:
php artisan logs:monitoring --limit=5
echo.
pause
goto menu

:manual_check
echo.
echo Running manual monitor check...
php artisan monitor:check
echo.
pause
goto menu

:dashboard_data
echo.
echo Dashboard overview:
php artisan tinker --execute="print_r((new App\Http\Controllers\Api\DashboardController())->overview()->getData());" 2>nul
echo.
pause
goto menu

:create_monitor
echo.
echo Creating test monitor...
php artisan tinker --execute="App\Models\Monitor::create(['name' => 'Test Site', 'type' => 'https', 'target' => 'https://httpstat.us/200', 'created_by' => 1, 'interval_seconds' => 30]); echo 'Test monitor created successfully!';" 2>nul
echo.
pause
goto menu

:migrate
echo.
echo Running database migrations...
php artisan migrate
echo.
pause
goto menu

:clear_cache
echo.
echo Clearing application cache...
php artisan cache:clear
php artisan config:clear
php artisan route:clear
echo Cache cleared successfully!
echo.
pause
goto menu

:exit
echo.
echo Goodbye!
echo.
exit