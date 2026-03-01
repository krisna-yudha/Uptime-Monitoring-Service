# Auto Webhook Detection - Tidak Perlu Setting .env!

## 🎯 Overview

Sistem sekarang **otomatis mendeteksi API URL** saat user klik tombol **Connect** pada notification channel. User tidak perlu lagi membuka atau mengubah file `.env` untuk setting webhook URL.

## ✨ Cara Kerja

### 1. **User Klik Tombol "Connect"**

Saat user klik tombol Connect di halaman Notification Channels:

```
┌─────────────────────────────┐
│  TELEGRAM          Connect  │ ← User klik ini
│                       ●     │
│  Bot                        │
│  Bot Token: 85658855...     │
│  Chat ID: 6054825120        │
└─────────────────────────────┘
```

### 2. **Frontend Auto-Detect API URL**

Frontend (Vue.js) otomatis mendeteksi API URL yang sedang digunakan:

**File:** `uptime-frontend/src/views/NotificationChannelsView.vue`

```javascript
async function connectTelegram(channelId) {
  // Auto-detect API URL dari axios configuration
  const apiModule = await import('../services/api')
  const webhookBaseUrl = apiModule.getApiBaseUrl()
  
  // Kirim URL ke backend
  await api.notificationChannels.connect(channelId, {
    webhook_url: webhookBaseUrl
  })
}
```

### 3. **Backend Terima & Setup Webhook**

Backend menerima webhook URL dari frontend dan menggunakannya:

**File:** `uptime-monitor/app/Http/Controllers/Api/NotificationChannelController.php`

```php
public function connectTelegram(Request $request, NotificationChannel $notificationChannel)
{
    // Gunakan URL dari request (auto-detected dari frontend)
    if ($request->has('webhook_url')) {
        $webhookBaseUrl = $request->input('webhook_url');
    } else {
        // Fallback: Smart detection dari APP_URL
        $appUrl = config('app.url');
        $webhookBaseUrl = str_replace('://app.', '://api.', $appUrl);
    }
    
    $webhookUrl = rtrim($webhookBaseUrl, '/') . '/api/telegram/webhook';
    
    // Setup webhook ke Telegram
    Http::post("https://api.telegram.org/bot{$token}/setWebhook", [
        'url' => $webhookUrl
    ]);
}
```

## 📋 Contoh Skenario

### Skenario 1: Production (app.gentz.me)

User mengakses: `https://app.gentz.me`

**Proses:**
1. Frontend detect API URL: `https://api.gentz.me`
2. Kirim ke backend
3. Backend setup webhook: `https://api.gentz.me/api/telegram/webhook`

✅ **Hasil:** Webhook ter-setup dengan benar tanpa setting .env!

### Skenario 2: Development (localhost)

User mengakses: `http://localhost:3000`

**Proses:**
1. Frontend detect API URL: `http://localhost:8000`
2. Kirim ke backend  
3. Backend setup webhook: `http://localhost:8000/api/telegram/webhook`

⚠️ **Note:** Localhost tidak akan work untuk Telegram. Use ngrok!

### Skenario 3: Custom Domain

User mengakses: `https://monitoring.company.com`

**Proses:**
1. Frontend detect API URL: `https://monitoring.company.com`
2. Kirim ke backend
3. Backend setup webhook: `https://monitoring.company.com/api/telegram/webhook`

✅ **Hasil:** Work dengan domain custom apapun!

## 🔧 Smart Detection (Fallback)

Jika frontend tidak mengirim `webhook_url`, backend punya **smart detection**:

```php
// Jika APP_URL = https://app.example.com
// Otomatis jadi: https://api.example.com

if (strpos($appUrl, '://app.') !== false) {
    $webhookBaseUrl = str_replace('://app.', '://api.', $appUrl);
}
```

**Contoh:**
- `https://app.gentz.me/` → `https://api.gentz.me`
- `https://app.mycompany.com/` → `https://api.mycompany.com`
- `https://frontend.com/` → `https://frontend.com` (no change)

## 🎁 Keuntungan

### ✅ Untuk User:
- **Tidak perlu edit .env** di server
- **Tidak perlu restart** application
- **Plug & play** - langsung work!
- **Multi-environment** friendly

### ✅ Untuk Developer:
- **No hardcode** URL di code
- **Environment agnostic** - work di mana saja
- **Easy deployment** - tidak perlu config tambahan
- **Smart fallback** untuk backward compatibility

## 🧪 Testing

### Test di Development

1. Start backend: `php artisan serve --port=8000`
2. Start frontend: `npm run dev` (port 3000)
3. Access: `http://localhost:3000`
4. Create Telegram channel
5. Click **Connect**
6. ✅ Webhook URL auto-detected: `http://localhost:8000/api/telegram/webhook`

### Test di Production

1. Deploy frontend to: `https://app.yourdomain.com`
2. Deploy backend to: `https://api.yourdomain.com`
3. Access frontend
4. Create Telegram channel
5. Click **Connect**
6. ✅ Webhook URL auto-detected: `https://api.yourdomain.com/api/telegram/webhook`

## 🔍 Debug

### Check Logs

Backend mencatat webhook URL yang digunakan:

```bash
# Laravel logs
tail -f storage/logs/laravel.log | grep "webhook"
```

Output:
```
[2024-03-01 10:30:45] local.INFO: Using webhook URL from request {"url":"https://api.gentz.me"}
[2024-03-01 10:30:45] local.INFO: Setting up Telegram webhook {"channel_id":1,"webhook_url":"https://api.gentz.me/api/telegram/webhook"}
```

### Check Telegram Webhook Status

```bash
curl https://api.telegram.org/bot<YOUR_BOT_TOKEN>/getWebhookInfo
```

Response akan show webhook URL yang ter-setup:
```json
{
  "ok": true,
  "result": {
    "url": "https://api.gentz.me/api/telegram/webhook",
    "has_custom_certificate": false,
    "pending_update_count": 0
  }
}
```

## 🚀 Migration dari Sistem Lama

Jika sebelumnya sudah setup webhook manual:

### Before (Manual)
```env
# File: .env
APP_URL=https://app.gentz.me/
```

User harus manual edit .env dan set webhook URL.

### After (Auto)
```env
# File: .env
APP_URL=https://app.gentz.me/
# Tidak perlu setting lain!
```

User tinggal klik tombol **Connect** - semua otomatis! ✨

## 📝 Summary

| Feature | Sebelum | Sesudah |
|---------|---------|---------|
| Edit .env | ✅ Perlu | ❌ Tidak perlu |
| Restart app | ✅ Perlu | ❌ Tidak perlu |
| Manual config | ✅ Ya | ❌ Tidak |
| Multi-environment | ❌ Sulit | ✅ Mudah |
| User friendly | ⚠️ Biasa | ✅ Sangat mudah |

## 🎉 Kesimpulan

Dengan sistem auto-detection ini:
- **User hanya perlu klik "Connect"**
- **Sistem otomatis detect API URL**
- **Webhook ter-setup otomatis**
- **No manual configuration needed!**

Simple, elegant, and it just works! 🚀
