# Monitor Priority System

## Overview
Sistem prioritas bertingkat untuk interval pengecekan monitor dengan 5 level. Semakin tinggi prioritas, semakin sering dilakukan pengecekan.

## Priority Levels

| Level | Label | Check Interval | Use Case |
|-------|-------|----------------|----------|
| 1 | Critical | 1 second | Layanan kritis yang memerlukan monitoring real-time |
| 2 | High | 1 minute | Layanan penting dengan kebutuhan monitoring tinggi |
| 3 | Medium | 5 minutes | Layanan standar dengan kebutuhan monitoring sedang |
| 4 | Low | 30 minutes | Layanan non-kritis dengan pemeriksaan berkala |
| 5 | Very Low | 1 hour | Layanan yang hanya memerlukan pemeriksaan berkala |

## Implementation Details

### Database
- Field baru `priority` ditambahkan ke tabel `monitors`
- Tipe: `tinyInteger` dengan default value `1` (Critical)
- Range: 1-5

### Backend Changes

1. **Model Monitor** (`app/Models/Monitor.php`)
   - Method `getCheckIntervalSeconds()` untuk menentukan interval berdasarkan priority
   - Accessor `getPriorityLabelAttribute()` untuk mendapatkan label yang mudah dibaca
   - Field `priority` ditambahkan ke `$fillable`

2. **ProcessMonitorCheck Job** (`app/Jobs/ProcessMonitorCheck.php`)
   - Menggunakan `getCheckIntervalSeconds()` untuk menghitung `next_check_at`
   - Interval dinamis berdasarkan priority level, bukan fixed interval

3. **MonitorController** (`app/Http/Controllers/Api/MonitorController.php`)
   - Validasi `priority` (integer 1-5) di `store()` dan `update()` methods

### Frontend Changes

1. **CreateMonitorView.vue**
   - Dropdown untuk memilih priority level
   - Menggantikan input manual interval_seconds
   - Default: Priority 1 (Critical)

2. **EditMonitorView.vue**
   - Dropdown untuk mengubah priority level
   - Tetap menggunakan field priority untuk edit monitor

## Usage

### Creating a Monitor with Priority
```javascript
// Frontend
const monitorData = {
  name: 'Production API',
  type: 'https',
  target: 'https://api.example.com',
  priority: 1  // Critical - checks every 1 second
}
```

### API Request
```json
POST /api/monitors
{
  "name": "Production API",
  "type": "https",
  "target": "https://api.example.com",
  "priority": 1
}
```

## Benefits

1. **Efisiensi Resource**: Monitor dengan prioritas rendah tidak membebani sistem
2. **Fleksibilitas**: Mudah menyesuaikan frekuensi monitoring per layanan
3. **User-Friendly**: Interface yang lebih mudah dipahami dibanding input detik manual
4. **Scalability**: Sistem dapat menangani lebih banyak monitor dengan distribusi priority yang baik

## Migration

Untuk menerapkan perubahan ini:

```bash
cd uptime-monitor
php artisan migrate
```

Migration file: `2026_01_19_124933_add_priority_to_monitors_table.php`

## Backward Compatibility

- Monitor existing yang tidak memiliki priority akan otomatis mendapat default value `1` (Critical)
- Field `interval_seconds` tetap ada di database untuk kompatibilitas
- System akan menggunakan `priority` untuk menentukan interval, bukan `interval_seconds`
