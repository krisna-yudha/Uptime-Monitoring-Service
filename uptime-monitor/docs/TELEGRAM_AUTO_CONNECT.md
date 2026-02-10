# 🔌 Telegram Bot Auto-Connect Feature

## Overview
Fitur ini memungkinkan Telegram bot **otomatis terhubung dan siap digunakan** saat menambahkan atau update notification channel.

## Fitur yang Ditambahkan

### 1. **Auto Webhook Setup (Backend)**
- **File:** `app/Observers/NotificationChannelObserver.php`
- **Fungsi:** Otomatis setup webhook saat channel Telegram dibuat/diupdate
- **Proses:**
  - Detect channel type = telegram
  - Check bot token valid
  - Set webhook ke `APP_URL/api/telegram/webhook`
  - Send confirmation message ke chat

### 2. **Manual Connect Button (Frontend)**
- **File:** `uptime-frontend/src/views/NotificationChannelsView.vue`
- **Button:** 🔌 Connect
- **Lokasi:** Di setiap Telegram channel card
- **Fungsi:**
  - Verify bot token
  - Setup webhook
  - Test connection
  - Show status (webhook URL, pending updates, commands ready)
  - Send welcome message dengan daftar commands

### 3. **API Endpoint**
- **Route:** `POST /api/notification-channels/{id}/connect`
- **Controller:** `NotificationChannelController@connectTelegram`
- **Response:**
  ```json
  {
    "success": true,
    "message": "Bot connected successfully!",
    "data": {
      "bot_username": "YourBot",
      "webhook_url": "https://domain.com/api/telegram/webhook",
      "webhook_set": true,
      "pending_updates": 0,
      "commands_ready": true,
      "last_error": null
    }
  }
  ```

## Cara Menggunakan

### Setup Pertama Kali

1. **Buka Notification Channels**
2. **Klik "Add Channel"**
3. **Pilih Type: Telegram**
4. **Masukkan:**
   - Channel Name
   - Bot Token (dari @BotFather)
   - Chat ID
5. **Enable channel**
6. **Save**
7. ✅ **Webhook otomatis ter-setup!**

### Manual Connect (Optional)

Jika webhook belum ter-setup atau ingin re-connect:

1. **Di channel card Telegram**
2. **Klik button "🔌 Connect"**
3. **Wait for confirmation**
4. **Bot siap digunakan!**

### Test Bot Commands

Buka Telegram dan kirim:
```
/start     - Welcome & Chat ID
/status    - Status monitors
/incidents - Recent incidents
/monitors  - List monitors
/uptime    - Uptime stats
/ping      - Health check
/help      - All commands
```

## Flow Diagram

```
┌─────────────────────────────────────┐
│  User Creates Telegram Channel     │
└──────────────┬──────────────────────┘
               │
               ▼
┌─────────────────────────────────────┐
│  NotificationChannelObserver        │
│  - Triggered automatically          │
│  - Check if type = telegram         │
│  - Check if enabled                 │
│  - Check APP_URL valid (HTTPS)      │
└──────────────┬──────────────────────┘
               │
               ▼
┌─────────────────────────────────────┐
│  Setup Webhook                      │
│  - Get bot token from config        │
│  - Verify bot token (getMe)         │
│  - Set webhook URL                  │
│  - Drop pending updates             │
└──────────────┬──────────────────────┘
               │
               ▼
┌─────────────────────────────────────┐
│  Send Confirmation                  │
│  - Send test message to chat        │
│  - List available commands          │
│  - Log success/failure              │
└─────────────────────────────────────┘
```

## Persyaratan

### Development (Localhost)
⚠️ **APP_URL harus HTTPS!**

Gunakan ngrok:
```bash
ngrok http 8000
```

Update .env:
```env
APP_URL=https://your-ngrok-url.ngrok.io
```

### Production
✅ **Domain dengan SSL valid**

Update .env:
```env
APP_URL=https://yourdomain.com
```

## Troubleshooting

### Bot tidak auto-connect
**Cek logs:**
```bash
tail -f storage/logs/laravel.log | grep -i "telegram webhook"
```

**Kemungkinan:**
- APP_URL = localhost (skip auto-setup)
- APP_URL tidak HTTPS
- Bot token invalid
- Network/firewall issue

**Solusi:**
- Set APP_URL ke ngrok/production URL
- Klik manual "Connect" button
- Check laravel.log untuk error detail

### Connect button tidak muncul
**Cek:**
- Frontend sudah rebuild?
- Route sudah registered? `php artisan route:list | grep connect`
- API service updated?

**Solusi:**
```bash
# Backend
php artisan config:clear
php artisan route:clear

# Frontend  
npm run dev
```

### Commands tidak bekerja
**Cek webhook info:**
```bash
curl "https://api.telegram.org/botYOUR_TOKEN/getWebhookInfo"
```

**Expected:**
```json
{
  "ok": true,
  "result": {
    "url": "https://yourdomain.com/api/telegram/webhook",
    "pending_update_count": 0
  }
}
```

**Solusi:**
- Klik "Connect" button
- Verify APP_URL correct
- Check Laravel server running
- Test dengan `/start` di Telegram

## Files Modified

### Backend
- `app/Observers/NotificationChannelObserver.php` (NEW)
- `app/Providers/AppServiceProvider.php` (Modified)
- `app/Http/Controllers/Api/NotificationChannelController.php` (Added connectTelegram method)
- `routes/api.php` (Added connect route)

### Frontend
- `uptime-frontend/src/views/NotificationChannelsView.vue` (Added Connect button & function)
- `uptime-frontend/src/services/api.js` (Added connect endpoint)

## Benefits

✅ **User-Friendly:** Sekali setup, langsung jalan  
✅ **No Manual Work:** Tidak perlu running script terpisah  
✅ **Real-time Feedback:** Tahu langsung status connection  
✅ **Error Handling:** Clear error messages  
✅ **Mobile Support:** Connect button available di mobile  
✅ **Production Ready:** Works dengan production domain & SSL

## Testing

### Manual Test
1. Create Telegram channel
2. Check logs: webhook auto-setup
3. Click Connect button
4. Verify webhook info
5. Test `/start` command

### Expected Behavior
- ✅ Auto-setup pada create (jika APP_URL HTTPS)
- ✅ Auto-setup pada update (jika enabled & HTTPS)
- ✅ Manual connect works anytime
- ✅ Confirmation message sent
- ✅ Commands immediately available

---

**Created:** January 26, 2026  
**Version:** 1.0  
**Status:** ✅ Ready for Production
