# Uptime Monitor Workers

## Overview

Uptime Monitor menggunakan sistem queue untuk memproses tugas secara asynchronous. Ada 2 worker utama:

## 1. Monitor Checks Worker

**Fungsi:** Menjalankan pengecekan service monitoring (HTTP, TCP, Ping, dll)

**Queue:** `monitor-checks`

**Cara Menjalankan:**
```bash
# Manual via artisan
php artisan worker:monitor-checks

# Atau gunakan batch file
run_monitor_worker.bat
```

**Konfigurasi:**
- `--queue=monitor-checks` - Queue yang didengarkan
- `--sleep=1` - Delay 1 detik saat tidak ada job
- `--tries=1` - Tidak retry karena monitor akan dijadwalkan ulang
- `--timeout=30` - Timeout 30 detik per job

## 2. Notification Worker (BARU)

**Fungsi:** Mengirim notifikasi ke channels (Telegram, Discord, Slack, Webhook)

**Queue:** `notifications`

**Cara Menjalankan:**
```bash
# Manual via artisan
php artisan worker:notifications

# Atau gunakan batch file
run_notification_worker.bat
```

**Konfigurasi:**
- `--queue=notifications` - Queue khusus notifikasi
- `--sleep=3` - Delay 3 detik saat tidak ada job
- `--tries=3` - Retry 3x jika gagal
- `--timeout=60` - Timeout 60 detik per job

## Menjalankan Semua Workers Sekaligus

Gunakan script helper untuk menjalankan semua workers dalam window terpisah:

```bash
start_all_workers.bat
```

Script ini akan membuka 2 terminal window:
1. Monitor Checks Worker
2. Notification Worker

## Cara Kerja Queue System

### Monitor Checks
1. Scheduler menjalankan `schedule:monitor-checks` setiap menit
2. Command menambahkan job `ProcessMonitorCheck` ke queue `monitor-checks`
3. Monitor worker memproses job dan melakukan pengecekan service
4. Jika service down, membuat incident dan dispatch notification job

### Notifications
1. Saat incident terjadi, `SendNotification` job ditambahkan ke queue `notifications`
2. Notification worker mengambil job dari queue
3. Worker mengirim notifikasi ke semua channels yang terkait dengan monitor
4. Jika gagal, job akan di-retry hingga 3x dengan delay 5 detik

## File-file Penting

### Commands
- `app/Console/Commands/RunMonitorWorker.php` - Monitor worker command
- `app/Console/Commands/RunNotificationWorker.php` - Notification worker command
- `app/Console/Commands/ScheduleMonitorChecks.php` - Scheduler untuk monitor checks

### Jobs
- `app/Jobs/ProcessMonitorCheck.php` - Job untuk pengecekan monitor
- `app/Jobs/SendNotification.php` - Job untuk kirim notifikasi

### Batch Files
- `run_monitor_worker.bat` - Jalankan monitor worker
- `run_notification_worker.bat` - Jalankan notification worker
- `start_all_workers.bat` - Jalankan semua workers sekaligus

## Queue Configuration

Edit `config/queue.php` untuk konfigurasi queue:

```php
'connections' => [
    'database' => [
        'driver' => 'database',
        'table' => 'jobs',
        'queue' => 'default',
        'retry_after' => 90,
    ],
],
```

## Monitoring Workers

### Cara Melihat Status Queue

```bash
# Lihat jumlah job di queue
php artisan queue:monitor notifications monitor-checks

# Lihat failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all
```

### Log Files

Worker akan mencatat aktivitas di:
- `storage/logs/laravel.log` - Log umum aplikasi
- Console output pada terminal worker

## Troubleshooting

### Worker Tidak Memproses Job

1. **Cek apakah worker berjalan:**
   - Pastikan window terminal worker masih terbuka
   - Lihat output log di terminal

2. **Cek queue table:**
   ```sql
   SELECT * FROM jobs WHERE queue = 'notifications';
   ```

3. **Cek failed jobs:**
   ```bash
   php artisan queue:failed
   ```

### Notifikasi Tidak Terkirim

1. **Cek notification worker sedang berjalan:**
   - Harus ada terminal "Notification Worker" yang aktif

2. **Cek monitor memiliki notification channels:**
   - Pastikan monitor punya `notification_channels` yang terisi
   - Lihat di database: `SELECT notification_channels FROM monitors WHERE id = ?`

3. **Cek SSL certificate (development):**
   - Sudah di-disable di `SendNotification.php` dengan `verify => false`

4. **Cek channel configuration:**
   - Pastikan webhook URL/bot token valid
   - Test channel dengan tombol "Test" di UI

### Restart Workers

Jika ada perubahan code, restart workers:

1. Tekan `Ctrl+C` di setiap terminal worker
2. Jalankan ulang `start_all_workers.bat`

## Production Deployment

Untuk production, gunakan supervisor atau systemd:

### Supervisor Config (Linux)

```ini
[program:uptime-monitor-checks]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/artisan worker:monitor-checks
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/path/to/storage/logs/monitor-worker.log

[program:uptime-notifications]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/artisan worker:notifications
autostart=true
autorestart=true
user=www-data
numprocs=3
redirect_stderr=true
stdout_logfile=/path/to/storage/logs/notification-worker.log
```

### Windows Service

Gunakan NSSM (Non-Sucking Service Manager) untuk membuat Windows Service.

## Best Practices

1. **Selalu jalankan kedua workers** untuk sistem berfungsi penuh
2. **Monitor worker logs** untuk mendeteksi masalah
3. **Set up proper logging** di production
4. **Use supervisor/systemd** di production untuk auto-restart
5. **Monitor queue depth** - jika queue menumpuk, tambah worker

## Support

Jika ada masalah, cek:
1. Laravel logs: `storage/logs/laravel.log`
2. Worker console output
3. Database `failed_jobs` table
