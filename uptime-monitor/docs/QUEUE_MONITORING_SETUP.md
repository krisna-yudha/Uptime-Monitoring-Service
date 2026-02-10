# Queue Health Monitoring - Supervisor Setup Guide

## File Konfigurasi Supervisor

File: `supervisor-queue-monitoring.conf`

Berisi 2 program:
1. **queue-health-monitor**: Monitor queue health setiap 5 menit
2. **queue-cleanup**: Cleanup stale jobs setiap 1 jam

## Setup di Production Server

### 1. Copy Config ke Supervisor Directory

```bash
# Copy config file
sudo cp /var/www/monitoring/Uptime-Monitoring-Service/uptime-monitor/supervisor-queue-monitoring.conf \
    /etc/supervisor/conf.d/queue-monitoring.conf

# Verify file
cat /etc/supervisor/conf.d/queue-monitoring.conf
```

### 2. Update & Reload Supervisor

```bash
# Reread config files
sudo supervisorctl reread

# Update supervisor with new config
sudo supervisorctl update

# Verify programs started
sudo supervisorctl status | grep queue
```

Output yang diharapkan:
```
queue-cleanup                    RUNNING   pid 12345, uptime 0:00:05
queue-health-monitor             RUNNING   pid 12346, uptime 0:00:05
```

### 3. Management Commands

```bash
# Start all queue monitoring
sudo supervisorctl start queue-monitoring:*

# Stop all queue monitoring
sudo supervisorctl stop queue-monitoring:*

# Restart all queue monitoring
sudo supervisorctl restart queue-monitoring:*

# View logs
sudo tail -f /var/log/supervisor/queue-health-monitor.log
sudo tail -f /var/log/supervisor/queue-cleanup.log

# Check status
sudo supervisorctl status queue-monitoring:*
```

### 4. Manual Testing (Before Supervisor)

Test command dulu sebelum add ke supervisor:

```bash
# Test queue health monitoring
cd /var/www/monitoring/Uptime-Monitoring-Service/uptime-monitor
php artisan queue:monitor-health

# Test cleanup
php artisan queue:monitor-health --cleanup --max-age=7200
```

## Untuk Development (Windows/XAMPP)

Karena supervisor tidak tersedia di Windows, gunakan batch script atau task scheduler:

### Opsi 1: Manual via Batch Script

Buat file `monitor-queue-health.bat`:

```batch
@echo off
:loop
php artisan queue:monitor-health
timeout /t 300 /nobreak
goto loop
```

Run di terminal terpisah:
```batch
cd c:\xampp\htdocs\prjctmgng\uptime-monitor
monitor-queue-health.bat
```

### Opsi 2: Windows Task Scheduler

1. Buka Task Scheduler
2. Create Basic Task:
   - Name: "Queue Health Monitor"
   - Trigger: Repeat every 5 minutes
   - Action: Start a program
   - Program: `C:\xampp\php\php.exe`
   - Arguments: `artisan queue:monitor-health`
   - Start in: `c:\xampp\htdocs\prjctmgng\uptime-monitor`

## Monitoring & Troubleshooting

### Check Logs

```bash
# Health monitor log
sudo tail -100 /var/log/supervisor/queue-health-monitor.log

# Cleanup log
sudo tail -100 /var/log/supervisor/queue-cleanup.log

# Error logs
sudo tail -50 /var/log/supervisor/queue-health-monitor-error.log
sudo tail -50 /var/log/supervisor/queue-cleanup-error.log
```

### Check Laravel Logs

```bash
# Application logs
tail -100 /var/www/monitoring/Uptime-Monitoring-Service/uptime-monitor/storage/logs/laravel.log | grep -i "queue health"
```

### Common Issues

**Program not starting:**
```bash
# Check supervisor logs
sudo tail -50 /var/log/supervisor/supervisord.log

# Verify permissions
ls -la /var/www/monitoring/Uptime-Monitoring-Service/uptime-monitor/artisan
```

**High CPU usage:**
- Reduce check frequency (increase sleep time)
- Optimize database queries
- Consider caching queue counts

## Configuration Tuning

Edit `/etc/supervisor/conf.d/queue-monitoring.conf`:

```ini
# Change monitoring interval (default 300s = 5 minutes)
command=bash -c 'while true; do php artisan queue:monitor-health && sleep 600; done'

# Change cleanup interval (default 3600s = 1 hour)
command=bash -c 'while true; do php artisan queue:monitor-health --cleanup --max-age=7200 && sleep 7200; done'

# Change stale job threshold (default 7200s = 2 hours)
--max-age=3600  # 1 hour
--max-age=14400 # 4 hours
```

After changes:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl restart queue-monitoring:*
```

## Integration dengan Alert System

Bisa ditambahkan alert ke Telegram/Slack jika queue critical:

Edit `app/Console/Commands/MonitorQueueHealth.php` untuk mengirim notifikasi.

## Verification

Pastikan system berjalan dengan baik:

```bash
# 1. Check supervisor status
sudo supervisorctl status

# 2. Monitor queue size
watch -n 30 'mysql -u root -p -e "SELECT COUNT(*) as total_jobs FROM uptime_monitor.jobs"'

# 3. Check if cleanup works
# Before cleanup
echo "Jobs before: $(mysql -u root -p uptime_monitor -e 'SELECT COUNT(*) FROM jobs' -sN)"

# Wait for cleanup to run (1 hour) or trigger manually
php artisan queue:monitor-health --cleanup --max-age=7200

# After cleanup
echo "Jobs after: $(mysql -u root -p uptime_monitor -e 'SELECT COUNT(*) FROM jobs' -sN)"
```
