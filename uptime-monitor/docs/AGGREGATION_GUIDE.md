# Data Aggregation Guide

## Overview

Sistem agregasi data dirancang untuk mengurangi ukuran database dan meningkatkan performa query dengan menggabungkan raw check data menjadi summary statistics pada berbagai interval waktu.

## Cara Kerja Agregasi

### 🔄 Automatic Scheduled Aggregation

Agregasi berjalan otomatis melalui Laravel Scheduler:

1. **Minute Aggregation** - Setiap menit (`:00`, `:01`, `:02`, dst)
   - Mengagregasi data dari **menit yang baru saja selesai**
   - Contoh: Jam 10:05 → agregasi data 10:04:00 - 10:04:59
   - Command: `php artisan metrics:aggregate --interval=minute`

2. **Hour Aggregation** - Setiap jam (01:00, 02:00, dst)
   - Mengagregasi data dari **jam yang baru saja selesai**
   - Contoh: Jam 14:00 → agregasi data 13:00:00 - 13:59:59
   - Command: `php artisan metrics:aggregate --interval=hour`

3. **Day Aggregation** - Setiap hari jam 01:00 AM
   - Mengagregasi data dari **hari kemarin**
   - Contoh: 6 Jan 01:00 → agregasi data 5 Jan 00:00:00 - 23:59:59
   - Command: `php artisan metrics:aggregate --interval=day`

### 📊 Data yang Dihitung

Untuk setiap periode agregasi, sistem menghitung:

#### Availability Metrics
- **Total Checks**: Jumlah total pengecekan
- **Successful Checks**: Jumlah check dengan status UP
- **Failed Checks**: Jumlah check dengan status DOWN
- **Uptime Percentage**: (Successful / Total) × 100

#### Performance Metrics
- **Average Response Time**: Rata-rata latency
- **Min Response Time**: Latency tercepat
- **Max Response Time**: Latency terlambat
- **Median Response Time**: Median dari semua latency

#### Reliability Metrics
- **Incident Count**: Jumlah incident yang terjadi
- **Total Downtime**: Estimasi total waktu down (dalam detik)

### 🔍 Duplicate Prevention

Sistem memiliki built-in duplicate prevention:
- Sebelum mengagregasi, check apakah period sudah pernah di-aggregate
- Jika sudah ada, skip untuk avoid data duplication
- Menggunakan unique constraint: `monitor_id + interval + period_start`

## Manual Aggregation

### Agregasi Data Historis

Jika ingin mengagregasi data dari tanggal tertentu:

```bash
# Agregasi semua menit di tanggal 5 Januari 2026
php artisan metrics:aggregate --interval=minute --date=2026-01-05

# Agregasi semua jam di tanggal 5 Januari 2026
php artisan metrics:aggregate --interval=hour --date=2026-01-05

# Agregasi satu hari penuh (5 Januari 2026)
php artisan metrics:aggregate --interval=day --date=2026-01-05
```

### Agregasi Monitor Spesifik

```bash
# Hanya agregasi monitor ID 1
php artisan metrics:aggregate --interval=minute --monitor=1

# Agregasi monitor 1 untuk tanggal tertentu
php artisan metrics:aggregate --interval=minute --monitor=1 --date=2026-01-05
```

## Retention Policy Integration

Agregasi bekerja bersama dengan cleanup policy:

### Default Retention (30 hari untuk semua)
- **Raw Checks**: 30 hari → kemudian dihapus
- **Raw Logs**: 30 hari → kemudian dihapus  
- **Minute Aggregates**: 30 hari → kemudian dihapus
- **Hour Aggregates**: 30 hari → kemudian dihapus
- **Day Aggregates**: 30 hari → kemudian dihapus

### Strategi yang Direkomendasikan

Untuk database yang lebih optimal, pertimbangkan cascade retention:

```php
'retention' => [
    'rawChecks' => 7,           // 7 hari (raw data terbaru)
    'rawLogs' => 30,            // 30 hari
    'minuteAggregates' => 30,   // 30 hari (detail tinggi)
    'hourAggregates' => 90,     // 90 hari (detail medium)
    'dayAggregates' => 365,     // 1 tahun (long-term trends)
]
```

Dengan strategi ini:
- Detail tinggi tersedia untuk 30 hari terakhir
- Trend bulanan tersedia untuk 90 hari
- Historical trends tersedia untuk 1 tahun

## Database Structure

### Table: monitor_metrics_aggregated

