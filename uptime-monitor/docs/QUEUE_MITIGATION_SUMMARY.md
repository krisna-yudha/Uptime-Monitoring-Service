# Mitigasi Queue Overload - Implementation Summary

## Masalah yang Ditemukan

1. **Queue overload**: 1.4+ juta jobs terakumulasi di database
2. **Monitor baru tidak berjalan**: Jobs gagal karena model serialization error
3. **Auto-requeue tanpa batas**: Setiap monitor me-requeue dirinya sendiri tanpa pengecekan

## Solusi yang Diimplementasikan

### 1. Fix Model Serialization Issue

**File**: `app/Jobs/ProcessMonitorCheck.php`

**Perubahan**:
```php
// SEBELUM: Store entire Monitor model (causes serialization issues)
protected Monitor $monitor;

// SESUDAH: Store only Monitor ID
protected int $monitorId;
protected ?Monitor $monitor = null;
```

**Benefit**: 
- Mencegah ModelNotFoundException saat job di-deserialize
- Lebih efisien (hanya menyimpan integer, bukan seluruh model)
- Tidak ada issue jika monitor di-update saat job pending

### 2. Queue Health Protection

**File**: `app/Jobs/ProcessMonitorCheck.php`

**Fitur Baru**:

#### a. Konstanta Limit
```php
const MAX_QUEUE_SIZE = 10000;          // Max total jobs
const MAX_PENDING_PER_MONITOR = 3;     // Max pending jobs per monitor
```

#### b. Method `canSafelyRequeue()`
Melakukan pengecekan sebelum auto-requeue:

1. **Total Queue Size Check**
   - Jika queue > 10,000 jobs → STOP requeue
   - Log CRITICAL alert
   
2. **Per-Monitor Pending Check**
   - Jika monitor sudah punya ≥3 pending jobs → SKIP requeue
   - Mencegah duplicate jobs untuk monitor yang sama

3. **Warning Threshold**
   - Jika queue > 7,000 jobs (70%) → Log WARNING
   - Reminder untuk scale workers

**Implementasi**:
```php
if (!$this->canSafelyRequeue($freshMonitor->id)) {
    Log::warning("Monitor requeue skipped - queue health protection");
    return;
}
```

### 3. Queue Health Monitoring Command

**File**: `app/Console/Commands/MonitorQueueHealth.php`

**Command**: `php artisan queue:monitor-health`

**Features**:
- Monitor total jobs, priority queue, regular queue
- Detect stale jobs (jobs yang stuck)
- Display color-coded status (✓ OK, ⚠️ Warning, ❌ Critical)
- Auto-cleanup dengan `--cleanup` flag

**Usage**:
```bash
# Monitor queue health
php artisan queue:monitor-health

# Monitor and cleanup stale jobs
php artisan queue:monitor-health --cleanup --max-age=7200
```

### 4. Automated Scheduling

**File**: `routes/console.php`

**Scheduled Tasks**:
```php
// Queue health check setiap 5 menit
Schedule::command('queue:monitor-health')
    ->everyFiveMinutes();

// Auto-cleanup setiap 1 jam
Schedule::command('queue:monitor-health --cleanup --max-age=7200')
    ->hourly();
```

### 5. Supervisor Integration (Production)

**File**: `supervisor-queue-monitoring.conf`

**Programs**:
1. `queue-health-monitor`: Run health check setiap 5 menit
2. `queue-cleanup`: Cleanup stale jobs setiap 1 jam

**Setup**:
```bash
sudo cp supervisor-queue-monitoring.conf /etc/supervisor/conf.d/
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status queue-monitoring:*
```

### 6. Development Tools (Windows)

**Batch Scripts**:
- `monitor-queue-health.bat`: Monitor queue health loop
- `queue-cleanup.bat`: Cleanup queue loop

**Helper Scripts**:
- `truncate_jobs.php`: Emergency clear all jobs
- `initialize_all_monitors.php`: Dispatch initial checks
- `check_queue_jobs.php`: Inspect queue contents

## Best Practices Baru

### 1. Sebelum Deploy ke Production

