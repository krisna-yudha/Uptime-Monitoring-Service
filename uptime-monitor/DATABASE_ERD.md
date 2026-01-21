# Entity Relationship Diagram (ERD)
## Uptime Monitoring System Database

### 📊 Database Structure Overview

```
┌─────────────────────────────────────────────────────────────────────┐
│                    UPTIME MONITORING SYSTEM ERD                     │
└─────────────────────────────────────────────────────────────────────┘
```

---

## 🗂️ Tables & Relationships

### 1️⃣ **users** (Pengguna Sistem)
**Primary Key:** `id`

| Field | Type | Description |
|-------|------|-------------|
| id | BIGINT UNSIGNED | Primary Key |
| name | VARCHAR(255) | Nama pengguna |
| email | VARCHAR(255) | Email (unique) |
| password | VARCHAR(255) | Password (hashed) |
| role | VARCHAR(50) | Role: 'admin' / 'user' |
| email_verified_at | TIMESTAMP | Waktu verifikasi email |
| remember_token | VARCHAR(100) | Token remember me |
| created_at | TIMESTAMP | Waktu dibuat |
| updated_at | TIMESTAMP | Waktu diupdate |

**Relationships:**
- `1 → N` monitors (created_by)
- `1 → N` monitors (actual_created_by)
- `1 → N` notification_channels (created_by)
- `1 → N` incidents (acknowledged_by)

---

### 2️⃣ **monitors** (Monitor Utama)
**Primary Key:** `id`

| Field | Type | Description |
|-------|------|-------------|
| id | BIGINT UNSIGNED | Primary Key |
| name | VARCHAR(255) | Nama monitor |
| group_name | VARCHAR(255) | Nama grup (nullable) |
| group_description | TEXT | Deskripsi grup (nullable) |
| group_config | JSON | Konfigurasi grup (nullable) |
| type | VARCHAR(50) | Tipe: http/https/tcp/ping/keyword/push |
| target | TEXT | Target URL/IP/Host |
| icon_url | VARCHAR(500) | URL icon monitor (nullable) |
| port | INTEGER | Port untuk TCP monitoring (nullable) |
| config | JSON | Konfigurasi spesifik monitor |
| interval_seconds | INTEGER | Interval pengecekan (detik) - default: 1 |
| **priority** | TINYINT | **Level prioritas (1-5)** |
| timeout_ms | INTEGER | Timeout (milliseconds) - default: 5000 |
| retries | INTEGER | Jumlah retry - default: 3 |
| notify_after_retries | INTEGER | Notif setelah retry ke-N |
| consecutive_failures | INTEGER | Kegagalan berturut-turut |
| enabled | BOOLEAN | Status aktif/nonaktif - default: true |
| is_public | BOOLEAN | Public/private - default: false |
| tags | JSON | Tags untuk kategorisasi (nullable) |
| created_by | BIGINT UNSIGNED | FK → users.id (nullable, cascade null) |
| actual_created_by | BIGINT UNSIGNED | FK → users.id (nullable, cascade null) |
| heartbeat_key | VARCHAR(255) | Key untuk heartbeat (unique, nullable) |
| last_status | VARCHAR(50) | Status terakhir: up/down/invalid/validating/unknown |
| last_error | TEXT | Error terakhir (nullable) |
| last_checked_at | TIMESTAMP | Waktu cek terakhir (nullable) |
| next_check_at | TIMESTAMP | Waktu cek berikutnya (nullable) |
| pause_until | TIMESTAMP | Dijeda sampai waktu ini (nullable) |
| ssl_cert_expiry | TIMESTAMP | Waktu expire SSL (nullable) |
| ssl_cert_issuer | VARCHAR(255) | Issuer SSL (nullable) |
| ssl_checked_at | TIMESTAMP | Terakhir cek SSL (nullable) |
| notification_channels | JSON | Array ID channel notifikasi |
| last_notification_sent | TIMESTAMP | Waktu notif terakhir (nullable) |
| last_critical_alert_sent | TIMESTAMP | Waktu alert kritis terakhir (nullable) |
| error_message | TEXT | Pesan error terkini (nullable) |
| last_error_at | TIMESTAMP | Waktu error terakhir (nullable) |
| created_at | TIMESTAMP | Waktu dibuat |
| updated_at | TIMESTAMP | Waktu diupdate |

