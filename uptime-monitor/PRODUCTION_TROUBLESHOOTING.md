# Production Troubleshooting - Monitor Status Unknown

## 🔴 Masalah di Production

Dari log browser console:
```
status: 'unknown'
updateHistoryRealtime fetched 0 checks
No data for realtime update
```

**Root Cause:** Monitoring job **tidak berjalan** atau **tidak menyimpan data checks**.

---

## ✅ Langkah Troubleshooting

### **STEP 1: Cek Queue Worker Berjalan atau Tidak**

Di production server, jalankan command ini:

```bash
# Cek process queue worker
ps aux | grep "queue:work"

# Atau di Windows
tasklist | findstr "php"
```

**Jika TIDAK ADA process queue:work:**
- ❌ Worker tidak berjalan
- ✅ Lanjut ke STEP 2

**Jika ADA process queue:work:**
- ✅ Worker berjalan
- ⚠️ Tapi mungkin stuck atau timeout
- ✅ Lanjut ke STEP 3

---

### **STEP 2: Start Queue Worker di Production**

#### **A. Manual Start (untuk testing)**

```bash
cd /path/to/uptime-monitor

# Start worker dengan timeout fix
php artisan queue:work \
  --queue=monitor-checks \
  --sleep=1 \
  --tries=3 \
  --timeout=300 \
  --verbose
```

#### **B. Background Process (recommended)**

```bash
# Dengan nohup (Linux)
nohup php artisan queue:work \
  --queue=monitor-checks \
  --sleep=1 \
  --tries=3 \
  --timeout=300 \
  > storage/logs/queue-worker.log 2>&1 &

# Atau dengan screen
screen -dmS queue-worker php artisan queue:work \
  --queue=monitor-checks \
  --sleep=1 \
  --tries=3 \
  --timeout=300 \
  --verbose
```

#### **C. Dengan Supervisor (production best practice)**

Create file `/etc/supervisor/conf.d/uptime-monitor-worker.conf`:

```ini
[program:uptime-monitor-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/uptime-monitor/artisan queue:work --queue=monitor-checks --sleep=1 --tries=3 --timeout=300
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/uptime-monitor/storage/logs/worker.log
stopwaitsecs=3600
```

Kemudian:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start uptime-monitor-worker:*
sudo supervisorctl status
```

---

### **STEP 3: Cek Jobs di Queue**

```bash
# Cek apakah ada jobs di database
php artisan tinker
>>> DB::table('jobs')->count()
>>> DB::table('jobs')->first()
>>> exit

# Cek failed jobs
php artisan queue:failed
```

**Jika ada jobs di queue tapi worker tidak process:**
```bash
# Restart worker
sudo supervisorctl restart uptime-monitor-worker:*

# Atau kill process dan start ulang
pkill -f "queue:work"
php artisan queue:work --queue=monitor-checks --sleep=1 --tries=3 --timeout=300 &
```

---

### **STEP 4: Manual Trigger Monitor Check**

Test apakah monitoring job bisa berjalan:

```bash
# Dispatch job untuk monitor ID 46 (dari log anda)
php artisan tinker
>>> $monitor = App\Models\Monitor::find(46);
>>> App\Jobs\ProcessMonitorCheck::dispatch($monitor);
>>> exit

# Check hasil setelah beberapa detik
php artisan tinker
>>> App\Models\Monitor::find(46)->checks()->latest()->first()
>>> exit
```

**Jika berhasil:**
- ✅ Job bisa jalan
- ⚠️ Tapi scheduler mungkin tidak jalan
- ✅ Lanjut STEP 5

**Jika gagal/timeout:**
- ❌ Ada masalah di job execution
- ✅ Check log errors

---

### **STEP 5: Cek Scheduler Berjalan**

Scheduler Laravel harus berjalan untuk dispatch monitoring jobs secara otomatis.

```bash
# Cek crontab
crontab -l

# Harus ada entry seperti ini:
* * * * * cd /var/www/uptime-monitor && php artisan schedule:run >> /dev/null 2>&1
```

**Jika TIDAK ada crontab:**

```bash
# Edit crontab
crontab -e

# Tambahkan baris ini:
* * * * * cd /var/www/uptime-monitor && php artisan schedule:run >> /dev/null 2>&1
```

**Test scheduler:**
```bash
# Run manual untuk test
php artisan schedule:run

# Cek log
tail -f storage/logs/laravel.log
```

---

### **STEP 6: Cek Environment Config**

Pastikan `.env` di production sudah benar:

```bash
cat .env | grep QUEUE

# Harus:
QUEUE_CONNECTION=database
```

Jika masih `sync`:
```bash
# Edit .env
nano .env

# Ubah:
QUEUE_CONNECTION=database

# Clear cache
php artisan config:clear
php artisan cache:clear

# Restart worker
sudo supervisorctl restart uptime-monitor-worker:*
```

---

### **STEP 7: Check Logs untuk Error**

```bash
# Laravel log
tail -100 storage/logs/laravel.log | grep -i "error\|exception\|timeout"