```sql
CREATE TABLE monitor_metrics_aggregated (
    id BIGINT PRIMARY KEY,
    monitor_id BIGINT,
    interval ENUM('minute', 'hour', 'day'),
    period_start TIMESTAMP,
    period_end TIMESTAMP,
    
    -- Availability
    total_checks INT,
    successful_checks INT,
    failed_checks INT,
    uptime_percentage DECIMAL(5,2),
    
    -- Performance  
    avg_response_time DECIMAL(8,3),
    min_response_time DECIMAL(8,3),
    max_response_time DECIMAL(8,3),
    median_response_time DECIMAL(8,3),
    
    -- Reliability
    incident_count INT,
    total_downtime_seconds DECIMAL(10,2),
    
    UNIQUE KEY (monitor_id, interval, period_start)
);
```

## Query Examples

### Get Minute-by-Minute Data for Today

```php
$metrics = MonitorMetricAggregated::where('monitor_id', $monitorId)
    ->where('interval', 'minute')
    ->whereBetween('period_start', [now()->startOfDay(), now()->endOfDay()])
    ->orderBy('period_start')
    ->get();
```

### Get Hourly Uptime for Last 7 Days

```php
$metrics = MonitorMetricAggregated::where('monitor_id', $monitorId)
    ->where('interval', 'hour')
    ->where('period_start', '>=', now()->subDays(7))
    ->orderBy('period_start')
    ->get(['period_start', 'uptime_percentage', 'avg_response_time']);
```

### Get Daily Summary for Last Month

```php
$metrics = MonitorMetricAggregated::where('monitor_id', $monitorId)
    ->where('interval', 'day')
    ->where('period_start', '>=', now()->subMonth())
    ->orderBy('period_start')
    ->get();
```

## Performance Benefits

### Before Aggregation
- Query 1 hari data (check setiap 10 detik) = **8,640 rows**
- Query 1 bulan = **259,200 rows** 
- Query 1 tahun = **3,110,400 rows** per monitor

### After Aggregation
- Query 1 hari (hourly aggregates) = **24 rows**
- Query 1 bulan (daily aggregates) = **30 rows**
- Query 1 tahun (daily aggregates) = **365 rows**

**Pengurangan data: ~99.98%** untuk long-term queries!

## Troubleshooting

### Missing Aggregated Data

Jika data agregasi tidak muncul:

1. Check scheduler berjalan:
```bash
# Windows
php artisan schedule:work

# Or dengan task scheduler/cron
php artisan schedule:run
```

2. Check log errors:
```bash
tail -f storage/logs/laravel.log
```

3. Manual run untuk test:
```bash
php artisan metrics:aggregate --interval=minute -v
```

### Duplicate Data

Jika terjadi duplicate aggregation:

1. Check unique constraint di database
2. Hapus duplicate manual:
```sql
DELETE t1 FROM monitor_metrics_aggregated t1
INNER JOIN monitor_metrics_aggregated t2 
WHERE t1.id > t2.id 
  AND t1.monitor_id = t2.monitor_id
  AND t1.interval = t2.interval
  AND t1.period_start = t2.period_start;
```

### Slow Aggregation

Jika agregasi lambat:

1. Check indexes pada table `monitor_checks`:
```sql
CREATE INDEX idx_monitor_checked_at ON monitor_checks(monitor_id, checked_at);
```

2. Pertimbangkan batch processing untuk many monitors
3. Run aggregation di off-peak hours

## Best Practices

1. **Always run scheduler** - Pastikan Laravel scheduler berjalan 24/7
2. **Monitor aggregation logs** - Check untuk errors atau skipped periods
3. **Backfill historical data** - Run manual aggregation untuk data lama
4. **Adjust retention** - Sesuaikan retention policy dengan kebutuhan storage
5. **Use appropriate interval** - Pilih interval yang sesuai untuk query Anda
   - Real-time dashboard → minute aggregates
   - Daily reports → hour aggregates  
   - Long-term trends → day aggregates

## Integration with Frontend

Frontend dapat query aggregated data untuk performa lebih baik:

```javascript
// Get hourly data for chart (last 24 hours)
const response = await api.get('/api/monitors/1/metrics', {
  params: {
    interval: 'hour',
    from: moment().subtract(24, 'hours'),
    to: moment()
  }
});

// Get daily data for long-term chart (last 30 days)
const response = await api.get('/api/monitors/1/metrics', {
  params: {
    interval: 'day',
    from: moment().subtract(30, 'days'),
    to: moment()
  }
});
```

## Summary

✅ **Agregasi sudah proper dengan perbaikan:**
- Scheduled runs mengagregasi period yang baru selesai (bukan data lama)
- Manual runs dengan `--date` tetap bisa aggregate historical data
- Duplicate prevention dengan check existing data
- Performance optimal dengan indexing yang tepat
- Retention policy terintegrasi dengan cleanup command

🎯 **Next Steps:**
1. Pastikan Laravel scheduler running (`php artisan schedule:work`)
2. Monitor log untuk memastikan agregasi berjalan
3. Adjust retention policy sesuai kebutuhan
4. Implement API endpoint untuk frontend query aggregated data
