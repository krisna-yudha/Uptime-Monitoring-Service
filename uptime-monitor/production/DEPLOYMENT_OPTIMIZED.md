# 🚀 Production Deployment - Optimized Setup

Panduan deployment production dengan konfigurasi optimized untuk response cepat dan auto-cleanup.

## 📋 Prerequisites

- Ubuntu/Debian server
- PHP 8.2+
- PostgreSQL 14+ atau MySQL 8+
- Redis (recommended) atau Database queue
- Nginx atau Apache
- Supervisor
- Cron

---

## 🔧 Step-by-Step Deployment

### 1. Clone & Setup Project

```bash
# Clone repository
cd /var/www
git clone <your-repo-url> uptime-monitor
cd uptime-monitor

# Install dependencies
composer install --optimize-autoloader --no-dev

# Setup environment
cp .env.example .env
php artisan key:generate

# Edit .env
nano .env
```

**.env Configuration:**
```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

# Database
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=uptime_monitor
DB_USERNAME=postgres
DB_PASSWORD=your_secure_password

# Queue (pilih salah satu)
QUEUE_CONNECTION=redis     # Recommended untuk production
# QUEUE_CONNECTION=database  # Alternative jika tanpa Redis

# Redis (jika pakai Redis queue)
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Logging
LOG_CHANNEL=daily
LOG_LEVEL=warning
```

### 2. Database Migration

```bash
# Run migrations
php artisan migrate --force

# Verify
php artisan db:show
```

### 3. Setup Cron (Laravel Scheduler)

```bash
# Edit crontab
sudo crontab -e -u www-data

# Add this line:
* * * * * cd /var/www/uptime-monitor && php artisan schedule:run >> /dev/null 2>&1
```

**Atau copy file cron yang sudah ada:**
```bash
sudo cp production/cron/uptime-monitor /etc/cron.d/uptime-monitor
sudo chown root:root /etc/cron.d/uptime-monitor
sudo chmod 644 /etc/cron.d/uptime-monitor

# Reload cron
sudo service cron reload

# Verify
sudo cat /etc/cron.d/uptime-monitor
```

### 4. Setup Supervisor (Workers)

**Install Supervisor:**
```bash
sudo apt-get update
sudo apt-get install supervisor
```

**Copy & Edit Config:**
```bash
# Copy config file
sudo cp production/supervisor/uptime-monitor.conf /etc/supervisor/conf.d/uptime-monitor.conf

# Edit paths jika berbeda
sudo nano /etc/supervisor/conf.d/uptime-monitor.conf

# Update paths:
# - /var/www/uptime-monitor (project path)
# - /var/log/supervisor (log path)
# - www-data (user)
```

**Reload Supervisor:**
```bash
# Reread config files
sudo supervisorctl reread

# Update supervisor with new config
sudo supervisorctl update

# Start all workers
sudo supervisorctl start uptime-monitor:*

# Verify status
sudo supervisorctl status uptime-monitor:*
```

**Expected Output:**
```
uptime-monitor:uptime-priority-queue_00    RUNNING   pid 12345, uptime 0:00:05
uptime-monitor:uptime-priority-queue_01    RUNNING   pid 12346, uptime 0:00:05
uptime-monitor:uptime-regular-queue_00     RUNNING   pid 12347, uptime 0:00:05
uptime-monitor:uptime-regular-queue_01     RUNNING   pid 12348, uptime 0:00:05
uptime-monitor:uptime-regular-queue_02     RUNNING   pid 12349, uptime 0:00:05
uptime-monitor:uptime-regular-queue_03     RUNNING   pid 12350, uptime 0:00:05
uptime-monitor:uptime-notification-worker_00  RUNNING   pid 12351, uptime 0:00:05
uptime-monitor:uptime-notification-worker_01  RUNNING   pid 12352, uptime 0:00:05
uptime-monitor:uptime-queue-health         RUNNING   pid 12353, uptime 0:00:05
uptime-monitor:uptime-queue-cleanup        RUNNING   pid 12354, uptime 0:00:05
```

### 5. Set Permissions

