-- Script untuk menghubungkan monitors dengan notification channels
-- Jalankan script ini di PostgreSQL

-- 1. Lihat notification channels yang tersedia
SELECT id, name, type FROM notification_channels ORDER BY id;

-- 2. Update SEMUA monitors agar terhubung dengan SEMUA notification channels
-- (Ini akan membuat semua monitor kirim notif ke semua channels)
UPDATE monitors 
SET notification_channels = (
    SELECT json_agg(id)::text::json
    FROM notification_channels
)
WHERE notification_channels IS NULL OR notification_channels = '[]'::json;

-- 3. Verifikasi hasilnya
SELECT 
    id, 
    name, 
    notification_channels,
    notify_after_retries,
    last_status
FROM monitors
ORDER BY id;

-- ATAU jika ingin set manual per monitor:
-- Ganti [1] dengan array ID channels yang diinginkan, contoh: [1,2,3]

-- Update monitor ID 1 dengan channel ID 1
-- UPDATE monitors 
-- SET notification_channels = '[1]'::json
-- WHERE id = 1;

-- Update monitor ID 2 dengan channel ID 1 dan 2
-- UPDATE monitors 
-- SET notification_channels = '[1,2]'::json
-- WHERE id = 2;

-- 4. PENTING: Set notify_after_retries ke 1 untuk instant notification
UPDATE monitors 
SET notify_after_retries = 1
WHERE notify_after_retries > 1 OR notify_after_retries IS NULL;

-- 5. Verifikasi konfigurasi final
SELECT 
    m.id,
    m.name,
    m.notification_channels,
    m.notify_after_retries,
    m.consecutive_failures,
    m.last_status,
    (
        SELECT string_agg(nc.name || ' (' || nc.type || ')', ', ')
        FROM notification_channels nc
        WHERE nc.id = ANY(
            SELECT jsonb_array_elements_text(m.notification_channels::jsonb)::int
        )
    ) as linked_channels
FROM monitors m
ORDER BY m.id;

-- 6. Test: Lihat incident terakhir dan apakah sudah ada notifikasi yang dikirim
SELECT 
    i.id as incident_id,
    i.monitor_id,
    m.name as monitor_name,
    i.status,
    i.started_at,
    m.notification_channels,
    m.consecutive_failures,
    m.last_notification_sent
FROM incidents i
JOIN monitors m ON i.monitor_id = m.id
WHERE i.status != 'resolved'
ORDER BY i.started_at DESC
LIMIT 10;
