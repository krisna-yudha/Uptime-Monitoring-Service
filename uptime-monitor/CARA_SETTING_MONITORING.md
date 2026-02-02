# 🚀 Cara Setting untuk Log Cepat & Auto-Cleanup Jobs

## 📌 Problem yang Diselesaikan

✅ **Monitor baru log-nya lama muncul** → Log akan muncul dalam **2-5 detik**  
✅ **Jobs menumpuk di database** → Auto-cleanup setiap **30 menit**  
✅ **Manual intervention terus** → Semuanya **otomatis**

---

## 🎯 Solusi yang Sudah Diimplementasikan

### 1. **MonitorObserver** (Sudah Aktif)
File: [`app/Observers/MonitorObserver.php`](app/Observers/MonitorObserver.php)

```php
// Otomatis dispatch job saat monitor baru dibuat
public function created(Monitor $monitor): void
{
    ProcessMonitorCheck::dispatch($monitor)
        ->onQueue('monitor-checks-priority'); // Priority queue!
}
```

✅ Monitor baru langsung dispatch ke **priority queue**  
✅ Worker priority akan process dengan **sleep 1 detik** (cepat!)  
✅ Monitor re-enabled juga langsung check

### 2. **Dual Queue System**
- **Priority Queue** (`monitor-checks-priority`): untuk monitor **baru** → sleep **1 detik**
- **Regular Queue** (`monitor-checks`): untuk monitor **existing** → sleep **3 detik**

### 3. **Auto-Cleanup Service**
- Cek queue health setiap **5 menit**
- Cleanup jobs lama (> 1 jam) setiap **30 menit**
- Alert jika jobs > **5000** (warning) atau > **10000** (critical)

---

## 🖥️ Setup Development (Windows/XAMPP)

### Pilihan 1: One-Click Start (PALING MUDAH) ⭐

```batch
start-optimized-workers.bat
```

Akan membuka **4 terminal windows**:
- 🟢 **Priority Queue** - untuk monitor baru (sleep 1s)
- 🔵 **Regular Queue** - untuk monitor existing (sleep 3s)
- 🟡 **Notifications** - untuk notifikasi
- 🟣 **Queue Monitor** - auto health check & cleanup

### Pilihan 2: Manual (untuk Debugging)

**Terminal 1** - Priority Worker:
```batch
php artisan queue:work database --queue=monitor-checks-priority --tries=3 --timeout=300 --sleep=1 --verbose
```

**Terminal 2** - Regular Worker:
```batch
php artisan queue:work database --queue=monitor-checks --tries=3 --timeout=300 --sleep=3 --verbose
```

**Terminal 3** - Auto Cleanup:
```batch
auto-queue-cleanup.bat
```

### Pilihan 3: Windows Task Scheduler (Production-like)

**Klik kanan** `setup-windows-tasks.bat` → **Run as Administrator**

Akan create 3 scheduled tasks:
- Laravel Scheduler (every 1 minute)
- Queue Health Check (every 5 minutes)
- Queue Cleanup (every 30 minutes)

---

## 🐧 Setup Production (Linux/Supervisor)

### Step 1: Copy Supervisor Config

```bash
sudo cp supervisor-optimized.conf /etc/supervisor/conf.d/uptime-monitor.conf
```

### Step 2: Edit Path (sesuaikan dengan instalasi Anda)

```bash
sudo nano /etc/supervisor/conf.d/uptime-monitor.conf

# Ubah path dari:
# /var/www/uptime-monitor
# Ke path instalasi Anda
```

### Step 3: Reload Supervisor

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start uptime-monitor:*
```

### Step 4: Setup Cron (Laravel Scheduler)

```bash
sudo cp production/cron/uptime-monitor /etc/cron.d/uptime-monitor
sudo chmod 644 /etc/cron.d/uptime-monitor
sudo service cron reload
```

### Step 5: Verify

```bash
# Check supervisor status
sudo supervisorctl status uptime-monitor:*

# Should show:
# uptime-priority-queue_00    RUNNING   pid 1234, uptime 0:00:05
# uptime-priority-queue_01    RUNNING   pid 1235, uptime 0:00:05
# uptime-regular-queue_00     RUNNING   pid 1236, uptime 0:00:05
# ... (dan lain-lain)

# View logs
sudo tail -f /var/www/uptime-monitor/storage/logs/worker-priority.log
```

---

## ✅ Testing & Verification

### Test 1: Monitor Baru Log Cepat

1. **Start workers** (pilih salah satu cara di atas)
2. **Buka UI**, tambah monitor baru
3. **Cek Priority Queue log** → harus langsung process
4. **Cek UI** → log muncul dalam **2-5 detik** ✅

### Test 2: Auto-Cleanup

```bash
# Check current queue status
php artisan queue:monitor-health

# Test manual cleanup
php artisan queue:monitor-health --cleanup --max-age=3600

# Test watch mode (continuous monitoring)
php artisan queue:monitor-health --watch --interval=60
```

### Test 3: Observer Auto-Dispatch

```bash
# Run test script
test-optimized-setup.bat       # Windows
./test-optimized-setup.sh      # Linux

# Manual test
php artisan tinker
>>> $m = \App\Models\Monitor::create([
    'name' => 'Test Monitor',
    'type' => 'http',
    'target' => 'https://google.com',
    'interval_seconds' => 60,
    'enabled' => 1
]);
>>> // Check jobs table immediately
>>> DB::table('jobs')->where('queue', 'monitor-checks-priority')->count();
>>> // Should return 1
>>> $m->delete();
```

---

## 📊 Monitoring Commands

### Queue Status
```bash
php artisan queue:monitor-health
```

Output:
```
=== Queue Health Monitor ===

