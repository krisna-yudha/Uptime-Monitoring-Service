# 🔔 Fitur Enable/Disable Notification Channels

## Cara Menggunakan

### 1. Enable/Disable Channel

**Via UI:**
1. Buka http://localhost:5173/notifications
2. Setiap channel memiliki status indicator (hijau = enabled, merah = disabled)
3. Klik tombol **"Disable"** untuk menonaktifkan channel
4. Klik tombol **"Enable"** untuk mengaktifkan kembali

**Visual Indicators:**
- ✅ **Green dot** = Channel aktif, akan menerima notifikasi
- 🔴 **Red dot** = Channel nonaktif, tidak akan menerima notifikasi
- **DISABLED badge** = Label pada channel yang nonaktif
- **Grayed out card** = Channel nonaktif akan tampak redup

### 2. Saat Membuat Channel Baru

Checkbox **"Enable this notification channel"** tersedia di form:
- ✅ **Checked** (default) = Channel langsung aktif setelah dibuat
- ⬜ **Unchecked** = Channel dibuat tapi tidak aktif

### 3. Behavior Sistem

**Channel Enabled:**
- ✅ Menerima notifikasi dari monitors yang terhubung
- ✅ Tombol "Test" bisa digunakan
- ✅ Akan muncul di log notifikasi

**Channel Disabled:**
- ❌ **TIDAK** menerima notifikasi meskipun terhubung ke monitor
- ❌ Tombol "Test" disabled (tidak bisa diklik)
- ⚠️ Monitor tetap terhubung, hanya tidak mengirim notif

### 4. Use Case

**Skenario 1: Maintenance Discord Bot**
```
1. Disable Discord channel
2. Bot tidak akan menerima spam notifikasi
3. Monitor tetap berjalan dan log tersimpan
4. Enable lagi setelah maintenance selesai
```

**Skenario 2: Testing Notification Setup**
```
1. Buat channel baru dengan status disabled
2. Edit dan configure dengan benar
3. Test channel untuk verifikasi
4. Enable setelah yakin setup benar
```

**Skenario 3: Rotasi Bot**
```
1. Disable Discord channel (weekend)
2. Enable Telegram channel
3. Toggle sesuai jadwal
4. Semua monitor tidak perlu diubah
```

### 5. Database & Backend

**Field di Database:**
- `is_enabled` (boolean, default: true)

**Filter Otomatis:**
- SendNotification job hanya kirim ke channel yang `is_enabled = true`
- Query: `WHERE is_enabled = true`

**API Endpoint:**
```
POST /api/notification-channels/{id}/toggle
```

Response:
```json
{
  "success": true,
  "message": "Notification channel enabled successfully",
  "data": {
    "id": 1,
    "name": "gentong",
    "type": "discord",
    "is_enabled": true,
    ...
  }
}
```

---

## Keuntungan Fitur Ini

✅ **No Need to Delete:** Tidak perlu hapus channel, cukup disable sementara  
✅ **Quick Toggle:** Toggle on/off dengan 1 klik  
✅ **No Monitor Reconfiguration:** Monitor tidak perlu diubah  
✅ **Maintenance Friendly:** Disable saat maintenance bot  
✅ **Testing Safe:** Test channel baru tanpa spam production  

---

## Testing

1. **Disable channel Discord:**
   - Klik "Disable" pada channel
   - Status indicator berubah merah
   - Card menjadi redup

2. **Trigger incident:**
   - Matikan salah satu service
   - Monitor akan detect DOWN
   - Incident tercatat di database
   - ❌ **Notifikasi TIDAK dikirim** ke Discord

3. **Enable kembali:**
   - Klik "Enable"
   - Status indicator hijau
   - Incident berikutnya akan kirim notif

4. **Verifikasi di log:**
```powershell
# Cek log Laravel
Get-Content storage\logs\laravel.log -Tail 20

# Akan muncul log skip channel disabled
```

---

**Fitur sudah siap digunakan!** 🎉