**Relationships:**
- `N → 1` users (created_by) - ON DELETE SET NULL
- `N → 1` users (actual_created_by) - ON DELETE SET NULL
- `1 → N` monitor_checks
- `1 → N` incidents
- `1 → N` monitor_metrics
- `1 → N` monitor_metrics_aggregated
- `1 → N` monitoring_logs

**Priority Levels:**
```
1 = Critical (1 second)    - Monitoring real-time
2 = High (1 minute)        - Monitoring penting
3 = Medium (5 minutes)     - Monitoring standar
4 = Low (30 minutes)       - Monitoring berkala
5 = Very Low (1 hour)      - Monitoring minimal
```

---

### 3️⃣ **monitor_checks** (Hasil Pengecekan)
**Primary Key:** `id`

| Field | Type | Description |
|-------|------|-------------|
| id | BIGINT UNSIGNED | Primary Key |
| monitor_id | BIGINT UNSIGNED | FK → monitors.id |
| checked_at | TIMESTAMP | Waktu pengecekan |
| status | VARCHAR(50) | Status: up/down/invalid |
| latency_ms | INTEGER | Latency (ms) - nullable |
| http_status | INTEGER | HTTP status code - nullable |
| error_message | TEXT | Pesan error - nullable |
| response_size | INTEGER | Ukuran response (bytes) - nullable |
| region | VARCHAR(50) | Region check - default: 'local' |
| meta | JSON | Metadata tambahan - nullable |
| created_at | TIMESTAMP | Waktu dibuat |
| updated_at | TIMESTAMP | Waktu diupdate |

**Indexes:**
- `monitor_id, checked_at`
- `status`
- `checked_at`

**Relationships:**
- `N → 1` monitors (monitor_id) - ON DELETE CASCADE

---

### 4️⃣ **incidents** (Insiden/Downtime)
**Primary Key:** `id`

| Field | Type | Description |
|-------|------|-------------|
| id | BIGINT UNSIGNED | Primary Key |
| monitor_id | BIGINT UNSIGNED | FK → monitors.id |
| started_at | TIMESTAMP | Waktu mulai incident |
| ended_at | TIMESTAMP | Waktu selesai incident - nullable |
| resolved | BOOLEAN | Status resolved - default: false |
| status | VARCHAR(50) | Status: open/resolved/acknowledged |
| alert_status | VARCHAR(50) | Status alert - nullable |
| acknowledged_at | TIMESTAMP | Waktu di-acknowledge - nullable |
| acknowledged_by | BIGINT UNSIGNED | FK → users.id - nullable |
| alert_log | JSON | Log alert yang dikirim - nullable |
| description | TEXT | Deskripsi incident - nullable |
| created_at | TIMESTAMP | Waktu dibuat |
| updated_at | TIMESTAMP | Waktu diupdate |

**Indexes:**
- `monitor_id, started_at`
- `resolved`

**Relationships:**
- `N → 1` monitors (monitor_id) - ON DELETE CASCADE
- `N → 1` users (acknowledged_by) - ON DELETE SET NULL

---

### 5️⃣ **monitor_metrics** (Metrik Real-time)
**Primary Key:** `id`

| Field | Type | Description |
|-------|------|-------------|
| id | BIGINT UNSIGNED | Primary Key |
| monitor_id | BIGINT UNSIGNED | FK → monitors.id |
| period_start | TIMESTAMP | Awal periode |
| period_end | TIMESTAMP | Akhir periode |
| avg_response_time_ms | DECIMAL(10,2) | Avg response time (ms) - nullable |
| p95_response_time_ms | DECIMAL(10,2) | P95 response time (ms) - nullable |
| uptime_seconds | INTEGER | Total waktu uptime (detik) - default: 0 |
| downtime_seconds | INTEGER | Total waktu downtime (detik) - default: 0 |
| checks_count | INTEGER | Jumlah pengecekan - default: 0 |
| created_at | TIMESTAMP | Waktu dibuat |
| updated_at | TIMESTAMP | Waktu diupdate |

**Indexes:**
- `monitor_id, period_start`

**Relationships:**
- `N → 1` monitors (monitor_id) - ON DELETE CASCADE

---

### 6️⃣ **monitor_metrics_aggregated** (Metrik Agregasi)
**Primary Key:** `id`

