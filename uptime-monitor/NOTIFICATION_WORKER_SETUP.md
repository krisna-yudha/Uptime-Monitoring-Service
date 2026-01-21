# Notification Worker - Supervisor Setup Guide

## Overview
Notification worker adalah service yang mengirim alert ke Discord, Telegram, Slack, dan channel lainnya saat terjadi incident (service down).

## File Configuration

### 1. supervisor-notification-worker.conf
File konfigurasi supervisor untuk production server.

### 2. install-notification-worker.sh
Script otomatis untuk instalasi dan setup.

## Instalasi di Server (Recommended)

### Metode 1: Automatic Installation (Paling Mudah)

```bash
# Upload file ke server
cd /var/www/uptime-monitor

# Berikan permission execute
chmod +x install-notification-worker.sh

# Jalankan script instalasi
sudo ./install-notification-worker.sh
```

Script akan otomatis:
- Install supervisor jika belum ada
- Detect web server user (www-data, nginx, apache)
- Generate config dengan path yang benar
- Setup log directory dengan permission yang tepat
- Start notification worker
- Show status

### Metode 2: Manual Installation

```bash
# 1. Copy file config ke supervisor directory
sudo cp supervisor-notification-worker.conf /etc/supervisor/conf.d/uptime-notification-worker.conf

# 2. Edit file dan sesuaikan path
sudo nano /etc/supervisor/conf.d/uptime-notification-worker.conf
# Ubah:
# - /var/www/uptime-monitor → path project Anda
# - user=www-data → user web server Anda

# 3. Create log directory
mkdir -p storage/logs
sudo chown -R www-data:www-data storage
sudo chmod -R 775 storage

# 4. Reload supervisor
sudo supervisorctl reread
sudo supervisorctl update

# 5. Start worker
sudo supervisorctl start uptime-notification-worker:*

# 6. Check status
sudo supervisorctl status uptime-notification-worker:*
```

## Management Commands

### Start/Stop/Restart
```bash
# Start notification worker
sudo supervisorctl start uptime-notification-worker:*

# Stop notification worker
sudo supervisorctl stop uptime-notification-worker:*

# Restart notification worker
sudo supervisorctl restart uptime-notification-worker:*

# Check status
sudo supervisorctl status uptime-notification-worker:*
```

### View Logs
```bash
# View log file
sudo tail -f /var/www/uptime-monitor/storage/logs/worker-notifications.log

# View via supervisor
sudo supervisorctl tail -f uptime-notification-worker:uptime-notification-worker_00

# Last 100 lines
sudo supervisorctl tail uptime-notification-worker:uptime-notification-worker_00
```

### Update After Code Changes
```bash
# Setelah update code, restart worker
cd /var/www/uptime-monitor
git pull
composer install --no-dev --optimize-autoloader
php artisan config:cache
php artisan route:cache

# Restart notification worker
sudo supervisorctl restart uptime-notification-worker:*
```

## Configuration Details

### Queue Settings
```ini
--queue=notifications     # Listen to notifications queue only
--sleep=0                 # No sleep, process immediately (real-time)
--tries=3                 # Retry 3 times if notification fails
--timeout=120             # Max 2 minutes per notification
--max-jobs=1000          # Restart after 1000 jobs to prevent memory leak
```

### Process Settings
```ini
numprocs=1               # 1 worker process (sufficient for most cases)
autostart=true           # Auto-start on boot
autorestart=unexpected   # Auto-restart if crashes
stopwaitsecs=60         # Wait 60s for graceful shutdown
```

## Troubleshooting

### Worker Not Starting
```bash
# Check supervisor logs
sudo tail -f /var/log/supervisor/supervisord.log

# Check worker logs
sudo tail -f /var/www/uptime-monitor/storage/logs/worker-notifications.log

# Check Laravel logs
sudo tail -f /var/www/uptime-monitor/storage/logs/laravel.log

# Verify permissions
ls -la /var/www/uptime-monitor/storage/logs
```

### Notifications Not Sending
```bash
# Check if worker is running
sudo supervisorctl status uptime-notification-worker:*

# Check queue has jobs
cd /var/www/uptime-monitor
php artisan queue:failed

# Test notification manually
php artisan tinker
>>> $monitor = App\Models\Monitor::first();
>>> App\Jobs\SendNotification::dispatch($monitor, 'test');
>>> exit

# Watch worker logs
sudo tail -f storage/logs/worker-notifications.log
```

### Restart Supervisor Service
```bash
# If supervisor itself has issues
sudo systemctl restart supervisor

# Check supervisor status
sudo systemctl status supervisor

# Enable on boot
sudo systemctl enable supervisor
```

## Performance Tuning

### High Volume Notifications
Jika banyak notifikasi per detik, increase numprocs:

```ini
numprocs=3  # Run 3 worker processes
```

### Memory Issues
Jika worker consume banyak memory:

```ini
--max-jobs=500          # Restart more frequently
--timeout=60           # Lower timeout
```

### Slow Notifications
Jika notifikasi lambat terkirim:

```ini
numprocs=2             # Add more workers
--sleep=0              # Already set to 0 (no delay)
```

## Monitoring

### Check Worker Health
```bash
# Quick status check
sudo supervisorctl status uptime-notification-worker:*

# Should show: RUNNING   pid 12345, uptime 1:23:45

# If shows FATAL or EXITED, check logs
sudo supervisorctl tail uptime-notification-worker:uptime-notification-worker_00
```

### Monitor Queue Size
```bash
cd /var/www/uptime-monitor

# Check pending jobs in notifications queue
php artisan queue:work database --queue=notifications --once --verbose
```

## Integration with Monitor Checks

Notification worker bekerja bersama dengan monitor checks worker:

1. **Monitor Checks Worker** → Detect service down → Create incident
2. **Incident Created** → Dispatch notification job to queue
3. **Notification Worker** → Process queue → Send to Discord/Telegram/Slack

Kedua worker harus running untuk sistem bekerja sempurna.

## Production Checklist

- [ ] Supervisor installed dan running
- [ ] Notification worker config deployed
- [ ] Worker started dan status RUNNING
- [ ] Logs directory writable oleh web server user
- [ ] Test notification terkirim
- [ ] Monitor checks worker juga running
- [ ] Database queue connection configured di .env
- [ ] Notification channels configured (Discord/Telegram/Slack)

## Support

Jika ada masalah:
1. Check logs di `storage/logs/worker-notifications.log`
2. Check supervisor status
3. Verify notification channels configured
4. Test manual notification dengan artisan tinker