```bash
# Set ownership
sudo chown -R www-data:www-data /var/www/uptime-monitor

# Set directory permissions
sudo find /var/www/uptime-monitor -type d -exec chmod 755 {} \;

# Set file permissions
sudo find /var/www/uptime-monitor -type f -exec chmod 644 {} \;

# Make artisan executable
sudo chmod +x /var/www/uptime-monitor/artisan

# Set writable directories
sudo chmod -R 775 /var/www/uptime-monitor/storage
sudo chmod -R 775 /var/www/uptime-monitor/bootstrap/cache
```

### 6. Optimize Laravel

```bash
cd /var/www/uptime-monitor

# Cache configurations
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Optimize composer autoloader
composer dump-autoload --optimize
```

### 7. Setup Nginx (Web Server)

**Create site config:**
```bash
sudo nano /etc/nginx/sites-available/uptime-monitor
```

**nginx.conf:**
```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/uptime-monitor/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

**Enable site:**
```bash
sudo ln -s /etc/nginx/sites-available/uptime-monitor /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

**Setup SSL (Optional but Recommended):**
```bash
sudo apt-get install certbot python3-certbot-nginx
sudo certbot --nginx -d your-domain.com
```

---

## ✅ Verification & Testing

### 1. Test Queue System

```bash
# Check queue health
php artisan queue:monitor-health

# Should show:
# ✓ Queue health is good
# Total Jobs: < 100
# Priority Queue: < 10
# Regular Queue: < 100
```

### 2. Test Monitor Creation

```bash
# Via tinker
php artisan tinker
>>> $m = \App\Models\Monitor::create([
    'name' => 'Test Monitor',
    'type' => 'http',
    'target' => 'https://google.com',
    'interval_seconds' => 60,
    'enabled' => 1
]);
>>> // Check if job dispatched
>>> exit
```

**Check logs:**
```bash
sudo tail -f /var/log/supervisor/uptime-priority-queue.log
```

**Expected:**
- Job should appear immediately in priority queue log
- Monitor should be checked within 2-5 seconds
- Log should appear in UI

### 3. Test Notifications

```bash
# Create test incident
php artisan tinker
>>> $monitor = \App\Models\Monitor::first();
>>> $incident = \App\Models\Incident::create([
    'monitor_id' => $monitor->id,
    'type' => 'down',
    'started_at' => now()
]);
>>> exit
```

**Check notification log:**
```bash
sudo tail -f /var/log/supervisor/uptime-notification-worker.log
```

### 4. Monitor Worker Status

```bash
# Real-time supervisor status
watch -n 2 'sudo supervisorctl status uptime-monitor:*'

# View logs
sudo tail -f /var/log/supervisor/uptime-*.log

# Check process count
ps aux | grep "queue:work" | wc -l
# Should show: 8 (2 priority + 4 regular + 2 notification)
```

---

## 📊 Monitoring & Maintenance

### Daily Checks

```bash
# Check worker status
sudo supervisorctl status uptime-monitor:*

# Check queue health
cd /var/www/uptime-monitor
php artisan queue:monitor-health

# Check logs for errors
sudo grep -i error /var/log/supervisor/uptime-*.log | tail -20
sudo grep -i error /var/www/uptime-monitor/storage/logs/*.log | tail -20
```

### Weekly Maintenance

```bash
# Flush old failed jobs
php artisan queue:flush

# Clear old logs (if needed)
php artisan log:clear

# Update dependencies (if needed)
composer update --no-dev
php artisan migrate --force
php artisan config:cache
sudo supervisorctl restart uptime-monitor:*
```

### Emergency Procedures

**Queue Overflow (> 10,000 jobs):**
```bash
# 1. Stop workers
sudo supervisorctl stop uptime-monitor:*

# 2. Clear queue
php artisan queue:clear redis

# 3. Disable monitors temporarily
psql -U postgres uptime_monitor -c "UPDATE monitors SET enabled=false;"

# 4. Re-enable monitors gradually
psql -U postgres uptime_monitor -c "UPDATE monitors SET enabled=true WHERE id <= 10;"

# 5. Start workers
sudo supervisorctl start uptime-monitor:*

# 6. Monitor closely
watch -n 2 'php artisan queue:monitor-health'
```

**Worker Crashed:**
```bash
# Restart all workers
sudo supervisorctl restart uptime-monitor:*

# Or restart specific worker
sudo supervisorctl restart uptime-monitor:uptime-priority-queue_00
```

