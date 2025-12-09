# 🔍 Uptime Monitor - Laravel Backend

Real-time service monitoring system with automatic bot notifications (Discord, Telegram, Slack).

## ⚡ Quick Start

```batch
# Start all services (Laravel + Workers + Frontend)
start-monitoring.bat

# Open browser
http://localhost:5173
```

**That's it! System is ready to use.** 🎉

For detailed guide, see [QUICK_START.md](QUICK_START.md)

---

## 🌟 Features

- ✅ **Real-time Monitoring** - HTTP, PING, PORT checks (down to 1-second intervals)
- ✅ **Auto Notifications** - Discord, Telegram, Slack bot integration
- ✅ **Incident Management** - Track, acknowledge, resolve incidents
- ✅ **Queue System** - Background workers for monitoring and notifications
- ✅ **Modern UI** - Vue.js 3 frontend with real-time updates
- ✅ **PostgreSQL** - Robust data storage with JSONB support

---

## 📋 Requirements

- PHP 8.2+
- PostgreSQL 14+
- Composer
- Node.js 18+ (for frontend)

---

## 🚀 Installation

### 1. Clone & Install Dependencies
```bash
composer install
cp .env.example .env
php artisan key:generate
```

### 2. Configure Database
Edit `.env`:
```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=uptime_monitor
DB_USERNAME=postgres
DB_PASSWORD=your_password
```

### 3. Migrate Database
```bash
php artisan migrate
```

### 4. Start Services
```batch
start-monitoring.bat
```

---

## 🔧 Usage

### Create Notification Channel
1. Open http://localhost:5173/notifications
2. Click "Add Channel"
3. Configure Discord/Telegram/Slack webhook
4. Test with "Test" button

### Create Monitor
1. Open http://localhost:5173/monitors
2. Click "Create Monitor"
3. Fill in details (name, type, target)
4. **Select notification channels**
5. Set interval (1-3600 seconds)
6. Save

### Bot Auto-Sends Notifications
When service goes down → Incident created → Bot notifies automatically! 🤖

---

## 📂 Project Structure

```
uptime-monitor/
├── app/
│   ├── Console/Commands/
│   │   ├── RunMonitorChecks.php      # Monitor checks worker
│   │   └── RunNotificationWorker.php # Notification worker
│   ├── Jobs/
│   │   ├── ProcessMonitorCheck.php   # Check service health
│   │   └── SendNotification.php      # Send to bot channels
│   ├── Models/
│   │   ├── Monitor.php
│   │   ├── Incident.php
│   │   └── NotificationChannel.php
│   └── Http/Controllers/
│       ├── MonitorController.php
│       ├── IncidentController.php
│       └── NotificationChannelController.php
├── database/migrations/
├── routes/
│   └── web.php
├── start-monitoring.bat              # AUTO START all services
├── stop-monitoring.bat               # STOP all services
├── worker_manager.bat                # Interactive worker manager
└── QUICK_START.md                    # Quick start guide
```

---

## 🎯 Workers

System uses 2 dedicated workers:

### 1. Monitor Checks Worker
**Queue**: `monitor-checks`  
**Function**: Check service health every interval  
**Command**: `php artisan worker:monitor-checks`

### 2. Notification Worker
**Queue**: `notifications`  
**Function**: Send alerts to Discord/Telegram/Slack  
**Command**: `php artisan worker:notifications`

Both start automatically via `start-monitoring.bat`

---

## 📊 Monitoring Types

| Type | Description | Example |
|------|-------------|---------|
| HTTP | Check HTTP/HTTPS endpoint | https://example.com |
| PING | ICMP ping check | 8.8.8.8 |
| PORT | TCP port check | 192.168.1.1:3306 |

---

## 🔔 Notification Channels

Supported notification types:
- **Discord** - Webhook URL
- **Telegram** - Bot token + Chat ID
- **Slack** - Webhook URL
- **Generic Webhook** - Custom HTTP endpoint

---

## 🛠️ Helpful Scripts

| Script | Purpose |
|--------|---------|
| `start-monitoring.bat` | Start ALL services |
| `stop-monitoring.bat` | Stop ALL services |
| `start_all_workers.bat` | Start workers only |
| `worker_manager.bat` | Interactive worker menu |
| `trigger_incident.bat` | Test notifications |
| `setup_notifications.php` | Link monitors to channels |

---

## 📖 Documentation

- [QUICK_START.md](QUICK_START.md) - Quick start guide
- [NOTIFICATION_SYSTEM_READY.md](NOTIFICATION_SYSTEM_READY.md) - Notification system docs
- [TROUBLESHOOTING_NOTIFICATIONS.md](TROUBLESHOOTING_NOTIFICATIONS.md) - Troubleshooting
- [WORKERS_README.md](WORKERS_README.md) - Worker documentation

---

## 🐛 Troubleshooting

### Notifications not working?
```bash
# Check workers running
tasklist | findstr "php"

# Check logs
Get-Content storage\logs\laravel.log -Tail 50

# Verify monitor has channels
php setup_notifications.php
```

### Worker crashed?
```batch
start-monitoring.bat
```

---

## 📝 License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

---

## 🙏 Credits

Built with Laravel 11 and Vue.js 3

---

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
