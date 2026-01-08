# 🔔 Panduan Notifikasi Otomatis Bot

## Status Setup ✅

Sistem notifikasi otomatis sudah **SIAP DIGUNAKAN**!

### Yang Sudah Dikonfigurasi:

1. ✅ **Notification Channels**: Discord bot "gentong" sudah terkonfigurasi
2. ✅ **Monitors**: Semua 5 monitor sudah terhubung ke channel Discord
3. ✅ **Notification Worker**: Worker sudah berjalan dan siap memproses notifikasi
4. ✅ **Queue System**: Database queue sudah dikonfigurasi dengan benar
5. ✅ **Jobs**: SendNotification job sudah diperbaiki dan berfungsi

### Monitor yang Terhubung:

Semua monitor ini akan mengirim notifikasi ke Discord channel "gentong":
- Monitor #1: rpjmd
- Monitor #2: goessti  
- Monitor #4: sie disperkim
- Monitor #5: cek (sedang DOWN - 42 failures)
- Monitor #6: CMS Semarang Kota

---

## 🚀 Cara Kerja Sistem

### Flow Notifikasi Otomatis:

```
Service DOWN 
  ↓
Monitor Check Worker mendeteksi kegagalan
  ↓
Setelah notify_after_retries tercapai (sekarang = 1)
  ↓
Incident dibuat di database
  ↓
SendNotification job ditambahkan ke queue 'notifications'
  ↓
Notification Worker mengambil job dari queue
  ↓
Worker mengirim pesan ke Discord webhook
  ↓
Bot Discord mengirim notifikasi ke channel
```

---

## 📋 Worker yang Harus Berjalan

Untuk sistem bekerja otomatis, kedua worker ini HARUS berjalan:

### 1. Monitor Checks Worker
**Fungsi**: Memantau service setiap beberapa detik
```batch
worker_manager.bat
```
Atau manual:
```batch
php artisan worker:monitor-checks --verbose
```

### 2. Notification Worker  
**Fungsi**: Mengirim notifikasi ke bot Discord/Telegram/Slack
```batch
run_notification_worker.bat
```
Atau manual:
```batch
php artisan worker:notifications --verbose
```

### Cara Start Semua Worker Sekaligus:
```batch
start_all_workers.bat
```

---

## 🧪 Testing Notifikasi

### Opsi 1: Test via UI (Recommended)
1. Buka http://localhost:5173/notifications
2. Klik tombol "Test" pada channel Discord "gentong"
3. Cek Discord channel untuk melihat test message

### Opsi 2: Simulasi Incident Real
1. Jalankan script test:
   ```batch
   trigger_incident.bat
   ```
2. Script akan:
   - Menampilkan daftar monitor
   - Meminta Anda pilih monitor
   - Sementara ubah target ke URL yang pasti down
   - Trigger monitor check
   - Incident akan terbuat
   - Notifikasi otomatis masuk queue
   - Worker akan kirim ke Discord

