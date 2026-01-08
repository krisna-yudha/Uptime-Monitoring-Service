# Fix untuk Monitor Job Timeout

## ✅ Masalah yang Diperbaiki

Error: `Monitor job failed {"exception":"App\\Jobs\\ProcessMonitorCheck has timed out."}`

## 🔧 Solusi yang Diterapkan

### 1. **Menambahkan Timeout Property di Job** 
File: `app/Jobs/ProcessMonitorCheck.php`

```php
public $timeout = 300; // 5 minutes timeout
public $tries = 3;     // 3 kali percobaan
```

**Penjelasan:** 
- Default timeout Laravel adalah 60 detik
- Untuk monitoring, beberapa service mungkin lambat merespons
- Timeout diset ke 5 menit untuk memberikan waktu cukup

### 2. **Mengubah Queue Connection**
File: `.env`

```env
# SEBELUM
QUEUE_CONNECTION=sync

# SESUDAH  
QUEUE_CONNECTION=database
```

**Penjelasan:**
- `sync` = job dijalankan langsung (blocking), timeout ketat
- `database` = job di-queue, lebih flexible, bisa di-retry
- Database queue lebih stabil untuk production

### 3. **Meningkatkan Retry After di Config**
File: `config/queue.php`

```php
'retry_after' => (int) env('DB_QUEUE_RETRY_AFTER', 300), // dari 90 ke 300 detik
```

## 🚀 Cara Menjalankan Setelah Fix

### **PENTING: Restart Queue Worker!**

```bash
# 1. Stop semua worker yang sedang berjalan
# Tutup semua window worker atau Ctrl+C

# 2. Jalankan migration untuk jobs table (jika belum)
php artisan queue:table
php artisan migrate

# 3. Start ulang worker
php artisan queue:work --queue=monitor-checks --sleep=1 --tries=3 --timeout=300

# ATAU gunakan batch file
.\start_all_workers.bat
```

### **Untuk Production (Recommended)**

Gunakan supervisor atau task scheduler untuk auto-restart:

```bash
# Manual start dengan verbose logging
php artisan queue:work --queue=monitor-checks --verbose --sleep=1 --tries=3 --timeout=300
```

## 📊 Monitoring Worker

### Cek Status Job

```bash
# Lihat job yang sedang diproses
php artisan queue:work --once

# Lihat failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all
```

### Clear Job yang Stuck

```bash
# Flush semua jobs
php artisan queue:flush

# Restart monitoring (akan create new jobs)
php artisan app:schedule-monitor-checks
```

## 🔍 Debug Timeout

Jika masih timeout, cek:

1. **Monitor Configuration**
   - Pastikan URL monitor valid
   - Timeout setting per monitor sesuai
   - Interval tidak terlalu cepat

2. **Database Performance**
   - Index pada table `monitors` dan `monitor_checks`
   - Database connection pool
   
3. **Network Issues**
   - Target server lambat respond
   - DNS resolution delay
   - Firewall blocking

## ⚙️ Environment Variables untuk Tuning

Tambahkan di `.env` untuk fine-tuning:

```env
# Queue Configuration
QUEUE_CONNECTION=database
DB_QUEUE_RETRY_AFTER=300

# Worker Configuration  
QUEUE_WORKER_TIMEOUT=300
QUEUE_WORKER_SLEEP=1
QUEUE_WORKER_TRIES=3

# Memory Limit
MEMORY_LIMIT=512M
```

## 📝 Log Monitoring

Monitor log untuk memastikan tidak ada timeout lagi:

```bash
# Real-time log
tail -f storage/logs/laravel.log

# Cari timeout errors
grep -i "timeout" storage/logs/laravel.log

# Cari job failures
grep -i "job failed" storage/logs/laravel.log
```

## ✨ Best Practices

1. **Selalu gunakan queue untuk long-running tasks**
2. **Set timeout sesuai kebutuhan (tidak terlalu pendek/panjang)**
3. **Monitor worker status dengan supervisor**
4. **Setup retry mechanism untuk failed jobs**
5. **Log semua failures untuk debugging**

## 🆘 Troubleshooting

### Job masih timeout?

```bash
# 1. Increase timeout lebih tinggi
public $timeout = 600; // 10 minutes

# 2. Cek process yang hang
ps aux | grep "queue:work"

# 3. Kill process yang stuck
kill -9 [PID]

# 4. Clear dan restart
php artisan queue:flush
php artisan cache:clear
.\start_all_workers.bat
```

### Worker tidak jalan?

```bash
# Cek jobs table ada data?
php artisan tinker
>>> DB::table('jobs')->count()

# Start worker manual dengan debug
php artisan queue:work --queue=monitor-checks --verbose
```

## 📅 Update Log

- **2026-01-08**: Fix timeout issue dengan menambah timeout property dan switch ke database queue