# Worker log (jika pakai supervisor)
tail -100 storage/logs/worker.log

# System log
tail -100 /var/log/syslog | grep php

# Nginx/Apache error log
tail -100 /var/log/nginx/error.log
tail -100 /var/log/apache2/error.log
```

---

## 🔧 Quick Fix Commands

### **Reset dan Start Fresh**

```bash
cd /var/www/uptime-monitor

# 1. Stop semua worker
pkill -f "queue:work"
# atau
sudo supervisorctl stop uptime-monitor-worker:*

# 2. Flush queue dan failed jobs
php artisan queue:flush
php artisan queue:restart

# 3. Clear cache
php artisan config:clear
php artisan cache:clear

# 4. Start worker dengan timeout fix
nohup php artisan queue:work \
  --queue=monitor-checks \
  --sleep=1 \
  --tries=3 \
  --timeout=300 \
  --verbose \
  > storage/logs/queue-worker.log 2>&1 &

# 5. Check process
ps aux | grep "queue:work"

# 6. Trigger test monitoring
php artisan app:schedule-monitor-checks

# 7. Wait 30 seconds kemudian check
sleep 30
php artisan tinker
>>> App\Models\Monitor::find(46)->checks()->count()
>>> exit
```

---

## 📊 Monitoring Worker Status

### **Real-time Log Monitoring**

```bash
# Monitor worker log
tail -f storage/logs/queue-worker.log

# Monitor Laravel log
tail -f storage/logs/laravel.log

# Monitor both
tail -f storage/logs/queue-worker.log storage/logs/laravel.log
```

### **Worker Health Check Script**

Create file `check-worker.sh`:

```bash
#!/bin/bash

WORKER_COUNT=$(ps aux | grep -c "queue:work.*monitor-checks")

if [ $WORKER_COUNT -lt 2 ]; then
    echo "❌ Worker not running! Starting..."
    cd /var/www/uptime-monitor
    nohup php artisan queue:work \
      --queue=monitor-checks \
      --sleep=1 \
      --tries=3 \
      --timeout=300 \
      > storage/logs/queue-worker.log 2>&1 &
    echo "✅ Worker started"
else
    echo "✅ Worker running ($((WORKER_COUNT - 1)) process)"
fi

# Check jobs
JOBS=$(php artisan tinker --execute="echo DB::table('jobs')->count(); exit;" 2>/dev/null | tail -1)
echo "📋 Jobs in queue: $JOBS"

# Check recent checks
RECENT=$(php artisan tinker --execute="echo App\Models\MonitorCheck::where('checked_at', '>', now()->subMinutes(5))->count(); exit;" 2>/dev/null | tail -1)
echo "✅ Checks in last 5 min: $RECENT"
```

Run berkala:
```bash
chmod +x check-worker.sh
./check-worker.sh

# Atau tambah ke crontab untuk auto-check setiap 5 menit
*/5 * * * * /var/www/uptime-monitor/check-worker.sh >> /var/log/worker-check.log 2>&1
```

---

## 🚨 Common Issues di Production

### **1. Worker Tidak Auto-Start saat Server Reboot**

**Solusi:** Gunakan supervisor atau systemd

### **2. Worker Stuck/Hang**

**Solusi:**
```bash
# Kill dan restart
pkill -9 -f "queue:work"
php artisan queue:restart
# Start ulang worker
```

### **3. Memory Limit**

Worker consume memory over time. **Restart periodik:**

```bash
# Dalam supervisor config, tambah:
stopwaitsecs=3600
```

Atau restart manual tiap 12 jam via cron:
```bash
0 */12 * * * supervisorctl restart uptime-monitor-worker:*
```

### **4. Database Connection Lost**

Worker lose DB connection setelah idle.

**Fix:** Set `DB_QUEUE_RETRY_AFTER` lebih rendah:
```env
DB_QUEUE_RETRY_AFTER=90
```

Dan restart worker tiap jam:
```bash
0 * * * * supervisorctl restart uptime-monitor-worker:*
```

---

## ✅ Verification Steps

Setelah fix, verify dengan:

```bash
# 1. Worker berjalan
ps aux | grep "queue:work"

# 2. Scheduler berjalan
crontab -l | grep schedule:run

# 3. Jobs diprocess
watch -n 5 'php artisan tinker --execute="echo DB::table(\"jobs\")->count(); exit;" 2>/dev/null | tail -1'

# 4. Checks bertambah
watch -n 10 'php artisan tinker --execute="echo App\Models\MonitorCheck::count(); exit;" 2>/dev/null | tail -1'

# 5. Monitor status updated
php artisan tinker
>>> App\Models\Monitor::find(46)->refresh()
>>> exit
```

---

## 📞 Need More Help?

Jika masih bermasalah, kirim informasi ini:

```bash
# System info
uname -a
php -v

# Process info
ps aux | grep php

# Queue config
php artisan tinker --execute="echo config('queue.default'); exit;"

# Recent logs
tail -50 storage/logs/laravel.log

# Environment
cat .env | grep -E "QUEUE|DB_|APP_ENV"
```
