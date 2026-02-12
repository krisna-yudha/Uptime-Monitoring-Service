@echo off
echo ============================================
echo   UPTIME MONITOR - FIRST TIME SETUP
echo ============================================
echo.

echo [%time%] Running first-time setup for Uptime Monitor...
echo.

REM Check if we're in the right directory
if not exist "artisan" (
    echo ERROR: artisan file not found!
    echo Make sure you're running this from the Laravel project directory.
    pause
    exit /b 1
)

REM Install dependencies
echo [%time%] 1/6 Installing Composer dependencies...
composer install --no-dev --optimize-autoloader
if %errorlevel% neq 0 (
    echo ERROR: Composer install failed!
    pause
    exit /b 1
)
echo.

REM Generate JWT secret
echo [%time%] 2/6 Generating JWT secret key...
php artisan jwt:secret --force
echo.

REM Generate APP_KEY if not exists
echo [%time%] 3/6 Checking application key...
php artisan key:generate --force
echo.

REM Run database migrations
echo [%time%] 4/6 Running database migrations...
php artisan migrate --force
if %errorlevel% neq 0 (
    echo ERROR: Database migration failed!
    echo Please check your database configuration in .env file.
    pause
    exit /b 1
)
echo.

REM Seed admin user
echo [%time%] 5/6 Creating admin user...
php artisan db:seed --class=AdminUserSeeder --force
echo.

REM Clear and optimize
echo [%time%] 6/6 Optimizing application...
php artisan config:cache
php artisan route:cache
echo.

echo ============================================
echo      SETUP COMPLETED SUCCESSFULLY!
echo ============================================
echo.
echo Default admin credentials:
echo  Email: admin@uptimemonitor.local  
echo  Password: password
echo.
echo Next steps:
echo  1. Run "start-monitoring.bat" to start all services
echo  2. Open http://localhost:8000 in your browser
echo  3. Use Postman with the API_TESTING_GUIDE.md
echo.
echo Setup files created:
echo  - start-monitoring.bat (Start all services)
echo  - stop-monitoring.bat (Stop all services) 
echo  - status-monitoring.bat (Check service status)
echo  - monitor-tools.bat (Development tools menu)
echo.
pause