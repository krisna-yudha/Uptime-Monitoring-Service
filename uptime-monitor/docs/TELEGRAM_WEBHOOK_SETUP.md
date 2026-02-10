# Telegram Webhook Setup Guide

## Masalah yang Ditemukan

❌ **Webhook URL tidak di-set** - Bot tidak tahu harus kirim update ke mana  
⚠️ **7 pending updates** - Ada 7 command yang belum diproses

## Solusi untuk Local Development

### Metode 1: Menggunakan ngrok (Recommended)

#### Step 1: Install ngrok
Download dari: https://ngrok.com/download

Atau install via chocolatey:
```bash
choco install ngrok
```

#### Step 2: Jalankan ngrok
```bash
ngrok http 8000
```

Atau jika server Laravel berjalan di port lain:
```bash
ngrok http 80   # untuk xampp
ngrok http 3000 # atau port lain
```

#### Step 3: Copy HTTPS URL
Setelah ngrok jalan, akan muncul URL seperti:
```
https://abc123xyz.ngrok.io
```

#### Step 4: Set Webhook
Jalankan command ini (ganti YOUR_BOT_TOKEN dan NGROK_URL):
```bash
php setup_telegram_webhook.php YOUR_BOT_TOKEN
```

Atau manual via curl:
```bash
curl -X POST "https://api.telegram.org/bot8565885504:AAEVmg7bEPnF-sBrZoM9ratvv-b8fpaK-9o/setWebhook" \
  -H "Content-Type: application/json" \
  -d '{"url":"https://abc123xyz.ngrok.io/api/telegram/webhook","allowed_updates":["message","callback_query"]}'
```

### Metode 2: Menggunakan serveo.net (Free, No Install)

```bash
ssh -R 80:localhost:8000 serveo.net
```

Akan mendapat URL seperti: `https://randomname.serveo.net`

Lalu set webhook ke: `https://randomname.serveo.net/api/telegram/webhook`

### Metode 3: Deploy ke Production

Jika sudah deploy ke server dengan domain dan SSL:

```bash
curl -X POST "https://api.telegram.org/botYOUR_BOT_TOKEN/setWebhook" \
  -H "Content-Type: application/json" \
  -d '{"url":"https://yourdomain.com/api/telegram/webhook"}'
```

## Verify Webhook

Cek status webhook:
```bash
curl "https://api.telegram.org/bot8565885504:AAEVmg7bEPnF-sBrZoM9ratvv-b8fpaK-9o/getWebhookInfo"
```

Hasil yang baik:
```json
{
  "ok": true,
  "result": {
    "url": "https://your-url.com/api/telegram/webhook",
    "has_custom_certificate": false,
    "pending_update_count": 0
  }
}
```

## Testing

1. **Pastikan Laravel server running:**
   ```bash
   php artisan serve
   ```

2. **Jalankan ngrok di terminal terpisah:**
   ```bash
   ngrok http 8000
   ```

3. **Set webhook dengan URL dari ngrok**

4. **Test di Telegram:**
   - Kirim `/start` - harus dapat welcome message
   - Kirim `/status` - harus dapat status monitors
   - Kirim `/ping` - harus dapat pong response

## Troubleshooting

### Bot tidak respon setelah setup webhook
1. Cek log: `storage/logs/laravel.log`
2. Pastikan tidak ada error di webhook handler
3. Test dengan diagnostic script:
   ```bash
   php test_telegram_webhook.php
   ```

### Pending updates tidak berkurang
Hapus pending updates:
```bash
curl "https://api.telegram.org/bot8565885504:AAEVmg7bEPnF-sBrZoM9ratvv-b8fpaK-9o/getUpdates?offset=-1"
```

### Error: SSL certificate problem
Untuk development, sudah di-handle dengan `verify: false` di controller.

### Webhook URL must be HTTPS
Telegram hanya terima HTTPS. Untuk development:
- ✅ ngrok (auto HTTPS)
- ✅ serveo (auto HTTPS)  
- ❌ http://localhost (tidak bisa)

## Quick Commands

**Hapus webhook (untuk testing polling mode):**
```bash
curl "https://api.telegram.org/botYOUR_TOKEN/deleteWebhook"
```

**Check webhook info:**
```bash
curl "https://api.telegram.org/botYOUR_TOKEN/getWebhookInfo"
```

**Get pending updates:**
```bash
curl "https://api.telegram.org/botYOUR_TOKEN/getUpdates"
```

## Notes

- Webhook lebih efisien dari polling untuk production
- Ngrok free tier timeout setelah 2 jam, perlu restart
- Untuk permanent solution, deploy ke server dengan SSL
- Bot token jangan di-commit ke git!
