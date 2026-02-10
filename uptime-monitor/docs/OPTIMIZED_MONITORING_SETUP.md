# 🚀 Setup Optimal: Log Cepat & Auto-Cleanup Jobs

## ⚡ Fitur Sudah Aktif

Sistem sudah memiliki **MonitorObserver** yang otomatis:
- ✅ Men-dispatch job pertama segera saat monitor baru dibuat
- ✅ Menggunakan priority queue untuk monitor baru
- ✅ Auto-schedule check saat monitor di-enable kembali

**File**: `app/Observers/MonitorObserver.php`

---

## 📋 Konfigurasi untuk Development (Windows/XAMPP)

### 1. Setup Queue Worker (Wajib untuk Log Muncul Cepat)

**File**: `start-optimized-workers.bat`

```batch
@echo off
title Uptime Monitor - Optimized Workers
cd /d "%~dp0"

echo Starting Optimized Queue Workers...
echo ===================================
echo.

REM Priority Queue - untuk monitor baru (responsif)
start "Priority Queue" cmd /k "php artisan queue:work database --queue=monitor-checks-priority --tries=3 --timeout=300 --sleep=1"

REM Regular Queue - untuk monitor existing
start "Regular Queue" cmd /k "php artisan queue:work database --queue=monitor-checks --tries=3 --timeout=300 --sleep=3"

REM Notification Worker
start "Notifications" cmd /k "php artisan worker:notifications --verbose"

REM Queue Health Monitor (auto-cleanup)
start "Queue Monitor" cmd /k "php artisan queue:monitor-health --watch --interval=300"

echo.
echo All workers started!
echo Check the new windows for logs.
echo.
pause
```

### 2. Auto-Cleanup Jobs yang Menumpuk

**File**: `auto-queue-cleanup.bat`

```batch
@echo off
title Queue Auto-Cleanup
cd /d "%~dp0"

echo Queue Auto-Cleanup Service
echo ==========================
echo Running cleanup every 30 minutes...
echo.

:loop
echo [%date% %time%] Running cleanup...
php artisan queue:monitor-health --cleanup --max-age=3600
if %errorlevel% neq 0 (
    echo [ERROR] Cleanup failed!
) else (
    echo [OK] Cleanup completed
)
echo.
timeout /t 1800 /nobreak
goto loop
```

### 3. Cron-like Scheduler (Windows Task Scheduler)

**File**: `setup-windows-tasks.bat` (Run as Administrator)

```batch
@echo off
echo Setting up Windows Scheduled Tasks for Uptime Monitor
echo ======================================================

SET PROJECT_PATH=c:\xampp\htdocs\prjctmgng\uptime-monitor

REM Laravel Scheduler - runs every minute
schtasks /create /tn "UptimeMonitor_Scheduler" /tr "php %PROJECT_PATH%\artisan schedule:run" /sc minute /mo 1 /f

REM Queue Health Check - every 5 minutes
schtasks /create /tn "UptimeMonitor_QueueHealth" /tr "php %PROJECT_PATH%\artisan queue:monitor-health" /sc minute /mo 5 /f

REM Queue Cleanup - every 30 minutes
schtasks /create /tn "UptimeMonitor_QueueCleanup" /tr "php %PROJECT_PATH%\artisan queue:monitor-health --cleanup --max-age=3600" /sc minute /mo 30 /f

echo.
echo Tasks created successfully!
echo.
echo To view tasks:
echo   schtasks /query /tn "UptimeMonitor*"
echo.
echo To delete tasks:
echo   schtasks /delete /tn "UptimeMonitor_Scheduler" /f
echo   schtasks /delete /tn "UptimeMonitor_QueueHealth" /f
echo   schtasks /delete /tn "UptimeMonitor_QueueCleanup" /f
echo.
pause
```

---

## 🐧 Konfigurasi untuk Production (Linux/Supervisor)

### 1. Supervisor Config - Optimized

**File**: `supervisor-optimized.conf`

