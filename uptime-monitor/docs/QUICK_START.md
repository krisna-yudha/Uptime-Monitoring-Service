# 🚀 QUICK START - Uptime Monitor

## Start Semua Service Sekaligus

```batch
start-monitoring.bat
```

**Script ini akan otomatis start:**
- ✅ Laravel Server (http://localhost:8000)
- ✅ Monitor Checks Worker (cek service tiap detik)
- ✅ Notification Worker (kirim ke Discord/Telegram/Slack)
- ✅ Frontend Server (http://localhost:5173)

## Stop Semua Service

```batch
stop-monitoring.bat
```

## Alternatif - Start Worker Manual

Jika hanya ingin start workers tanpa Laravel/Frontend server:

```batch
start_all_workers.bat
```

Atau pilih worker mana yang mau dijalankan:
```batch
worker_manager.bat
```

---

## 🎯 Cara Pakai Sistem

### 1. Start Services
```batch
start-monitoring.bat
```

### 2. Buka UI di Browser
```
http://localhost:5173
```

### 3. Buat Notification Channel
- Klik menu "Notifications"
- Klik "Add Channel"
- Pilih tipe: Discord/Telegram/Slack
- Isi webhook URL atau bot token
- Test dengan tombol "Test"

### 4. Buat Monitor
- Klik menu "Monitors"
- Klik "Create Monitor"
- Isi nama, pilih tipe (HTTP/PING/PORT)
- Isi target URL/IP/PORT
- **Centang notification channel** yang sudah dibuat
- Set interval (minimal 1 detik untuk realtime)
- Klik "Create"

### 5. Done! 🎉

Bot akan **otomatis** mengirim notifikasi saat ada incident.

---

## 📊 Cek Status

### Via UI
http://localhost:5173/dashboard

### Via Command
```powershell
# Cek monitors
php -r "require 'vendor/autoload.php'; $app = require 'bootstrap/app.php'; $app->make('Illuminate\Contracts\Console\Kernel')->bootstrap(); $monitors = App\Models\Monitor::all(); foreach($monitors as $m) { echo $m->name . ' - ' . $m->last_status . PHP_EOL; }"

# Cek incidents
php -r "require 'vendor/autoload.php'; $app = require 'bootstrap/app.php'; $app->make('Illuminate\Contracts\Console\Kernel')->bootstrap(); $incidents = App\Models\Incident::where('status', '!=', 'resolved')->count(); echo 'Open incidents: ' . $incidents . PHP_EOL;"

# Cek queue
php artisan queue:work database --queue=notifications --stop-when-empty
```

---

## 🔧 Troubleshooting

### Notifikasi tidak terkirim?
1. Cek worker berjalan: lihat window "Notification Worker"
2. Cek log: `Get-Content storage\logs\laravel.log -Tail 20`
3. Verify monitor punya notification_channels

### Worker crash?
Restart dengan `start-monitoring.bat`

### Port sudah dipakai?
Edit `start-monitoring.bat` dan ubah port:
```batch
php artisan serve --port=8001
```

---

## 📁 File Penting

| File | Fungsi |
|------|--------|
| `start-monitoring.bat` | Start SEMUA services |
| `stop-monitoring.bat` | Stop SEMUA services |
| `start_all_workers.bat` | Start workers saja |
| `worker_manager.bat` | Menu pilih worker |
| `trigger_incident.bat` | Test notifikasi |
| `setup_notifications.php` | Setup notification channels |

---

**Sistem sudah siap pakai! Tinggal jalankan `start-monitoring.bat` dan buka http://localhost:5173** 🎉
