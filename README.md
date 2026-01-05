# 🔍 Uptime Monitoring Service

Real-time service monitoring system dengan notifikasi otomatis melalui Discord, Telegram, dan Slack.

---

## 📂 Struktur Proyek

Proyek ini terdiri dari dua komponen utama:

### 🎨 **Frontend (FE)** - Vue.js Application
📁 **Lokasi**: [`/uptime-frontend`](./uptime-frontend)

- Framework: Vue.js 3 + Vite
- User Interface untuk monitoring dashboard
- Manajemen monitor, incident, dan notification channels
- Real-time updates dan responsive design

📚 **Dokumentasi**: [Frontend README](./uptime-frontend/README.md)

**Quick Start Frontend:**
```bash
cd uptime-frontend
npm install
npm run dev
```
Frontend akan berjalan di `http://localhost:5173`

---

### ⚙️ **Backend (BE)** - Laravel Application
📁 **Lokasi**: [`/uptime-monitor`](./uptime-monitor)

- Framework: Laravel 11
- RESTful API untuk monitoring system
- Background workers untuk checks dan notifications
- Database: PostgreSQL

📚 **Dokumentasi**: [Backend README](./uptime-monitor/README.md)

**Quick Start Backend:**
```bash
cd uptime-monitor
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```
Backend API akan berjalan di `http://localhost:8000`

---

## 🚀 Quick Start - Jalankan Semua Layanan

Untuk menjalankan **seluruh sistem** (Backend + Workers + Frontend):

```bash
cd uptime-monitor
start-monitoring.bat
```

Kemudian buka browser di: **http://localhost:5173**

> **Note**: Script `start-monitoring.bat` akan otomatis menjalankan Laravel server, queue workers, dan frontend development server.

---

## 📋 Requirements

### Frontend Requirements
- Node.js 20.19+ atau 22.12+
- npm atau yarn

### Backend Requirements
- PHP 8.2+
- PostgreSQL 14+
- Composer
- Node.js 20.19+ atau 22.12+ (untuk asset compilation)

---

## 🌟 Features

- ✅ Real-time Monitoring (HTTP, PING, PORT, SSL, Heartbeat)
- ✅ Auto Notifications (Discord, Telegram, Slack)
- ✅ Incident Management
- ✅ Dashboard dengan Statistics & Charts
- ✅ Responsive UI (Mobile-friendly)
- ✅ JWT Authentication
- ✅ Background Workers untuk monitoring dan notifications

---

## 📖 Dokumentasi Lengkap

### Frontend Documentation
- [Frontend README](./uptime-frontend/README.md) - Overview dan quick start
- [Frontend Documentation](./uptime-frontend/FRONTEND-DOCUMENTATION.md) - Dokumentasi lengkap

### Backend Documentation
- [Backend README](./uptime-monitor/README.md) - Overview dan quick start
- [Quick Start Guide](./uptime-monitor/QUICK_START.md) - Panduan memulai
- [Production Deployment](./uptime-monitor/PRODUCTION_DEPLOYMENT.md) - Setup production
- [Architecture](./uptime-monitor/ARCHITECTURE.md) - Arsitektur sistem
- [Developer Reference](./uptime-monitor/DEVELOPER_QUICK_REFERENCE.md) - Referensi developer
- [Workers Documentation](./uptime-monitor/WORKERS_README.md) - Dokumentasi workers
- [Notification System](./uptime-monitor/NOTIFICATION_SYSTEM_READY.md) - Sistem notifikasi
- [Troubleshooting](./uptime-monitor/TROUBLESHOOTING_NOTIFICATIONS.md) - Troubleshooting

---

## 🔧 Development Workflow

1. **Start Backend** (Terminal 1):
   ```bash
   cd uptime-monitor
   php artisan serve
   ```

2. **Start Workers** (Terminal 2):
   ```bash
   cd uptime-monitor
   php artisan worker:monitor-checks
   ```

3. **Start Notification Worker** (Terminal 3):
   ```bash
   cd uptime-monitor
   php artisan worker:notifications
   ```

4. **Start Frontend** (Terminal 4):
   ```bash
   cd uptime-frontend
   npm run dev
   ```

**Atau gunakan script otomatis**:
```bash
cd uptime-monitor
start-monitoring.bat
```

---

## 🏗️ Project Architecture

```
Uptime-Monitoring-Service/
│
├── uptime-frontend/          # 🎨 FRONTEND (Vue.js)
│   ├── src/                  # Source code
│   ├── public/               # Static assets
│   ├── package.json          # Dependencies
│   └── README.md             # Frontend docs
│
├── uptime-monitor/           # ⚙️ BACKEND (Laravel)
│   ├── app/                  # Application code
│   ├── database/             # Migrations & seeders
│   ├── routes/               # API routes
│   ├── composer.json         # Dependencies
│   └── README.md             # Backend docs
│
└── terraform/                # Infrastructure as Code
```

---

## 🤝 Integrasi FE & BE

Frontend berkomunikasi dengan Backend melalui **RESTful API**:

- **Base URL**: `http://localhost:8000/api`
- **Authentication**: JWT Token
- **Endpoints**: Monitor, Incident, Notification Channels, etc.

Frontend otomatis tersambung ke Backend jika kedua service berjalan di default ports.

---

## 🐛 Troubleshooting

### CORS Errors
Jika ada error CORS, pastikan Backend sudah dikonfigurasi CORS di `config/cors.php`.

### Connection Refused
- Pastikan Backend berjalan di `http://localhost:8000`
- Pastikan Frontend berjalan di `http://localhost:5173`

### Workers Not Running
Gunakan script untuk restart workers:
```bash
cd uptime-monitor
start-monitoring.bat
```

---

## 📝 License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

---

## 🙏 Credits

Built with Laravel 11 and Vue.js 3

---

**🎯 Status: Production Ready**

Sistem monitoring lengkap dan siap digunakan! 🚀
