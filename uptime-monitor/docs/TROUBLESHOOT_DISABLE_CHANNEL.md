# 🔍 Cara Memastikan Bot Tidak Menerima Notifikasi Saat Disabled

## Masalah Yang Dilaporkan
"ketika saya disable bot chanel nya, notifikasi masih masuk ke dalam bot"

## Solusi & Verifikasi

### 1. Pastikan Channel Benar-Benar Disabled

**Via UI:**
```
1. Buka http://localhost:5173/notifications
2. Lihat status indicator pada channel:
   - 🟢 Green Dot = ENABLED (akan terima notif)
   - 🔴 Red Dot = DISABLED (tidak terima notif)
3. Klik tombol "Disable" jika masih enabled
4. Refresh halaman untuk konfirmasi
```

**Via Database:**
```powershell
cd uptime-monitor
php check_channels.php
```

Output seharusnya:
```
[1] nama_channel (discord) - ❌ DISABLED
```

### 2. Test dengan Log yang Jelas

**Script Test:**
```powershell
# 1. Disable channel
php toggle_channel.php

# 2. Dispatch test notification
php test_disabled_notification.php

# 3. Process dengan logging
php artisan queue:work database --queue=notifications --once --verbose

# 4. Cek log
Get-Content storage\logs\laravel.log -Tail 50 | Select-String -Pattern "Skipping disabled"
```

**Expected Output di Log:**
```
[INFO] Skipping disabled notification channel
  monitor_id: 1
  monitor_name: My Monitor
  channel_id: 1
  channel_name: gentong
  channel_type: discord
  is_enabled: false
```

### 3. Verify Notification Tidak Terkirim

**Check Discord:**
- TIDAK ADA pesan baru di Discord channel
- Bot tidak mengirim apapun

**Check Laravel Log:**
```powershell
Get-Content storage\logs\laravel.log -Tail 30
```

Harusnya ada log:
```
[INFO] Notification channels loaded
  enabled_count: 0
  disabled_count: 1
```

**Check Database Jobs:**
```powershell
php check_queue.php
```

Job processed tapi NO notification sent.

---

## Troubleshooting

### Kemungkinan Penyebab Masih Terima Notifikasi:

#### 1. **Worker Lama Masih Running**
Worker yang sudah jalan sebelum fitur enable/disable ditambahkan masih menggunakan code lama.

**Solusi:**
```powershell
# Stop semua worker
stop-monitoring.bat

# Start ulang
start-monitoring.bat
```

#### 2. **Cache Code Lama**
Laravel cache masih menyimpan code sebelum update.

**Solusi:**
```powershell
php artisan config:clear
php artisan cache:clear  
php artisan queue:restart
```

#### 3. **Ada Channel Lain Yang Enabled**
Monitor terhubung ke multiple channels, salah satunya masih enabled.

**Check:**
```powershell
php check_channels.php
```

Jika ada beberapa channel, pastikan SEMUA yang ingin didisable sudah disabled.

#### 4. **Notification Sudah di Queue Sebelum Disable**
Job sudah masuk queue sebelum channel di-disable.

**Solusi:**
```powershell
# Clear pending jobs
php artisan queue:flush

# Atau process semua job lama dulu
php artisan queue:work database --queue=notifications --stop-when-empty
```

#### 5. **Monitor Punya notification_channels Kosong**
Jika monitor tidak punya notification_channels configured, mungkin ada fallback behavior.

**Check:**
```sql
SELECT id, name, notification_channels 
FROM monitors;
```

Should show: `notification_channels: [1]` or similar.

---

## Flow yang Benar

### Saat Channel DISABLED:

```
1. Incident terjadi
2. SendNotification job di-dispatch ke queue
3. Worker pick up job
4. getMonitorChannels() dipanggil
5. Query: WHERE is_enabled = true
6. Result: EMPTY ARRAY (karena channel disabled)
7. Loop foreach channels: SKIP (array kosong)
8. NO notification sent
9. Log: "enabled_count: 0"
```

### Saat Channel ENABLED:

```
1. Incident terjadi
2. SendNotification job di-dispatch  
3. Worker pick up job
4. getMonitorChannels() dipanggil
5. Query: WHERE is_enabled = true
6. Result: [Channel object]
7. Loop foreach: sendToChannel()
8. HTTP request ke Discord/Telegram
9. Bot receives notification
10. Log: "Notification sent successfully"
```

---

## Testing Steps (Lengkap)

### Step 1: Setup
```powershell
# Pastikan channel disabled
cd uptime-monitor
php toggle_channel.php  # Toggle sampai disabled
php check_channels.php  # Verify: ❌ DISABLED
```

### Step 2: Stop All Workers
```powershell
stop-monitoring.bat
```

### Step 3: Clear Cache & Old Jobs
```powershell
php artisan config:clear
php artisan cache:clear
php artisan queue:flush  # Clear failed jobs
```

### Step 4: Start Fresh Workers
```powershell
start-monitoring.bat
```

### Step 5: Trigger Test Incident
```powershell
# Matikan salah satu service yang di-monitor
# ATAU
php test_disabled_notification.php
```

### Step 6: Monitor Logs Real-Time
```powershell
# Terminal 1: Watch log
Get-Content storage\logs\laravel.log -Wait -Tail 10

# Terminal 2: Process job manually untuk debugging
php artisan queue:work database --queue=notifications --once --verbose
```

### Step 7: Verify Results
```
✅ Log shows: "Skipping disabled notification channel"
✅ Log shows: "enabled_count: 0"
✅ Discord channel: NO new messages
✅ Bot: SILENT
```

---

## Script Helper

Saya sudah membuat script untuk memudahkan testing:

| Script | Fungsi |
|--------|--------|
| `check_channels.php` | Cek status semua channels |
| `toggle_channel.php` | Toggle enabled/disabled |
| `test_disabled_notification.php` | Test dispatch dengan channel disabled |
| `check_queue.php` | Cek pending jobs |

---

## Jika Masih Bermasalah

Jika setelah semua step di atas notifikasi masih terkirim:

1. **Screenshot:**
   - UI showing channel disabled
   - Discord showing message received
   - Laravel log

2. **Check Database Directly:**
```sql
SELECT * FROM notification_channels WHERE id = 1;
```

`is_enabled` harusnya `false` atau `0`.

3. **Debug SendNotification Job:**
Tambahkan `dd()` untuk debug:
```php
// Di SendNotification.php, method getMonitorChannels()
$enabledChannels = $allChannels->filter(function($channel) {
    dd($channel->is_enabled, $channel->name); // Debug point
    return $channel->is_enabled === true;
});
```

4. **Contact:** Berikan output dari semua check di atas.

---

**TL;DR:**
1. Disable channel via UI
2. Restart workers: `stop-monitoring.bat` lalu `start-monitoring.bat`
3. Clear cache: `php artisan queue:restart`
4. Test incident baru
5. Bot seharusnya TIDAK menerima notifikasi

