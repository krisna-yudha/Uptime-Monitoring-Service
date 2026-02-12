@echo off
echo ================================================
echo    TELEGRAM WEBHOOK SETUP - LOCAL DEVELOPMENT
echo ================================================
echo.

REM Ganti dengan bot token Anda
set BOT_TOKEN=8565885504:AAEVmg7bEPnF-sBrZoM9ratvv-b8fpaK-9o

echo Step 1: Install ngrok (jika belum)
echo -----------------------------------------------
echo Download ngrok dari: https://ngrok.com/download
echo Atau gunakan: choco install ngrok
echo.
pause

echo.
echo Step 2: Jalankan ngrok
echo -----------------------------------------------
echo Buka terminal baru dan jalankan:
echo    ngrok http 8000
echo.
echo atau jika menggunakan port lain:
echo    ngrok http YOURPORT
echo.
echo Salin HTTPS URL yang muncul (contoh: https://abc123.ngrok.io)
echo.
pause

echo.
echo Step 3: Setup webhook
echo -----------------------------------------------
set /p NGROK_URL="Paste ngrok HTTPS URL (tanpa trailing slash): "

echo.
echo Setting webhook to: %NGROK_URL%/api/telegram/webhook
echo.

curl -k -X POST "https://api.telegram.org/bot%BOT_TOKEN%/setWebhook" ^
  -H "Content-Type: application/json" ^
  -d "{\"url\":\"%NGROK_URL%/api/telegram/webhook\",\"allowed_updates\":[\"message\",\"callback_query\"]}"

echo.
echo.
echo ================================================
echo                    DONE!
echo ================================================
echo.
echo Test bot Anda dengan mengirim /start di Telegram
echo.
pause
