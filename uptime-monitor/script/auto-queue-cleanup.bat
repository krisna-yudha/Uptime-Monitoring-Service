@echo off
title Queue Auto-Cleanup Service
cd /d "%~dp0"

echo ========================================
echo  Queue Auto-Cleanup Service
echo ========================================
echo.
echo Akan membersihkan jobs lama setiap 30 menit
echo Jobs lebih dari 1 jam akan dihapus otomatis
echo.
echo Tekan Ctrl+C untuk stop service
echo ========================================
echo.

:loop
echo [%date% %time%] Running cleanup...
php artisan queue:monitor-health --cleanup --max-age=3600

if %errorlevel% neq 0 (
    echo [ERROR] Cleanup gagal! Error code: %errorlevel%
) else (
    echo [OK] Cleanup selesai
)

echo.
echo Menunggu 30 menit untuk cleanup berikutnya...
echo (Next run: %time%)
echo.

REM Wait 30 minutes (1800 seconds)
timeout /t 1800 /nobreak

goto loop
