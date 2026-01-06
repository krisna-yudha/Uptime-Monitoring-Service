# Data Aggregation System - Dokumentasi

## 📊 Ringkasan Sistem

Sistem agregasi data dirancang untuk meringkas data monitoring mentah menjadi rata-rata per periode, sehingga:
- **Mengurangi ukuran database** hingga 90%+
- **Mempercepat query** untuk laporan dan grafik
- **Mempertahankan akurasi** data historis

## 🗂️ Struktur Data

### Level Agregasi

| Level | Granularity | Retention | Digunakan untuk |
|-------|-------------|-----------|-----------------|
| **Raw** | 1-10 detik | 7 hari | Realtime monitoring, debug |
| **Per Menit** | 1 menit | 30 hari | Grafik detail, analisis jangka pendek |
| **Per Jam** | 1 jam | 90 hari | Laporan harian, trend mingguan |
| **Per Hari** | 1 hari | 1 tahun+ | Statistik bulanan, laporan tahunan |

### Data yang Disimpan

Setiap agregasi menyimpan:
- Total checks
- Successful checks (UP)
- Failed checks (DOWN)
- Uptime percentage
- Response time (avg, min, max, median)
- Incident count
- Total downtime

## 🚀 Instalasi & Setup

### 1. Jalankan Migration

```bash
php artisan migrate
```

Ini akan membuat tabel `monitor_metrics_aggregated`

### 2. Jalankan Agregasi Manual (Testing)

**Agregasi per menit:**
```bash
php artisan metrics:aggregate --interval=minute
```

**Agregasi untuk tanggal tertentu:**
```bash
php artisan metrics:aggregate --interval=minute --date=2026-01-04
```

**Agregasi untuk monitor tertentu:**
```bash
php artisan metrics:aggregate --interval=minute --monitor=1
```

**Agregasi per jam:**
```bash
php artisan metrics:aggregate --interval=hour --date=2026-01-04
```

**Agregasi per hari:**
```bash
php artisan metrics:aggregate --interval=day --date=2026-01-04
```

### 3. Testing Cleanup (Dry Run)

Cek apa yang akan dihapus tanpa benar-benar menghapus:

```bash
php artisan metrics:cleanup --dry-run
```

Jalankan cleanup sesungguhnya:

```bash
php artisan metrics:cleanup
```

## ⏰ Schedulerkotomatis

Sistem akan berjalan otomatis via Laravel Scheduler:

```
┌─────────────┬──────────────────────────────────────┐
│ Waktu       │ Task                                  │
├─────────────┼──────────────────────────────────────┤
│ Setiap menit│ Agregasi data menit sebelumnya       │
│ Setiap jam  │ Agregasi data jam sebelumnya         │
│ 01:00 AM    │ Agregasi data hari sebelumnya        │
│ 02:00 AM    │ Cleanup data lama (retention policy) │
│ 03:00 AM    │ Cleanup logs (bulanan)               │
└─────────────┴──────────────────────────────────────┘
```

**Pastikan scheduler berjalan:**

Windows (Task Scheduler):
```bash
# Tambahkan ke Task Scheduler dengan action:
cd C:\xampp\htdocs\prjctmgng\uptime-monitor && php artisan schedule:run
# Interval: Setiap 1 menit
```

Linux (crontab):
```bash
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

## 📈 Retention Policy

Data akan dihapus otomatis berdasarkan usia:

| Data Type | Retention Period |
|-----------|------------------|
| Raw Checks | 7 hari |
| Raw Logs | 30 hari |
| Minute Aggregates | 30 hari |
| Hour Aggregates | 90 hari |
| Day Aggregates | 365 hari (1 tahun) |

## 🔍 Query Data Agregasi

### Mengambil Data Agregasi via Model

```php
use App\Models\MonitorMetricAggregated;

// Get minute aggregates for last 24 hours
$minuteMetrics = MonitorMetricAggregated::where('monitor_id', 1)
    ->where('interval', 'minute')
    ->where('period_start', '>=', now()->subDay())
    ->orderBy('period_start')
    ->get();