### Opsi 3: Manual - Matikan Service Real
1. Pastikan kedua worker berjalan
2. Pilih salah satu service yang di-monitor (misalnya http://localhost:8080)
3. Stop service tersebut (matikan server)
4. Tunggu beberapa detik
5. Monitor check akan mendeteksi DOWN
6. Notifikasi akan otomatis terkirim ke Discord!

---

## 🔍 Monitoring & Troubleshooting

### Cek Status Queue
```powershell
# Lihat job yang pending di queue notifications
php artisan queue:work database --queue=notifications --stop-when-empty --verbose
```

### Cek Log Notifikasi
```powershell
# Lihat log Laravel
Get-Content storage\logs\laravel.log -Tail 50
```

### Cek Failed Jobs
```powershell
# Lihat job yang gagal
php artisan queue:failed
```

### Reprocess Failed Jobs
```powershell
# Retry semua failed jobs
php artisan queue:retry all
```

### Cek Incident Terbaru
```powershell
php -r "require 'vendor/autoload.php'; \$app = require 'bootstrap/app.php'; \$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap(); \$incidents = App\Models\Incident::with('monitor')->latest()->take(5)->get(); foreach(\$incidents as \$i) { echo '['.\$i->id.'] '.\$i->monitor->name.' - Status: '.\$i->status.' - Started: '.\$i->started_at.PHP_EOL; }"
```

---

## ⚙️ Konfigurasi Penting

### notify_after_retries
**Saat ini**: Semua monitor diset ke **1**  
**Artinya**: Notifikasi langsung dikirim pada failure pertama

Jika ingin notifikasi setelah beberapa kali failure:
```sql
UPDATE monitors SET notify_after_retries = 3 WHERE id = 1;
```

### notification_channels
**Format**: JSON array of channel IDs  
**Contoh**: `[1]` atau `[1,2,3]`

Untuk menambah/ubah channel pada monitor:
```sql
-- Link monitor ke channel 1 dan 2
UPDATE monitors SET notification_channels = '[1,2]'::json WHERE id = 1;
```

---

## 📊 Status Monitor Saat Ini

| Monitor ID | Nama | Status | Failures | Channels | notify_after_retries |
|------------|------|--------|----------|----------|---------------------|
| 1 | rpjmd | UP | 0 | [1] | 1 |
| 2 | goessti | UP | 0 | [1] | 1 |
| 4 | sie disperkim | UP | 0 | [1] | 1 |
| 5 | cek | **DOWN** | 42 | [1] | 1 |
| 6 | CMS Semarang Kota | UP | 0 | [1] | 1 |

**Note**: Monitor #5 "cek" sedang DOWN dengan 42 consecutive failures.

---

## 🔧 Maintenance Scripts

### Setup/Re-setup Notification Channels
Jika perlu link ulang monitors ke channels:
```powershell
php setup_notifications.php
```

### Manual SQL Queries
```sql
-- Lihat semua notification channels
SELECT id, name, type FROM notification_channels;

-- Lihat monitor dengan channels-nya
SELECT 
    m.id,
    m.name,
    m.notification_channels,
    m.last_status,
    m.consecutive_failures
FROM monitors m;

-- Link semua monitor ke semua channels
UPDATE monitors 
SET notification_channels = (
    SELECT json_agg(id)::text::json
    FROM notification_channels
);

-- Set notify setelah 2 kali failure
UPDATE monitors SET notify_after_retries = 2;
```

---

## ✅ Checklist Sistem Berjalan

Pastikan semua ini sudah jalan:

- [ ] Laravel server running (php artisan serve)
- [ ] Frontend server running (npm run dev)
- [ ] Monitor Checks Worker running
- [ ] Notification Worker running
- [ ] PostgreSQL database running
- [ ] Discord webhook sudah dikonfigurasi
- [ ] Monitors sudah terhubung ke notification channels

---

## 🎯 Next Steps

1. **Test notifikasi** dengan salah satu cara di atas
2. **Monitor Discord channel** untuk melihat pesan notifikasi
3. **Adjust notify_after_retries** sesuai kebutuhan
4. **Tambah notification channels** lain jika perlu (Telegram, Slack)
5. **Setup Windows Service** agar worker jalan otomatis saat PC restart

---

## 📞 Troubleshooting Common Issues

### Notifikasi tidak terkirim?
1. Cek kedua worker berjalan
2. Cek log: `Get-Content storage\logs\laravel.log -Tail 50`
3. Cek queue: `php artisan queue:work database --queue=notifications --once`
4. Verify webhook URL valid
5. Cek monitor punya notification_channels

### Worker berhenti sendiri?
- Restart dengan batch file yang disediakan
- Cek error di terminal window worker

### Discord tidak terima pesan?
1. Test via UI dulu (tombol Test di Notifications page)
2. Verify webhook URL benar
3. Cek Discord channel settings
4. Cek internet connection

---

**Sistem Siap Digunakan! 🎉**

Untuk test, coba matikan salah satu service yang di-monitor, atau gunakan script `trigger_incident.bat`.
