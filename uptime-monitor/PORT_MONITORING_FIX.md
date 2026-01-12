# Fix Port Monitoring Issue - HTTP Connect Timeout

## 🔴 Problem

Monitor yang mengecek URL dengan port berbeda (contoh: `192.168.88.241:8080`) hanya melakukan check **sekali** dan tidak lanjut. Status monitor menjadi stuck atau tidak ter-update.

## 🔍 Root Cause

HTTP client di `ProcessMonitorCheck.php` hanya menggunakan `timeout()` tanpa `connectTimeout()`. 

**Perbedaan:**
- **`timeout()`**: Waktu maksimal untuk seluruh request (connection + data transfer + response)
- **`connectTimeout()`**: Waktu maksimal **hanya untuk membuat connection** ke server

Ketika monitoring URL dengan port non-standard (misal port 8080), jika server lambat atau tidak responsif pada fase connection, request akan **hang** dan tidak pernah selesai karena tidak ada timeout spesifik untuk connection phase.

## ✅ Solution

Menambahkan `connectTimeout()` pada semua HTTP requests untuk memastikan connection phase memiliki timeout maksimal.

### File yang Diupdate:

#### 1. `app/Jobs/ProcessMonitorCheck.php`

**Method `checkHttp()`:**
```php
// BEFORE - Hanya timeout untuk keseluruhan request
$httpClient = Http::timeout($this->monitor->timeout_ms / 1000)
    ->retry($this->monitor->retries, 1000)
    ->withOptions(['allow_redirects' => true]);

// AFTER - Ada connectTimeout terpisah
$timeoutSeconds = $this->monitor->timeout_ms / 1000;
$connectTimeoutSeconds = min($timeoutSeconds, 10); // Max 10 seconds for connection

$httpClient = Http::timeout($timeoutSeconds)
    ->connectTimeout($connectTimeoutSeconds)
    ->retry($this->monitor->retries, 1000)
    ->withOptions(['allow_redirects' => true]);
```

**Method `checkKeyword()`:**
```php
// BEFORE
$response = Http::timeout($this->monitor->timeout_ms / 1000)
    ->get($this->monitor->target);

// AFTER
$timeoutSeconds = $this->monitor->timeout_ms / 1000;
$connectTimeoutSeconds = min($timeoutSeconds, 10);

$response = Http::timeout($timeoutSeconds)
    ->connectTimeout($connectTimeoutSeconds)
    ->get($this->monitor->target);
```

**Method `validateHttpService()`:**
- Sudah menggunakan `connectTimeout(10)` ✅

#### 2. `app/Jobs/SendNotification.php`

**Discord & Slack Webhooks:**
```php
// BEFORE
->timeout(30)->post($webhookUrl, $payload)

// AFTER
->timeout(30)->connectTimeout(10)->post($webhookUrl, $payload)
```

#### 3. `app/Http/Controllers/Api/NotificationChannelController.php`

**Test Webhooks (Discord & Slack):**
```php
// BEFORE
->timeout(30)->post($webhookUrl, $payload)

// AFTER
->timeout(30)->connectTimeout(10)->post($webhookUrl, $payload)
```

## 🎯 Benefits

1. **Prevent Hanging Requests**: Connection yang lambat atau stuck tidak akan menggantung worker
2. **Better Resource Management**: Worker tidak terjebak waiting untuk connection yang tidak responsif
3. **Faster Failure Detection**: Monitor down terdeteksi lebih cepat (max 10 detik connection time)
4. **Consistent Monitoring**: Monitor dengan port berbeda akan berjalan normal tanpa stuck

## 📋 Testing

### Cara Test Fix ini:

1. **Create Monitor dengan Port Berbeda:**
```
Name: Test Port 8080
Type: HTTP
Target: http://192.168.88.241:8080
Interval: 10 seconds
```

2. **Monitor Status Checks:**
```bash
# Jalankan worker
php artisan queue:work --queue=monitor-checks --verbose

# Atau gunakan batch file
.\start_all_workers.bat
```

3. **Cek di Database:**
```sql
-- Lihat monitor checks terbaru
SELECT id, monitor_id, checked_at, status, latency_ms, error_message
FROM monitor_checks
WHERE monitor_id = [YOUR_MONITOR_ID]
ORDER BY checked_at DESC
LIMIT 10;

-- Pastikan ada check baru setiap interval
```

4. **Cek di Frontend:**
- Monitor harus menampilkan status (up/down) secara konsisten
- Tidak stuck di status "unknown"
- Last check time ter-update setiap interval

## ⚙️ Technical Details

**Connect Timeout Logic:**
```php
$timeoutSeconds = $this->monitor->timeout_ms / 1000;
$connectTimeoutSeconds = min($timeoutSeconds, 10);
```

- Menggunakan **minimum** antara monitor timeout atau 10 detik
- Contoh:
  - Monitor timeout 5000ms (5s) → connectTimeout = 5s
  - Monitor timeout 30000ms (30s) → connectTimeout = 10s (max)
  
**Reasoning:**
- Connection seharusnya cepat (<10s)
- Jika connection > 10s, kemungkinan besar ada masalah network/firewall
- Data transfer bisa lebih lama, jadi `timeout` tetap mengikuti setting monitor

## 🔧 Deployment Steps

### Lokal/Development:
```bash
# Tidak perlu migration, hanya update code
git pull origin main

# Restart workers
.\stop-monitoring.bat
.\start_all_workers.bat
```

### Production:
```bash
# Update code
cd /path/to/uptime-monitor
git pull origin main

# Restart queue workers
# Jika menggunakan Supervisor:
sudo supervisorctl restart uptime-monitor-worker:*

# Jika manual:
pkill -f "queue:work"
php artisan queue:work --queue=monitor-checks --daemon &
```

## 📅 Update Log

- **2026-01-12**: Initial fix for port monitoring timeout issue
- **Author**: System Administrator
- **Ticket/Issue**: Port monitoring stuck/tidak lanjut setelah 1 check

## 🆘 Troubleshooting

### Monitor masih stuck?

1. **Cek worker running:**
```bash
# Windows
tasklist | findstr php

# Linux
ps aux | grep "queue:work"
```

2. **Cek failed jobs:**
```bash
php artisan queue:failed
```

3. **Clear dan restart:**
```bash
php artisan queue:flush
php artisan cache:clear
.\start_all_workers.bat
```

4. **Enable debug logging:**
```bash
# Edit .env
LOG_LEVEL=debug

# Tail logs
tail -f storage/logs/laravel.log
```

### Connection masih timeout?

- Periksa firewall/network: `telnet 192.168.88.241 8080`
- Periksa target server responsif: `curl -v http://192.168.88.241:8080`
- Increase monitor timeout jika server memang lambat

## 📝 Notes

- Fix ini **backward compatible** - tidak perlu migration database
- Berlaku untuk semua HTTP/HTTPS monitors
- Webhook notifications juga sudah di-fix untuk konsistensi