// Get hourly aggregates for last week
$hourlyMetrics = MonitorMetricAggregated::where('monitor_id', 1)
    ->interval('hour')
    ->recent(168) // 7 days * 24 hours
    ->get();

// Get daily aggregates for last month
$dailyMetrics = MonitorMetricAggregated::where('monitor_id', 1)
    ->interval('day')
    ->dateRange(now()->subMonth(), now())
    ->get();
```

### Statistik Aggregate

```php
// Average uptime for last 30 days (daily aggregates)
$avgUptime = MonitorMetricAggregated::where('monitor_id', 1)
    ->interval('day')
    ->recent(720)
    ->avg('uptime_percentage');

// Total incidents in last week
$incidents = MonitorMetricAggregated::where('monitor_id', 1)
    ->interval('hour')
    ->recent(168)
    ->sum('incident_count');
```

## 💡 Best Practices

### 1. Initial Setup (Data Historis)

Jika Anda sudah punya data lama, agregasi manual:

```bash
# Agregasi 7 hari terakhir (per menit)
for i in {0..6}; do
    date=$(date -d "$i days ago" +%Y-%m-%d)
    php artisan metrics:aggregate --interval=minute --date=$date
done

# Agregasi 30 hari terakhir (per jam)
for i in {0..29}; do
    date=$(date -d "$i days ago" +%Y-%m-%d)
    php artisan metrics:aggregate --interval=hour --date=$date
done

# Agregasi 90 hari terakhir (per hari)
for i in {0..89}; do
    date=$(date -d "$i days ago" +%Y-%m-%d)
    php artisan metrics:aggregate --interval=day --date=$date
done
```

### 2. Monitoring Agregasi

Cek jumlah data:

```bash
php artisan tinker
```

```php
use App\Models\MonitorCheck;
use App\Models\MonitorMetricAggregated;

// Raw data
MonitorCheck::count();

// Aggregated data
MonitorMetricAggregated::interval('minute')->count();
MonitorMetricAggregated::interval('hour')->count();
MonitorMetricAggregated::interval('day')->count();
```

### 3. Backup Sebelum Cleanup

```bash
# Backup database sebelum cleanup pertama kali
mysqldump -u root uptime_monitor > backup_before_cleanup.sql
```

## ⚙️ Konfigurasi

### Ubah Retention Policy

Edit file `app/Console/Commands/CleanupOldMonitorData.php`:

```php
protected $retentionPolicy = [
    'raw_checks' => 14,     // Ubah dari 7 ke 14 hari
    'raw_logs' => 60,       // Ubah dari 30 ke 60 hari
    'minute_aggregates' => 60,
    'hour_aggregates' => 180,
    'day_aggregates' => 730, // 2 tahun
];
```

## 📊 Estimasi Penghematan Database

### Contoh Skenario:
- **Monitors:** 50
- **Interval:** 10 detik
- **Checks per hari per monitor:** 8,640
- **Total checks per hari:** 432,000

### Tanpa Agregasi (1 tahun):
```
432,000 checks/day × 365 days = 157,680,000 records
Estimasi: ~15 GB
```

### Dengan Agregasi (1 tahun):
```
Raw (7 hari):     432,000 × 7 = 3,024,000 records
Minute (30 hari): 72,000 × 30 = 2,160,000 records  
Hour (90 hari):   3,000 × 90 = 270,000 records
Day (365 hari):   50 × 365 = 18,250 records
Total: ~5,472,250 records
Estimasi: ~500 MB
```

**Penghematan: ~97%** 🎉

## 🐛 Troubleshooting

### Agregasi tidak berjalan otomatis

Cek scheduler:
```bash
php artisan schedule:list
```

Test manual:
```bash
php artisan schedule:run
```

### Data agregasi tidak akurat

Re-run agregasi untuk periode tertentu:
```bash
php artisan metrics:aggregate --interval=minute --date=2026-01-04
```

### Database masih besar

Cek data yang belum di-cleanup:
```bash
php artisan metrics:cleanup --dry-run
```

## 📞 Support

Jika ada masalah atau pertanyaan, hubungi tim development atau buat issue di repository.
