# Troubleshooting Guide - Monitoring Logs Tidak Keluar

## Masalah
Saat monitor baru ditambahkan, log messages monitoring tidak keluar, hanya log SSL cert saja.

## Penyebab Umum

### 1. Queue Tidak Diproses
Job `ProcessMonitorCheck` didispatch ke queue tapi tidak diproses oleh worker.

**Cek:**
```bash
# Apakah workers berjalan?
sudo supervisorctl status | grep uptime

# Apakah ada jobs di queue?
redis-cli
LLEN queues:monitor-checks-priority
LLEN queues:monitor-checks
exit
```

**Solusi:**
```bash
# Restart workers
sudo supervisorctl restart uptime-queue-worker:*
sudo supervisorctl restart uptime-notification-worker:*
```

### 2. Redis Tidak Terkonfigurasi dengan Benar
Queue connection di `.env` masih menggunakan `database` bukan `redis`.

**Cek:**
```bash
cd /var/www/monitoring/Uptime-Monitoring-Service/uptime-monitor
grep QUEUE_CONNECTION .env
```

**Harus:**
```env
QUEUE_CONNECTION=redis
```

**Solusi:**
```bash
# Update .env
nano .env
# Ubah: QUEUE_CONNECTION=redis

# Clear cache
php artisan config:clear
php artisan config:cache

# Restart workers
sudo supervisorctl restart uptime-queue-worker:*
```

### 3. PostgreSQL Advisory Lock Error
Bug di `ProcessMonitorCheck.php` yang sudah diperbaiki belum di-deploy.

**Cek:**
```bash
cd /var/www/monitoring/Uptime-Monitoring-Service/uptime-monitor
grep "pg_try_advisory_lock({" app/Jobs/ProcessMonitorCheck.php
```

**Harus muncul:**
```php
$lockResult = DB::select("SELECT pg_try_advisory_lock({$lockKey}) as lock_acquired");
```

**Solusi:**
```bash
# Pull latest code
git pull

# Atau copy file yang sudah diperbaiki
# Restart workers
sudo supervisorctl restart uptime-queue-worker:*
```

### 4. Worker Tidak Listen ke Queue yang Tepat
Job didispatch ke `monitor-checks-priority` tapi worker tidak listen queue tersebut.

**Cek:**
```bash
sudo cat /etc/supervisor/conf.d/uptime-monitor.conf | grep "queue:work"
```

**Harus ada:**
```
--queue=monitor-checks-priority,monitor-checks,default
```

**Solusi sudah benar** di config yang saya buat.

### 5. MonitoringLog Tidak Tersimpan ke Database
Error saat menyimpan log ke tabel `monitoring_logs`.

**Cek:**
```bash
# Check apakah tabel ada
php artisan tinker
\Schema::hasTable('monitoring_logs');
exit

# Check recent logs
php artisan tinker
\App\Models\MonitoringLog::count();
\App\Models\MonitoringLog::latest()->first();
exit
```

**Solusi:**
```bash
# Run migrations jika tabel tidak ada
php artisan migrate

# Check error di Laravel logs
tail -100 storage/logs/laravel.log | grep MonitoringLog
```

## Quick Fix - Step by Step

```bash
# 1. Navigate to app directory
cd /var/www/monitoring/Uptime-Monitoring-Service/uptime-monitor

# 2. Check environment
grep QUEUE_CONNECTION .env
# Should be: QUEUE_CONNECTION=redis

# 3. Update if wrong
sed -i 's/QUEUE_CONNECTION=.*/QUEUE_CONNECTION=redis/' .env

# 4. Clear cache
php artisan config:clear
php artisan config:cache

# 5. Check Redis is running
redis-cli ping

# 6. Check queue size
redis-cli LLEN queues:monitor-checks-priority

# 7. Restart all workers
sudo supervisorctl restart uptime-monitor-checks:*
sudo supervisorctl restart uptime-queue-worker:*
sudo supervisorctl restart uptime-notification-worker:*

# 8. Check workers status
sudo supervisorctl status | grep uptime

# 9. Test dengan tambah monitor baru
# Kemudian check logs:

# 10. Monitor real-time logs
tail -f storage/logs/laravel.log

# Di terminal lain, tambah monitor baru dari frontend
# Anda harus lihat logs seperti:
# - "ProcessMonitorCheck started"
# - "Creating MonitorCheck record"
# - "MonitorCheck record created"
# - "Monitor check completed"
```

## Verification Script

```bash
#!/bin/bash
# Save as check-monitoring.sh

echo "=== Checking Monitoring System ==="

echo -n "Redis: "
redis-cli ping

echo -n "Queue Jobs (monitor-checks-priority): "
redis-cli LLEN queues:monitor-checks-priority

echo -n "Workers Status: "
sudo supervisorctl status | grep -c RUNNING

echo "Recent Monitor Checks:"
cd /var/www/monitoring/Uptime-Monitoring-Service/uptime-monitor
php artisan tinker --execute="echo \App\Models\MonitorCheck::count() . ' total checks';"

echo "Recent Monitoring Logs:"
php artisan tinker --execute="echo \App\Models\MonitoringLog::count() . ' total logs';"
```

## Expected Behavior

Ketika monitor baru ditambahkan, Anda harus melihat:

### Di Laravel Logs (`storage/logs/laravel.log`):
```
[timestamp] local.INFO: Initial monitor check dispatched to priority queue {"monitor_id":X}
[timestamp] local.INFO: ProcessMonitorCheck started {"monitor_id":X}
[timestamp] local.INFO: Creating MonitorCheck record {"monitor_id":X}
[timestamp] local.INFO: MonitorCheck record created {"monitor_id":X,"check_id":Y}
[timestamp] local.INFO: Monitor check completed {"monitor_id":X,"status":"up"}
```

### Di Database (`monitoring_logs` table):
```sql
SELECT * FROM monitoring_logs WHERE monitor_id = X ORDER BY created_at DESC;
```

Should show:
- `check_start` event
- `check_complete` event
- `status_change` event (jika ada perubahan status)

### Di Redis Queue:
```bash
redis-cli LLEN queues:monitor-checks-priority
# Should be 0 (semua sudah diproses)
```

## Jika Masih Tidak Keluar

```bash
# Test manual dispatch
php artisan tinker

$monitor = \App\Models\Monitor::first();
\App\Jobs\ProcessMonitorCheck::dispatch($monitor)->onQueue('monitor-checks-priority');
exit

# Tunggu beberapa detik, lalu check logs
tail -20 storage/logs/laravel.log

# Jika tetap tidak ada logs, berarti worker tidak berjalan
sudo supervisorctl status | grep uptime

# Check worker logs
sudo tail -50 /var/log/supervisor/uptime-queue-worker.log
```

---

**Last Updated**: January 2026