| Field | Type | Description |
|-------|------|-------------|
| id | BIGINT UNSIGNED | Primary Key |
| monitor_id | BIGINT UNSIGNED | FK → monitors.id |
| interval | ENUM | Interval: 'minute'/'hour'/'day' |
| period_start | TIMESTAMP | Awal periode |
| period_end | TIMESTAMP | Akhir periode |
| total_checks | INTEGER | Total checks - default: 0 |
| successful_checks | INTEGER | Checks sukses - default: 0 |
| failed_checks | INTEGER | Checks gagal - default: 0 |
| uptime_percentage | DECIMAL(5,2) | Persentase uptime - nullable |
| avg_response_time | DECIMAL(10,3) | Avg response time - nullable |
| min_response_time | DECIMAL(10,3) | Min response time - nullable |
| max_response_time | DECIMAL(10,3) | Max response time - nullable |
| median_response_time | DECIMAL(10,3) | Median response time - nullable |
| incident_count | INTEGER | Jumlah incident - default: 0 |
| total_downtime_seconds | DECIMAL(15,2) | Total downtime (detik) - nullable |
| created_at | TIMESTAMP | Waktu dibuat |
| updated_at | TIMESTAMP | Waktu diupdate |

**Indexes:**
- `monitor_id, interval, period_start`
- UNIQUE: `monitor_id, interval, period_start`

**Relationships:**
- `N → 1` monitors (monitor_id) - ON DELETE CASCADE

---

### 7️⃣ **monitoring_logs** (Log Aktivitas)
**Primary Key:** `id`

| Field | Type | Description |
|-------|------|-------------|
| id | BIGINT UNSIGNED | Primary Key |
| monitor_id | BIGINT UNSIGNED | FK → monitors.id |
| event_type | VARCHAR(100) | Tipe event |
| status | VARCHAR(50) | Status - nullable |
| log_data | JSON | Data log - nullable |
| response_time | DECIMAL(10,3) | Response time - nullable |
| error_message | TEXT | Pesan error - nullable |
| logged_at | TIMESTAMP | Waktu log |
| created_at | TIMESTAMP | Waktu dibuat |
| updated_at | TIMESTAMP | Waktu diupdate |

**Indexes:**
- `monitor_id, logged_at`
- `event_type`
- `logged_at`

**Relationships:**
- `N → 1` monitors (monitor_id) - ON DELETE CASCADE

---

### 8️⃣ **notification_channels** (Channel Notifikasi)
**Primary Key:** `id`

| Field | Type | Description |
|-------|------|-------------|
| id | BIGINT UNSIGNED | Primary Key |
| name | VARCHAR(255) | Nama channel |
| type | VARCHAR(50) | Tipe: email/slack/telegram/discord/webhook |
| config | JSON | Konfigurasi channel |
| created_by | BIGINT UNSIGNED | FK → users.id |
| is_enabled | BOOLEAN | Status aktif - default: true |
| created_at | TIMESTAMP | Waktu dibuat |
| updated_at | TIMESTAMP | Waktu diupdate |

**Relationships:**
- `N → 1` users (created_by) - ON DELETE CASCADE
- `N → N` monitors (via notification_channels JSON field)

---

## 📐 Visual ERD Diagram