```ini
# Supervisor Configuration - Optimized for Fast Response & Auto-Cleanup
# Installation:
# sudo cp supervisor-optimized.conf /etc/supervisor/conf.d/uptime-monitor.conf
# sudo supervisorctl reread && sudo supervisorctl update

# Priority Queue Worker - untuk monitor baru (multiple processes)
[program:uptime-priority-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/uptime-monitor/artisan queue:work database --queue=monitor-checks-priority --tries=3 --timeout=300 --sleep=1
directory=/var/www/uptime-monitor
user=www-data
numprocs=2
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
redirect_stderr=true
stdout_logfile=/var/www/uptime-monitor/storage/logs/worker-priority.log
stdout_logfile_maxbytes=10MB
stdout_logfile_backups=5
priority=1000

# Regular Queue Worker - untuk monitor existing
[program:uptime-regular-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/uptime-monitor/artisan queue:work database --queue=monitor-checks --tries=3 --timeout=300 --sleep=3
directory=/var/www/uptime-monitor
user=www-data
numprocs=4
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
redirect_stderr=true
stdout_logfile=/var/www/uptime-monitor/storage/logs/worker-regular.log
stdout_logfile_maxbytes=10MB
stdout_logfile_backups=5
priority=999

# Notification Worker
[program:uptime-notifications]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/uptime-monitor/artisan worker:notifications --verbose
directory=/var/www/uptime-monitor
user=www-data
numprocs=1
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
redirect_stderr=true
stdout_logfile=/var/www/uptime-monitor/storage/logs/worker-notifications.log
stdout_logfile_maxbytes=10MB
stdout_logfile_backups=5
priority=998

# Queue Health Monitor - Auto monitor & alert
[program:uptime-queue-monitor]
command=bash -c 'while true; do php /var/www/uptime-monitor/artisan queue:monitor-health && sleep 300; done'
directory=/var/www/uptime-monitor
user=www-data
autostart=true
autorestart=true
redirect_stderr=true
stdout_logfile=/var/www/uptime-monitor/storage/logs/queue-monitor.log
stdout_logfile_maxbytes=5MB
stdout_logfile_backups=3
priority=997

# Queue Auto-Cleanup - Cleanup jobs older than 1 hour
[program:uptime-queue-cleanup]
command=bash -c 'while true; do php /var/www/uptime-monitor/artisan queue:monitor-health --cleanup --max-age=3600 && sleep 1800; done'
directory=/var/www/uptime-monitor
user=www-data
autostart=true
autorestart=true
redirect_stderr=true
stdout_logfile=/var/www/uptime-monitor/storage/logs/queue-cleanup.log
stdout_logfile_maxbytes=5MB
stdout_logfile_backups=3
priority=996

# Group all programs
[group:uptime-monitor]
programs=uptime-priority-queue,uptime-regular-queue,uptime-notifications,uptime-queue-monitor,uptime-queue-cleanup
```

### 2. Crontab untuk Laravel Scheduler

**File**: `/etc/cron.d/uptime-monitor`

```bash
SHELL=/bin/bash
PATH=/usr/local/sbin:/usr/local/bin:/sbin:/bin:/usr/sbin:/usr/bin
MAILTO=""

# Laravel Task Scheduler - Runs every minute
* * * * * www-data cd /var/www/uptime-monitor && php artisan schedule:run >> /dev/null 2>&1
```

**Setup**:
```bash
sudo cp /var/www/uptime-monitor/production/cron/uptime-monitor /etc/cron.d/uptime-monitor
sudo chmod 644 /etc/cron.d/uptime-monitor
sudo service cron reload
```

---

## 🎯 Optimasi Parameter

### Database Queue Configuration

**File**: `config/queue.php` (pastikan sudah seperti ini)

```php
'database' => [
    'driver' => 'database',
    'table' => 'jobs',
    'queue' => 'default',
    'retry_after' => 300,  // 5 minutes timeout
    'after_commit' => false,
],
```

### Monitor Observer Settings

Observer sudah optimal dengan:
- ✅ Immediate dispatch untuk monitor baru
- ✅ Priority queue: `monitor-checks-priority`
- ✅ Auto-schedule saat enable monitor

---

## 📊 Monitoring & Troubleshooting

### Check Queue Status
```bash
# Development (Windows)
php artisan queue:monitor-health

# Production (Linux)
sudo supervisorctl status uptime-monitor:*
```

### View Logs Real-time
```bash
# Development
tail -f storage/logs/laravel.log

# Production
sudo tail -f /var/log/supervisor/worker-*.log
sudo tail -f /var/www/uptime-monitor/storage/logs/*.log
```

### Manual Test Monitor Baru
```bash
# Tambah monitor via API/UI, lalu check:
php artisan queue:monitor-health

# Lihat apakah job langsung masuk ke queue
# Lihat worker log apakah langsung process
```

---

## ⚙️ Cara Setup

### Development (Windows/XAMPP)

