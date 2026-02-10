# Troubleshooting: Notifikasi Tidak Terkirim

## Masalah: Bot Tidak Mengirim Notifikasi Saat Ada Insiden

Jika bot tidak mengirim notifikasi saat ada insiden, periksa hal-hal berikut:

### 1. ✅ Notification Worker Harus Berjalan

**Cek apakah notification worker aktif:**
- Harus ada terminal window dengan judul "Notification Worker" yang terbuka
- Jika tidak ada, jalankan: `run_notification_worker.bat` ATAU `worker_manager.bat`

**Cara Menjalankan:**
```bash
# Opsi 1: Gunakan batch file
run_notification_worker.bat

# Opsi 2: Gunakan worker manager (recommended)
worker_manager.bat
# Pilih "2. Start Notification Worker"

# Opsi 3: Manual via artisan
php artisan worker:notifications
```

### 2. ✅ Monitor Harus Terhubung dengan Notification Channel

**Cek di UI:**
1. Buka halaman **Monitors**
2. Pilih monitor yang ingin diedit
3. Scroll ke section "**Notification Channels**"
4. **Centang minimal 1 channel** (Discord, Telegram, dll)
5. Klik **Save**

**Catatan:** Jika section "Notification Channels" tidak ada saat create/edit monitor, berarti frontend belum selesai diupdate. Gunakan cara manual di bawah.

**Cara Manual (via Database):**
```sql
-- Lihat available channels
SELECT id, name, type FROM notification_channels;

-- Update monitor dengan notification channels
-- Ganti [1,2] dengan ID channels yang ingin digunakan
UPDATE monitors 
SET notification_channels = '[1,2]'
WHERE id = 1;  -- Ganti dengan ID monitor Anda
```

### 3. ✅ Notification Channel Harus Sudah Dibuat

**Buat channel baru:**
1. Buka halaman **Notifications** di sidebar
2. Klik "**Add Channel**"
3. Pilih type (Discord, Telegram, Slack, Webhook)
4. Isi konfigurasi sesuai type
5. Klik "**Test Channel**" untuk memastikan berfungsi
6. Klik "**Create Channel**"

### 4. ✅ Cek Pengaturan Notify After Retries

Monitor punya setting `notify_after_retries` yang menentukan setelah berapa kali gagal baru kirim notifikasi.

**Default:** 2 (kirim notifikasi setelah 2x check gagal)

**Cara ubah:**
```sql
-- Set agar langsung notif saat pertama kali down
UPDATE monitors 
SET notify_after_retries = 1
WHERE id = 1;  -- Ganti dengan ID monitor Anda
```

### 5. ✅ Cek Queue Jobs

**Lihat apakah ada job notifications pending:**
```bash
# Lihat semua jobs di queue
php artisan queue:monitor notifications

# Lihat failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all
```

**Via Database:**
```sql
-- Lihat jobs yang pending
SELECT * FROM jobs WHERE queue = 'notifications';

-- Lihat failed jobs
SELECT * FROM failed_jobs ORDER BY failed_at DESC LIMIT 10;
```

### 6. ✅ Cek Logs Laravel

Buka `storage/logs/laravel.log` dan cari error terkait notifikasi:

```bash
# Windows PowerShell
Get-Content storage\logs\laravel.log -Tail 50 | Select-String "notification"

# Atau buka file langsung
notepad storage\logs\laravel.log
```

## Cara Test Notifikasi

### Test 1: Test Channel Langsung
1. Buka halaman **Notifications**
2. Klik tombol "**Test**" pada channel
3. Jika muncul error, perbaiki konfigurasi channel
4. Jika sukses, lanjut ke test 2

### Test 2: Simulasi Incident
1. Matikan service yang dimonitor (atau ubah URL jadi salah)
2. Tunggu monitor check berjalan (sesuai interval)
3. Cek halaman **Incidents** - harusnya ada incident baru
4. Cek bot (Discord/Telegram) - harusnya ada notifikasi masuk

### Test 3: Manual Dispatch Notification
```bash
php artisan tinker
```

```php
$monitor = App\Models\Monitor::find(1); // ID monitor Anda
$channel = App\Models\NotificationChannel::find(1); // ID channel Anda
App\Jobs\SendNotification::dispatch($monitor, 'test', $channel);
exit
```

Lalu cek notification worker - harusnya memproses job tersebut.

## Checklist Lengkap

- [ ] Notification worker berjalan (`run_notification_worker.bat`)
- [ ] Monitor checks worker berjalan (`run_monitor_worker.bat`)
- [ ] Monitor sudah punya notification_channels (cek database)
- [ ] Notification channel sudah dibuat dan di-test
- [ ] notify_after_retries sesuai (1 atau 2)
- [ ] Tidak ada failed jobs (`php artisan queue:failed`)
- [ ] Tidak ada error di `storage/logs/laravel.log`

## Solusi Cepat

**Jika masih belum berfungsi, restart semua:**

```bash
# 1. Stop semua workers (Ctrl+C di setiap terminal)

# 2. Clear queue
php artisan queue:clear

# 3. Clear cache
php artisan cache:clear
php artisan config:clear

# 4. Restart workers
worker_manager.bat
# Pilih "3. Start ALL Workers"
```

## FAQ

**Q: Kenapa harus ada 2 workers?**
A: Monitor checks worker untuk cek service, notification worker untuk kirim notifikasi. Keduanya harus berjalan.

**Q: Apakah queue harus database?**
A: Ya, saat ini menggunakan database queue (table `jobs`).

**Q: Berapa lama delay setelah incident sampai notifikasi terkirim?**
A: Biasanya 1-5 detik setelah incident dibuat, tergantung `notify_after_retries`.

**Q: Apakah notifikasi terkirim ke semua channels yang terhubung?**
A: Ya, jika monitor punya `notification_channels = [1,2,3]`, notif akan terkirim ke channel 1, 2, dan 3.

## Bantuan Lebih Lanjut

Jika masalah masih berlanjut, kumpulkan informasi berikut:

1. Screenshot error dari browser console (F12)
2. Output dari terminal notification worker
3. Isi file `storage/logs/laravel.log` (20 baris terakhir)
4. Result dari query: `SELECT id, name, notification_channels FROM monitors;`
5. Result dari query: `SELECT * FROM jobs WHERE queue = 'notifications' LIMIT 10;`
