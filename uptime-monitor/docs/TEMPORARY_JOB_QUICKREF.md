# 🎯 QUICK REFERENCE - TEMPORARY JOB SYSTEM

## ✅ Apa yang Sudah Diubah?

### 1. ProcessMonitorCheck.php
- ❌ **HAPUS**: Auto-requeue logic (job tidak dispatch dirinya lagi)
- ✅ **TAMBAH**: `$deleteWhenMissingModels = true` (auto-cleanup)
- ✅ **RESULT**: Job otomatis terhapus setelah selesai diproses

### 2. routes/console.php
- ✅ **UPDATE**: Scheduler dispatch job setiap 10 detik
- ✅ **RESULT**: Scheduler yang create job baru, bukan job sendiri

### 3. RunMonitorChecks.php
- ✅ **ALREADY**: Update `next_check_at` untuk prevent duplikasi
- ✅ **RESULT**: Monitor tidak di-dispatch berkali-kali

## 🧪 Test Hasil

```
BEFORE:  552 jobs in queue
PROCESS: 1 job executed
AFTER:   551 jobs in queue  ✅ -1 job (TERHAPUS!)
```

## 🚀 Cara Gunakan

### Development (Local):
```bash
# 1. Start queue worker
php artisan queue:work --queue=monitor-checks-priority,monitor-checks

# 2. Scheduler akan dispatch job otomatis (via schedule:run)
# Atau manual test:
php artisan monitor:check
```

### Production:
```bash
# 1. Setup cron (scheduler)
* * * * * cd /path/to/project && php artisan schedule:run >> /var/log/scheduler.log 2>&1

# 2. Setup supervisor (worker)
sudo supervisorctl start uptime-queue-worker
```

## 📊 Monitoring

```bash
# Check queue health
php artisan queue:monitor-health

# Or use batch script (Windows)
monitor-temporary-jobs.bat
```

## ✅ Expected Behavior

1. **Scheduler** dispatch job setiap 10 detik (atau sesuai monitor interval)
2. **Worker** process job dari queue
3. **Job** execute check dan create logs
4. **Job** AUTO-DELETE setelah selesai
5. **Queue** tetap clean (< 100 jobs)

## 🎉 Benefit

- ✅ Tidak ada job menumpuk
- ✅ Queue selalu clean
- ✅ Performa lebih baik
- ✅ Database lebih ringan
- ✅ Tidak ada risk overflow

## ⚠️ Important Notes

1. **WAJIB** jalankan queue worker (local atau via supervisor)
2. **WAJIB** setup cron untuk scheduler (production)
3. Job **TIDAK** auto-requeue lagi (ini adalah fitur, bukan bug!)
4. Scheduler yang handle dispatching job baru

## 🔍 Troubleshooting

**Q: Job tidak jalan otomatis?**
A: Check scheduler dan worker running

**Q: Queue size naik terus?**
A: Check ada auto-requeue logic yang tertinggal

**Q: Monitor tidak ter-check?**
A: Pastikan `next_check_at` ter-update dengan benar

---
📖 Dokumentasi lengkap: TEMPORARY_JOB_SYSTEM.md
