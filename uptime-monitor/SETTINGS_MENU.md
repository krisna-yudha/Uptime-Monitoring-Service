# Settings Menu - Documentation

## 📋 Overview

Menu Settings telah ditambahkan untuk mengatur agregasi data dan retention policy secara visual melalui UI.

## ✨ Fitur

### 1. **Data Aggregation Control**
- **Auto Aggregation Toggle**: Enable/disable agregasi otomatis
- **Aggregation Intervals**: Pilih interval mana yang aktif
  - ✅ Per Minute (setiap menit)
  - ✅ Per Hour (setiap jam)  
  - ✅ Per Day (setiap hari jam 01:00)

### 2. **Data Retention Settings**
Atur berapa lama data disimpan untuk setiap level:

| Data Type | Default | Configurable Range |
|-----------|---------|-------------------|
| **Raw Checks** | 7 days | 1-365 days/weeks/months |
| **Raw Logs** | 30 days | 1-365 days/weeks/months |
| **Minute Aggregates** | 30 days | 1-365 days/weeks/months |
| **Hour Aggregates** | 90 days | 1-365 days/weeks/months |
| **Day Aggregates** | 1 year | 1-3650 days/months/years |

### 3. **Manual Actions**

#### Run Aggregation
- Pilih interval: minute / hour / day
- Pilih tanggal spesifik
- Klik "Run Aggregation"
- Hasil: Jumlah period yang diagregasi

#### Run Cleanup
- ✅ **Dry Run**: Preview saja, tidak delete data
- ❌ **Real Cleanup**: Benar-benar delete data lama
- Hasil: Summary berapa data yang dihapus

## 🎯 Cara Menggunakan

### Setup Awal Retention Policy

1. Buka menu **Settings** dari sidebar
2. Scroll ke **Data Retention** section
3. Atur retention untuk setiap level:
   - Raw Checks: Misalnya 7 days (untuk debugging)
   - Raw Logs: 30 days (untuk audit trail)
   - Minute Aggregates: 30 days (grafik detail)
   - Hour Aggregates: 90 days (laporan mingguan)
   - Day Aggregates: 1-2 years (laporan tahunan)
4. Klik **Save Settings**

### Test Agregasi Manual

1. Scroll ke **Manual Actions**
2. Di card "Run Aggregation":
   - Pilih interval: `minute`
   - Pilih date: `2026-01-05`
   - Klik **Run Aggregation**
3. Tunggu hasil agregasi

### Preview Cleanup (Aman)

1. Di card "Run Cleanup":
   - ✅ Centang **Dry Run**
   - Klik **Preview Cleanup**
2. Lihat preview data yang akan dihapus
3. Jika OK, uncheck Dry Run dan run cleanup sebenarnya

## 🔧 Backend

### API Endpoints

```
GET    /api/settings           - Get current settings
PUT    /api/settings           - Save settings
POST   /api/settings/aggregate - Run manual aggregation
POST   /api/settings/cleanup   - Run manual cleanup
```

### Artisan Commands

```bash
# Manual aggregation
php artisan metrics:aggregate --interval=minute --date=2026-01-05
php artisan metrics:aggregate --interval=hour --date=2026-01-04
php artisan metrics:aggregate --interval=day --date=2026-01-01

# Manual cleanup
php artisan metrics:cleanup --dry-run  # Preview
php artisan metrics:cleanup            # Real cleanup
```

## 📊 Database Impact

### Estimasi Penghematan dengan Retention Policy

**Skenario:**
- 50 monitors @ 10s interval = 432,000 checks/day
- Retention: 7d raw, 30d minute, 90d hour, 365d day

**Tanpa Agregasi (1 tahun):**
```
432,000 × 365 = 157,680,000 records (~15 GB)
```

**Dengan Agregasi (1 tahun):**
```
Raw (7d):      3,024,000
Minute (30d):  2,160,000
Hour (90d):      270,000
Day (365d):       18,250
Total:         5,472,250 records (~500 MB)

Penghematan: 97% ✨
```

## ⚠️ Perhatian

1. **Backup dulu** sebelum cleanup pertama kali
2. **Test dengan dry-run** sebelum cleanup real
3. **Retention terlalu pendek** = data historis hilang
4. **Retention terlalu panjang** = database membengkak
5. **Auto aggregate** harus enabled agar scheduler jalan

## 🎨 UI Features

- ✅ Real-time toggle switches
- ✅ Visual retention cards dengan color coding
- ✅ Inline value preview
- ✅ Manual action feedback
- ✅ Success/error indicators
- ✅ Responsive design (mobile-friendly)

## 📱 Access

Menu Settings bisa diakses:
1. Dari sidebar: klik icon ⚙️ **Settings**
2. Langsung via URL: `/settings`
3. Requires authentication

Selamat menggunakan! 🎉