**Opsi 1: Manual Workers** (Recommended untuk testing)
```batch
# Terminal 1 - Priority Queue
php artisan queue:work database --queue=monitor-checks-priority --tries=3 --timeout=300 --sleep=1

# Terminal 2 - Regular Queue  
php artisan queue:work database --queue=monitor-checks --tries=3 --timeout=300 --sleep=3

# Terminal 3 - Auto Cleanup
auto-queue-cleanup.bat
```

**Opsi 2: Batch Script** (Recommended untuk development)
```batch
start-optimized-workers.bat
```

**Opsi 3: Windows Task Scheduler** (Recommended untuk production-like)
```batch
# Run as Administrator
setup-windows-tasks.bat
```

### Production (Linux/Supervisor)

```bash
# 1. Copy supervisor config
sudo cp supervisor-optimized.conf /etc/supervisor/conf.d/uptime-monitor.conf

# 2. Update paths sesuai instalasi Anda
sudo nano /etc/supervisor/conf.d/uptime-monitor.conf

# 3. Reload supervisor
sudo supervisorctl reread
sudo supervisorctl update

# 4. Start all workers
sudo supervisorctl start uptime-monitor:*

# 5. Setup cron
sudo cp production/cron/uptime-monitor /etc/cron.d/uptime-monitor
sudo chmod 644 /etc/cron.d/uptime-monitor
sudo service cron reload

# 6. Verify
sudo supervisorctl status uptime-monitor:*
```

---

## 🎯 Hasil yang Diharapkan

### Saat Tambah Monitor Baru:
1. ✅ **Observer otomatis dispatch** job ke `monitor-checks-priority`
2. ✅ **Priority worker** langsung process (sleep hanya 1 detik)
3. ✅ **Log muncul dalam 2-5 detik** di UI
4. ✅ **Status langsung update** di dashboard

### Auto-Cleanup Jobs:
1. ✅ **Queue monitor** cek setiap 5 menit
2. ✅ **Auto cleanup** jobs > 1 jam setiap 30 menit
3. ✅ **Mencegah** penumpukan jobs
4. ✅ **Alert** jika queue > threshold

---

## 🔧 Parameter Tuning

Sesuaikan berdasarkan load:

```bash
# Low Traffic (< 50 monitors)
numprocs=2 (priority), 2 (regular)
sleep=1 (priority), 5 (regular)
cleanup_interval=3600 (1 hour)

# Medium Traffic (50-200 monitors)  
numprocs=2 (priority), 4 (regular)
sleep=1 (priority), 3 (regular)
cleanup_interval=1800 (30 min)

# High Traffic (> 200 monitors)
numprocs=4 (priority), 8 (regular)
sleep=0 (priority), 1 (regular)
cleanup_interval=900 (15 min)
```

---

## 📝 Checklist

- [ ] MonitorObserver aktif di AppServiceProvider
- [ ] Priority queue worker berjalan
- [ ] Regular queue worker berjalan
- [ ] Auto-cleanup service berjalan
- [ ] Laravel scheduler aktif (cron/task scheduler)
- [ ] Test tambah monitor baru → log muncul < 5 detik
- [ ] Monitor queue health secara berkala
- [ ] Setup alerting jika queue overflow

---

## 🆘 Troubleshooting

### Log tidak muncul saat tambah monitor:
```bash
# 1. Cek observer terdaftar
php artisan tinker
>>> App\Models\Monitor::getObservableEvents()

# 2. Cek job masuk queue
SELECT * FROM jobs ORDER BY id DESC LIMIT 10;

# 3. Cek worker berjalan
ps aux | grep "queue:work"  # Linux
tasklist | findstr "php"   # Windows

# 4. Manual dispatch
php artisan tinker
>>> dispatch(new \App\Jobs\ProcessMonitorCheck(\App\Models\Monitor::find(X)));
```

### Jobs menumpuk:
```bash
# 1. Cek jumlah jobs
php artisan queue:monitor-health

# 2. Manual cleanup
php artisan queue:monitor-health --cleanup --max-age=3600

# 3. Emergency: clear all
php truncate_jobs.php
```

---

Dengan setup ini, sistem akan:
- ⚡ **Respond cepat** saat monitor baru (2-5 detik)
- 🧹 **Auto-cleanup** jobs yang menumpuk
- 📊 **Monitor** queue health otomatis
- 🔔 **Alert** jika ada masalah

Pilih setup sesuai environment Anda (Development/Production) dan ikuti langkah-langkah di atas!