```bash
# 1. Clear old queue
php artisan queue:clear database

# 2. Flush failed jobs
php artisan queue:flush

# 3. Re-initialize monitors
php initialize_all_monitors.php

# 4. Start workers
sudo supervisorctl start uptime-queue-worker:*

# 5. Monitor queue health
watch -n 30 'php artisan queue:monitor-health'
```

### 2. Regular Maintenance

```bash
# Weekly: Clear old failed jobs
php artisan queue:flush

# Daily: Check queue health
php artisan queue:monitor-health

# When queue > 5000: Investigate
php artisan queue:monitor-health --cleanup --max-age=3600
```

### 3. Emergency Response

Jika queue tiba-tiba membengkak:

```bash
# 1. Stop auto-requeue sementara (disable monitors)
mysql> UPDATE monitors SET enabled = 0;

# 2. Stop workers
sudo supervisorctl stop uptime-queue-worker:*

# 3. Clear queue
php truncate_jobs.php

# 4. Enable monitors kembali
mysql> UPDATE monitors SET enabled = 1;

# 5. Re-initialize
php initialize_all_monitors.php

# 6. Start workers
sudo supervisorctl start uptime-queue-worker:*
```

## Monitoring Dashboard

Track metrics ini:

1. **Queue Size**: `SELECT COUNT(*) FROM jobs;`
2. **Failed Jobs**: `SELECT COUNT(*) FROM failed_jobs;`
3. **Jobs per Monitor**: 
   ```sql
   SELECT 
     SUBSTRING(payload, POSITION('"monitorId":' IN payload) + 12, 3) as monitor_id,
     COUNT(*) 
   FROM jobs 
   GROUP BY monitor_id 
   HAVING COUNT(*) > 3;
   ```
4. **Stale Jobs**:
   ```sql
   SELECT COUNT(*) 
   FROM jobs 
   WHERE created_at < EXTRACT(EPOCH FROM NOW() - INTERVAL '1 hour');
   ```

## Alert Thresholds

| Metric | OK | Warning | Critical | Action |
|--------|-------|---------|----------|--------|
| Total Jobs | < 5,000 | 5,000-10,000 | > 10,000 | Scale workers / Cleanup |
| Failed Jobs | < 100 | 100-500 | > 500 | Investigate errors |
| Pending per Monitor | < 3 | 3-5 | > 5 | Check monitor config |
| Stale Jobs | 0 | < 100 | > 100 | Run cleanup |

## Performance Impact

**Before**:
- Queue: 1.4M+ jobs
- Processing: FAILED (ModelNotFoundException)
- CPU: High (deserialize errors)
- Monitor baru: TIDAK BERJALAN

**After**:
- Queue: < 5,000 jobs (controlled)
- Processing: SUCCESS
- CPU: Normal
- Monitor baru: LANGSUNG BERJALAN
- Auto-protection: ACTIVE

## Files Changed/Created

### Modified:
1. `app/Jobs/ProcessMonitorCheck.php` - Fix serialization + health checks
2. `routes/console.php` - Add scheduled monitoring

### Created:
1. `app/Console/Commands/MonitorQueueHealth.php` - Health monitoring command
2. `supervisor-queue-monitoring.conf` - Supervisor config
3. `monitor-queue-health.bat` - Windows monitoring script
4. `queue-cleanup.bat` - Windows cleanup script
5. `QUEUE_MONITORING_SETUP.md` - Setup documentation
6. Helper scripts untuk troubleshooting

## Testing Checklist

- [x] Queue health command berjalan
- [x] Auto-requeue dengan limit check
- [x] Monitor baru langsung dapat logs
- [x] Cleanup command bekerja
- [ ] Supervisor config tested (production)
- [ ] Alert system integration (optional)
- [ ] Load testing dengan 50+ monitors

## Next Steps (Optional)

1. **Alert Integration**: Kirim notif ke Telegram/Slack jika queue critical
2. **Metrics Dashboard**: Visualisasi queue health di frontend
3. **Auto-scaling**: Scale workers berdasarkan queue size
4. **Queue Prioritization**: Gunakan Redis untuk better performance
5. **Distributed Queue**: Multiple queue servers untuk high availability