```
┌──────────────┐
│    users     │
├──────────────┤
│ id (PK)      │◄─────────────────┐
│ name         │                  │
│ email        │                  │
│ password     │                  │
│ role         │                  │
└──────────────┘                  │
       ▲                          │
       │ created_by               │ actual_created_by
       │                          │
       │                          │
┌──────┴───────────────────────────┴─────────────────┐
│                  monitors                          │
├────────────────────────────────────────────────────┤
│ id (PK)                                            │
│ name                                               │
│ group_name                                         │
│ type                                               │
│ target                                             │
│ priority ⭐ (1-5)                                  │
│ interval_seconds                                   │
│ timeout_ms                                         │
│ enabled                                            │
│ last_status                                        │
│ notification_channels (JSON)                       │
│ created_by (FK) ────────────────────────┐          │
│ actual_created_by (FK) ─────────────────┼──────┐   │
└────────────────────────────────────────────────────┘
       │                                         │   │
       │                                         │   │
       ├──────────────┬──────────────┬──────────┼───┼───────────┐
       │              │              │          │   │           │
       ▼              ▼              ▼          ▼   │           ▼
┌─────────────┐ ┌─────────────┐ ┌──────────────┐   │    ┌──────────────┐
│monitor_     │ │  incidents  │ │monitor_      │   │    │ monitoring_  │
│  checks     │ │             │ │  metrics     │   │    │    logs      │
├─────────────┤ ├─────────────┤ ├──────────────┤   │    ├──────────────┤
│ id (PK)     │ │ id (PK)     │ │ id (PK)      │   │    │ id (PK)      │
│ monitor_id  │ │ monitor_id  │ │ monitor_id   │   │    │ monitor_id   │
│   (FK)      │ │   (FK)      │ │   (FK)       │   │    │   (FK)       │
│ checked_at  │ │ started_at  │ │ period_start │   │    │ event_type   │
│ status      │ │ ended_at    │ │ period_end   │   │    │ log_data     │
│ latency_ms  │ │ resolved    │ │ avg_response │   │    │ logged_at    │
│ http_status │ │ status      │ │ uptime_sec   │   │    └──────────────┘
│ error_msg   │ │ acknowledged│ │ downtime_sec │   │
└─────────────┘ │   _by (FK)──┼─┘              │   │
                └─────────────┘                 │   │
                       │                        │   │
                       │                        ▼   │
                       │                 ┌──────────┴────────┐
                       │                 │monitor_metrics_   │
                       │                 │   aggregated      │
                       │                 ├───────────────────┤
                       │                 │ id (PK)           │
                       └─────────────────┤ monitor_id (FK)   │
                                         │ interval (ENUM)   │
                                         │ period_start      │
                                         │ total_checks      │
                                         │ uptime_%          │
                                         │ avg_response      │
                                         └───────────────────┘

┌──────────────────────┐
│ notification_        │
│    channels          │
├──────────────────────┤
│ id (PK)              │
│ name                 │
│ type                 │
│ config (JSON)        │
│ created_by (FK) ─────┼──► users.id
│ is_enabled           │
└──────────────────────┘
         ▲
         │
         │ Many-to-Many via
         │ monitors.notification_channels (JSON)
         │
    monitors.notification_channels
```

---

## 🔗 Relationship Summary

### One-to-Many (1:N)
1. **users** → **monitors** (created_by)
2. **users** → **monitors** (actual_created_by)
3. **users** → **notification_channels** (created_by)
4. **users** → **incidents** (acknowledged_by)
5. **monitors** → **monitor_checks**
6. **monitors** → **incidents**
7. **monitors** → **monitor_metrics**
8. **monitors** → **monitor_metrics_aggregated**
9. **monitors** → **monitoring_logs**

### Many-to-Many (N:N)
1. **monitors** ↔ **notification_channels** (via JSON field `notification_channels`)

---

## 🔑 Key Features

### Priority System
Monitor memiliki 5 level prioritas yang menentukan interval pengecekan:
- **Priority 1**: 1 detik (Critical)
- **Priority 2**: 60 detik (High)
- **Priority 3**: 5 menit (Medium)
- **Priority 4**: 30 menit (Low)
- **Priority 5**: 1 jam (Very Low)

### Data Aggregation
- **monitor_metrics**: Metrik real-time
- **monitor_metrics_aggregated**: Metrik teragregasi per menit/jam/hari

### Monitoring Types
- HTTP/HTTPS
- TCP/Port
- Ping
- Keyword
- Push/Heartbeat

### Notification System
- Multi-channel (Email, Slack, Telegram, Discord, Webhook)
- Configurable per monitor via JSON field

---

## 📊 Index Strategy

### High-Performance Indexes
1. **monitor_checks**: `(monitor_id, checked_at)` - untuk query historical data
2. **incidents**: `(monitor_id, started_at)` - untuk incident timeline
3. **monitor_metrics_aggregated**: `(monitor_id, interval, period_start)` UNIQUE
4. **monitoring_logs**: `(monitor_id, logged_at)` - untuk log retrieval

### Query Optimization
- Cascade delete untuk menjaga referential integrity
- Set null untuk user deletions (preserve monitor history)
- JSON fields untuk fleksibilitas konfigurasi

---

## 🗃️ Storage Considerations

### Data Retention Policy
Berdasarkan **monitor_metrics_aggregated.interval**:
- **Raw checks**: 7-30 hari
- **Minute aggregates**: 30 hari
- **Hour aggregates**: 90 hari
- **Day aggregates**: 1-3 tahun

### Cleanup Strategy
- Raw data dibersihkan sesuai retention policy
- Aggregated data disimpan lebih lama untuk historical analysis

---

**Created:** 2026-01-19  
**Version:** 1.0  
**System:** Uptime Monitoring Platform