+----------------------+-------+------------+
| Metric               | Count | Status     |
+----------------------+-------+------------+
| Total Jobs           | 234   | ✓ OK       |
| Priority Queue       | 5     | ✓ OK       |
| Regular Queue        | 229   | ✓ OK       |
| Failed Jobs          | 3     | ✓ OK       |
| Stale Jobs (>3600s)  | 0     | ✓ OK       |
+----------------------+-------+------------+

✓ Queue health is good
```

### Watch Mode (Continuous)
```bash
php artisan queue:monitor-health --watch --interval=300
```

### Manual Cleanup
```bash
php artisan queue:monitor-health --cleanup --max-age=3600
```

### Check Failed Jobs
```bash
php artisan queue:failed
```

### View Real-time Logs
```bash
# Development
tail -f storage/logs/laravel.log

# Production
sudo tail -f /var/www/uptime-monitor/storage/logs/worker-priority.log
sudo tail -f /var/www/uptime-monitor/storage/logs/queue-monitor.log
```

---

## 🔧 Tuning Parameters

Sesuaikan dengan traffic monitoring Anda:

### Low Traffic (< 50 monitors)
```ini
Priority: numprocs=2, sleep=1
Regular:  numprocs=2, sleep=5
Cleanup:  every 60 minutes
```

### Medium Traffic (50-200 monitors) ⭐ DEFAULT
```ini
Priority: numprocs=2, sleep=1
Regular:  numprocs=4, sleep=3
Cleanup:  every 30 minutes
```

### High Traffic (> 200 monitors)
```ini
Priority: numprocs=4, sleep=0
Regular:  numprocs=8, sleep=1
Cleanup:  every 15 minutes
```

Edit di:
- **Windows**: `start-optimized-workers.bat`
- **Linux**: `supervisor-optimized.conf`

---

## 🆘 Troubleshooting

### ❌ Log tidak muncul saat tambah monitor

**Cek 1: Workers berjalan?**
```bash
# Windows
tasklist | findstr "php"

# Linux
ps aux | grep "queue:work"
sudo supervisorctl status uptime-monitor:*
```

**Cek 2: Job masuk queue?**
```sql
SELECT * FROM jobs ORDER BY id DESC LIMIT 10;
```

**Cek 3: Observer terdaftar?**
```bash
php artisan tinker
>>> App\Models\Monitor::getObservableEvents()
```

**Solusi:**
```bash
# Restart workers
# Windows: Close all terminals, run start-optimized-workers.bat lagi
# Linux: sudo supervisorctl restart uptime-monitor:*
```

### ⚠️ Jobs menumpuk

**Cek:**
```bash
php artisan queue:monitor-health
```

**Cleanup:**
```bash
# Auto cleanup
php artisan queue:monitor-health --cleanup --max-age=3600

# Emergency: clear ALL jobs
php truncate_jobs.php
```

**Prevent:**
- Pastikan auto-cleanup service berjalan
- Tambah worker jika traffic tinggi

### 💥 Worker mati terus (memory exhausted)

**Cek memory limit:**
```bash
php -i | grep memory_limit
```

**Tambah di php.ini:**
```ini
memory_limit = 512M
```

**Atau di worker command:**
```bash
php -d memory_limit=512M artisan queue:work ...
```

---

## 📁 File Reference

| File | Keterangan |
|------|------------|
| [`start-optimized-workers.bat`](start-optimized-workers.bat) | ⭐ One-click start semua workers (Windows) |
| [`auto-queue-cleanup.bat`](auto-queue-cleanup.bat) | Auto cleanup service (Windows) |
| [`setup-windows-tasks.bat`](setup-windows-tasks.bat) | Setup Task Scheduler (Windows Admin) |
| [`supervisor-optimized.conf`](supervisor-optimized.conf) | ⭐ Supervisor config (Linux Production) |
| [`production/cron/uptime-monitor`](production/cron/uptime-monitor) | Crontab untuk Laravel scheduler |
| [`app/Observers/MonitorObserver.php`](app/Observers/MonitorObserver.php) | Observer yang auto-dispatch jobs |
| [`OPTIMIZED_MONITORING_SETUP.md`](OPTIMIZED_MONITORING_SETUP.md) | Dokumentasi lengkap (READ THIS!) |
| [`QUICK_START_OPTIMIZED.txt`](QUICK_START_OPTIMIZED.txt) | Quick reference card |

---

## 🎯 Hasil yang Diharapakan

Setelah setup, sistem akan:

✅ **Monitor baru → log muncul 2-5 detik** (via priority queue)  
✅ **Jobs auto-cleanup setiap 30 menit** (no manual intervention)  
✅ **Alert otomatis jika queue overflow** (via queue monitor)  
✅ **Prevent queue bottleneck** (dual queue + auto-cleanup)  
✅ **Workers auto-restart jika crash** (supervisor/task scheduler)

---

## 🚀 Quick Start

### Development (Windows):
```batch
start-optimized-workers.bat
```

### Production (Linux):
```bash
sudo supervisorctl start uptime-monitor:*
```

**That's it!** Monitor baru akan langsung dapat log dalam hitungan detik! 🎉

---

## 📞 Support

Jika ada masalah:

1. Cek [`TROUBLESHOOTING_GUIDE.md`](TROUBLESHOOTING_GUIDE.md)
2. Lihat logs: `tail -f storage/logs/laravel.log`
3. Test dengan: `test-optimized-setup.bat` atau `test-optimized-setup.sh`
4. Review queue: `php artisan queue:monitor-health`

---

**Dokumentasi lengkap:** [`OPTIMIZED_MONITORING_SETUP.md`](OPTIMIZED_MONITORING_SETUP.md)
