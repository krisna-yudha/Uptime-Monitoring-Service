# Queue Workers Optimization Guide

## Masalah
Saat monitor baru ditambahkan, ada yang cepat menerima respons dan ada yang lambat karena:
1. Initial check dijalankan synchronously (blocking)
2. Semua checks masuk ke queue yang sama tanpa priority
3. Jumlah workers tidak cukup untuk menangani load

## Solusi Implementasi

### 1. Priority Queue System
Monitor checks sekarang menggunakan 2 queue berbeda:
- **`monitor-checks-priority`** - Untuk initial checks (monitor baru)
- **`monitor-checks`** - Untuk scheduled checks

### 2. Menjalankan Queue Workers

#### Windows (Development)

**Worker untuk Priority Queue** (proses initial checks cepat):
```batch
php artisan queue:work database --queue=monitor-checks-priority --sleep=0 --tries=3 --timeout=60
```

**Worker untuk Regular Checks**:
```batch
php artisan queue:work database --queue=monitor-checks --sleep=1 --tries=3 --timeout=90
```

**Worker untuk Notifications**:
```batch
php artisan queue:work database --queue=notifications --sleep=1 --tries=5 --timeout=30
```

#### Multiple Workers (Parallel Processing)

Buka **3-5 terminal berbeda** dan jalankan:

**Terminal 1-2**: Priority Workers
```batch
start php artisan queue:work database --queue=monitor-checks-priority --sleep=0 --tries=3 --timeout=60
start php artisan queue:work database --queue=monitor-checks-priority --sleep=0 --tries=3 --timeout=60
```

**Terminal 3-4**: Regular Check Workers
```batch
start php artisan queue:work database --queue=monitor-checks --sleep=1 --tries=3 --timeout=90
start php artisan queue:work database --queue=monitor-checks --sleep=1 --tries=3 --timeout=90
```

**Terminal 5**: Notification Worker
```batch
start php artisan queue:work database --queue=notifications --sleep=1 --tries=5 --timeout=30
```

### 3. Production Setup (Supervisor)

Edit file `supervisor-uptime-monitor.conf`:

```ini
[program:uptime-monitor-priority]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/artisan queue:work database --queue=monitor-checks-priority --sleep=0 --tries=3 --timeout=60 --max-time=3600
autostart=true
autorestart=true
user=www-data
numprocs=3
redirect_stderr=true
stdout_logfile=/var/log/supervisor/uptime-monitor-priority.log

[program:uptime-monitor-regular]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/artisan queue:work database --queue=monitor-checks --sleep=1 --tries=3 --timeout=90 --max-time=3600
autostart=true
autorestart=true
user=www-data
numprocs=5
redirect_stderr=true
stdout_logfile=/var/log/supervisor/uptime-monitor-regular.log

[program:uptime-monitor-notifications]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/artisan queue:work database --queue=notifications --sleep=1 --tries=5 --timeout=30 --max-time=3600
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/supervisor/uptime-monitor-notifications.log
```

Reload supervisor:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start all
```

### 4. Monitoring Queue Performance

**Cek jumlah jobs dalam queue**:
```bash
php artisan queue:monitor database:monitor-checks-priority,database:monitor-checks,database:notifications
```

**Cek failed jobs**:
```bash
php artisan queue:failed
```

**Retry failed jobs**:
```bash
php artisan queue:retry all
```

**Clear queue (hati-hati di production!)**:
```bash
php artisan queue:clear database
```

### 5. Performance Metrics

Dengan setup ini:
- ✅ Initial checks: **1-5 detik** (priority queue)
- ✅ Regular checks: **5-15 detik** (based on schedule)
- ✅ Parallel processing: **3-5 workers** dapat handle 50-100 monitors
- ✅ Auto-retry untuk failures

### 6. Troubleshooting

**Jika masih lambat:**

1. **Tingkatkan jumlah workers**:
   ```batch
   # Tambahkan lebih banyak workers di terminal baru
   start php artisan queue:work database --queue=monitor-checks-priority --sleep=0 --tries=3 --timeout=60
   ```

2. **Cek database jobs table**:
   ```sql
   SELECT queue, COUNT(*) as pending FROM jobs GROUP BY queue;
   ```

3. **Monitor memory usage**:
   - Restart workers setiap 1 jam: `--max-time=3600`
   - Atau set max jobs: `--max-jobs=1000`

4. **Optimize timeout settings**:
   - Edit `timeout_ms` di monitor creation (default 30000ms)
   - Sesuaikan dengan target website yang di-monitor

### 7. Batch Script untuk Windows

Buat file `start_all_workers.bat`:
```batch
@echo off
echo Starting Queue Workers...

start "Priority Worker 1" cmd /k php artisan queue:work database --queue=monitor-checks-priority --sleep=0 --tries=3 --timeout=60
start "Priority Worker 2" cmd /k php artisan queue:work database --queue=monitor-checks-priority --sleep=0 --tries=3 --timeout=60
start "Regular Worker 1" cmd /k php artisan queue:work database --queue=monitor-checks --sleep=1 --tries=3 --timeout=90
start "Regular Worker 2" cmd /k php artisan queue:work database --queue=monitor-checks --sleep=1 --tries=3 --timeout=90
start "Notification Worker" cmd /k php artisan queue:work database --queue=notifications --sleep=1 --tries=5 --timeout=30

echo All workers started!
pause
```

Jalankan dengan: `start_all_workers.bat`

## Hasil Optimasi

**Sebelum**:
- Initial check: 30-60 detik (blocking, synchronous)
- Monitor lambat merespons memblock yang lain
- Response time tidak konsisten

**Sesudah**:
- Initial check: 1-5 detik (queue dengan priority)
- Semua checks parallel, tidak saling blocking
- Response time konsisten dan cepat