**High Memory Usage:**
```bash
# Check PHP memory limit
php -i | grep memory_limit

# Increase in php.ini
sudo nano /etc/php/8.2/cli/php.ini
# memory_limit = 512M

# Or in supervisor config (already set to max-jobs=1000 for auto-restart)
```

---

## 🔧 Tuning for Traffic

Edit `/etc/supervisor/conf.d/uptime-monitor.conf`:

### Low Traffic (< 50 monitors):
```ini
uptime-priority-queue: numprocs=2, sleep=0
uptime-regular-queue: numprocs=2, sleep=1
uptime-notification-worker: numprocs=1, sleep=0
```

### Medium Traffic (50-200 monitors) - DEFAULT:
```ini
uptime-priority-queue: numprocs=2, sleep=0
uptime-regular-queue: numprocs=4, sleep=1
uptime-notification-worker: numprocs=2, sleep=0
```

### High Traffic (> 200 monitors):
```ini
uptime-priority-queue: numprocs=4, sleep=0
uptime-regular-queue: numprocs=8, sleep=0
uptime-notification-worker: numprocs=4, sleep=0
```

**After editing:**
```bash
sudo supervisorctl reread
sudo supervisorctl update
```

---

## 📝 Logs Location

| Component | Log File |
|-----------|----------|
| Priority Queue | `/var/log/supervisor/uptime-priority-queue.log` |
| Regular Queue | `/var/log/supervisor/uptime-regular-queue.log` |
| Notifications | `/var/log/supervisor/uptime-notification-worker.log` |
| Queue Health | `/var/log/supervisor/uptime-queue-health.log` |
| Auto Cleanup | `/var/log/supervisor/uptime-queue-cleanup.log` |
| Laravel App | `/var/www/uptime-monitor/storage/logs/laravel.log` |
| Nginx Access | `/var/log/nginx/access.log` |
| Nginx Error | `/var/log/nginx/error.log` |

---

## 🔒 Security Checklist

- [ ] `.env` file has proper permissions (600)
- [ ] APP_DEBUG=false in production
- [ ] Strong database password
- [ ] Redis password configured (if applicable)
- [ ] SSL certificate installed
- [ ] Firewall configured (UFW)
- [ ] Regular backups scheduled
- [ ] Log rotation configured
- [ ] Rate limiting enabled
- [ ] CORS properly configured

---

## 🆘 Troubleshooting

### Issue: Workers not starting

**Check:**
```bash
# Supervisor status
sudo supervisorctl status uptime-monitor:*

# Check supervisor error log
sudo tail -f /var/log/supervisor/supervisord.log

# Test command manually
cd /var/www/uptime-monitor
sudo -u www-data php artisan queue:work redis --queue=monitor-checks-priority --sleep=0 --tries=3 --timeout=300 --verbose
```

### Issue: Monitor tidak dapat log

**Debug:**
```bash
# 1. Check if job dispatched
php artisan tinker
>>> DB::table('jobs')->count();

# 2. Check if worker processing
sudo tail -f /var/log/supervisor/uptime-priority-queue.log

# 3. Check Laravel log
tail -f /var/www/uptime-monitor/storage/logs/laravel.log

# 4. Manual dispatch test
php artisan tinker
>>> dispatch(new \App\Jobs\ProcessMonitorCheck(\App\Models\Monitor::first()));
```

### Issue: High CPU/Memory

**Monitor:**
```bash
# Check resource usage
top
htop

# Check queue size
php artisan queue:monitor-health

# Reduce workers if needed
sudo supervisorctl stop uptime-monitor:uptime-regular-queue_03
```

---

## 📞 Support & Documentation

- **Full Setup Guide:** `OPTIMIZED_MONITORING_SETUP.md`
- **Quick Reference:** `QUICK_START_OPTIMIZED.txt`
- **Cara Setting:** `CARA_SETTING_MONITORING.md`
- **Queue Reference:** `QUEUE_QUICK_REFERENCE.txt`

---

**Deployment berhasil!** 🎉

Monitor baru akan langsung mendapat log dalam **2-5 detik**, dan jobs akan auto-cleanup setiap **30 menit**.
