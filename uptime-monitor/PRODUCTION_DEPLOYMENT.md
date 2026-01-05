# 🚀 Production Deployment Guide

Complete guide for deploying Uptime Monitor to production environment.

---

## 📋 Prerequisites

- Ubuntu 20.04+ / Debian 11+ (or similar Linux server)
- PHP 8.2+
- PostgreSQL 14+
- Nginx or Apache
- Supervisor
- Git
- Composer

---

## 🔧 Step-by-Step Installation

### 1. Install System Dependencies

```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install PHP and extensions
sudo apt install -y php8.2 php8.2-fpm php8.2-cli php8.2-common php8.2-pgsql \
    php8.2-mbstring php8.2-xml php8.2-curl php8.2-zip php8.2-gd php8.2-bcmath

# Install PostgreSQL
sudo apt install -y postgresql postgresql-contrib

# Install Supervisor
sudo apt install -y supervisor

# Install Nginx (optional)
sudo apt install -y nginx

# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

### 2. Setup Database

```bash
# Switch to postgres user
sudo -u postgres psql

# Create database and user
CREATE DATABASE uptime_monitor;
CREATE USER uptime_user WITH PASSWORD 'your_secure_password';
GRANT ALL PRIVILEGES ON DATABASE uptime_monitor TO uptime_user;
\q
```

### 3. Clone and Install Application

```bash
# Create directory
sudo mkdir -p /var/www
cd /var/www

# Clone repository (or upload files)
sudo git clone https://github.com/your-repo/uptime-monitor.git
cd uptime-monitor

# Set ownership
sudo chown -R www-data:www-data /var/www/uptime-monitor

# Install dependencies (as www-data user)
sudo -u www-data composer install --optimize-autoloader --no-dev
```

### 4. Configure Environment

```bash
# Copy .env file
sudo -u www-data cp .env.example .env

# Edit .env
sudo nano .env
```

**Important .env settings:**

```env
APP_NAME="Uptime Monitor"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://monitor.yourdomain.com
APP_KEY=  # Will be generated in next step

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=uptime_monitor
DB_USERNAME=uptime_user
DB_PASSWORD=your_secure_password

QUEUE_CONNECTION=database
LOG_CHANNEL=daily
LOG_LEVEL=warning

# JWT Authentication
JWT_SECRET=  # Will be generated in next step
```

### 5. Initialize Application

```bash
# Generate app key
sudo -u www-data php artisan key:generate

# Generate JWT secret
sudo -u www-data php artisan jwt:secret

# Run migrations
sudo -u www-data php artisan migrate --force

# Cache configuration
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache
```

### 6. Set Permissions

```bash
# Set proper ownership
sudo chown -R www-data:www-data /var/www/uptime-monitor

# Set directory permissions
sudo find /var/www/uptime-monitor -type d -exec chmod 755 {} \;
sudo find /var/www/uptime-monitor -type f -exec chmod 644 {} \;

# Set writable directories
sudo chmod -R 775 /var/www/uptime-monitor/storage
sudo chmod -R 775 /var/www/uptime-monitor/bootstrap/cache
```

---

## ⏰ Setup Cron (Laravel Scheduler)

**Required for automated monitoring and maintenance tasks!**

Edit crontab for www-data user:

```bash
sudo crontab -u www-data -e
```

Add this line:

```bash
* * * * * cd /var/www/uptime-monitor && php artisan schedule:run >> /dev/null 2>&1
```

This enables:
- Monitor health checks (every second)
- Metrics aggregation (minute/hour/day)
- Data cleanup (daily)
- Log rotation (monthly)

---

## 👷 Setup Supervisor (Queue Workers)

**Required for processing background jobs!**

### 1. Copy Configuration

```bash
sudo cp /var/www/uptime-monitor/supervisor-uptime-monitor.conf /etc/supervisor/conf.d/uptime-monitor.conf
```

### 2. Edit Configuration

```bash
sudo nano /etc/supervisor/conf.d/uptime-monitor.conf
```

**Update paths if needed:**
- Replace `/var/www/uptime-monitor` with your actual path
- Adjust `user=www-data` if using different user
- Adjust `numprocs` based on server capacity

### 3. Start Workers

```bash
# Reload supervisor configuration
sudo supervisorctl reread
sudo supervisorctl update

# Start workers
sudo supervisorctl start uptime-monitor-checks:*
sudo supervisorctl start uptime-monitor-notifications:*

# Check status
sudo supervisorctl status
```

**Expected output:**
```
uptime-monitor-checks:uptime-monitor-checks_00    RUNNING   pid 12345, uptime 0:00:10
uptime-monitor-checks:uptime-monitor-checks_01    RUNNING   pid 12346, uptime 0:00:10
uptime-monitor-notifications:uptime-monitor-notifications_00    RUNNING   pid 12347, uptime 0:00:10
```

### 4. Supervisor Management Commands

```bash
# Start all workers
sudo supervisorctl start uptime-monitor-checks:* uptime-monitor-notifications:*

# Stop all workers
sudo supervisorctl stop uptime-monitor-checks:* uptime-monitor-notifications:*

# Restart all workers
sudo supervisorctl restart uptime-monitor-checks:* uptime-monitor-notifications:*

# View logs
sudo supervisorctl tail uptime-monitor-checks
sudo supervisorctl tail uptime-monitor-notifications

# Clear logs
sudo supervisorctl clear uptime-monitor-checks
```

---

## 🌐 Setup Web Server (Nginx)

### Create Nginx Configuration

```bash
sudo nano /etc/nginx/sites-available/uptime-monitor
```

**Basic Configuration:**

```nginx
server {
    listen 80;
    server_name monitor.yourdomain.com;
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

### Enable Site

```bash
# Create symbolic link
sudo ln -s /etc/nginx/sites-available/uptime-monitor /etc/nginx/sites-enabled/

# Test configuration
sudo nginx -t

# Restart Nginx
sudo systemctl restart nginx
```

### Setup SSL with Let's Encrypt

```bash
# Install Certbot
sudo apt install -y certbot python3-certbot-nginx

# Get SSL certificate
sudo certbot --nginx -d monitor.yourdomain.com

# Auto-renewal is configured automatically
# Test renewal:
sudo certbot renew --dry-run
```

---

## 📊 Monitoring & Logs

### Application Logs

```bash
# Laravel logs
tail -f /var/www/uptime-monitor/storage/logs/laravel.log

# Daily logs
tail -f /var/www/uptime-monitor/storage/logs/laravel-$(date +%Y-%m-%d).log
```

### Worker Logs

```bash
# Monitor checks worker
tail -f /var/www/uptime-monitor/storage/logs/worker-monitor.log

# Notifications worker
tail -f /var/www/uptime-monitor/storage/logs/worker-notifications.log
```

### System Logs

```bash
# Nginx access log
sudo tail -f /var/log/nginx/access.log

# Nginx error log
sudo tail -f /var/log/nginx/error.log

# Supervisor logs
sudo tail -f /var/log/supervisor/supervisord.log
```

---

## 🔄 Deployment Updates

When deploying updates:

```bash
cd /var/www/uptime-monitor

# Pull latest code
sudo -u www-data git pull

# Install/update dependencies
sudo -u www-data composer install --optimize-autoloader --no-dev

# Run migrations
sudo -u www-data php artisan migrate --force

# Clear and recache
sudo -u www-data php artisan config:clear
sudo -u www-data php artisan cache:clear
sudo -u www-data php artisan view:clear
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache

# Restart workers
sudo supervisorctl restart uptime-monitor-checks:*
sudo supervisorctl restart uptime-monitor-notifications:*

# Restart PHP-FPM
sudo systemctl restart php8.2-fpm
```

---

## 🔒 Security Best Practices

1. **Firewall Configuration:**
```bash
sudo ufw allow 22/tcp      # SSH
sudo ufw allow 80/tcp      # HTTP
sudo ufw allow 443/tcp     # HTTPS
sudo ufw enable
```

2. **PostgreSQL Security:**
```bash
# Edit postgresql.conf
sudo nano /etc/postgresql/14/main/postgresql.conf
# Set: listen_addresses = 'localhost'

# Edit pg_hba.conf
sudo nano /etc/postgresql/14/main/pg_hba.conf
# Use: local all all md5

sudo systemctl restart postgresql
```

3. **File Permissions:**
```bash
# Ensure .env is not accessible
sudo chmod 640 /var/www/uptime-monitor/.env
sudo chown www-data:www-data /var/www/uptime-monitor/.env
```

4. **Regular Updates:**
```bash
# Keep system updated
sudo apt update && sudo apt upgrade -y

# Update Composer dependencies (periodically)
sudo -u www-data composer update
```

---

## 📈 Performance Optimization

### 1. OPcache Configuration

Edit `/etc/php/8.2/fpm/php.ini`:

```ini
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=10000
opcache.validate_timestamps=0
opcache.save_comments=1
opcache.fast_shutdown=1
```

### 2. PostgreSQL Tuning

Edit `/etc/postgresql/14/main/postgresql.conf`:

```ini
shared_buffers = 256MB
effective_cache_size = 1GB
maintenance_work_mem = 128MB
checkpoint_completion_target = 0.9
wal_buffers = 16MB
default_statistics_target = 100
random_page_cost = 1.1
effective_io_concurrency = 200
work_mem = 8MB
min_wal_size = 1GB
max_wal_size = 4GB
```

Restart PostgreSQL:
```bash
sudo systemctl restart postgresql
```

### 3. Redis (Optional but Recommended)

```bash
# Install Redis
sudo apt install -y redis-server

# Configure Laravel to use Redis
# Edit .env:
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

# Restart workers
sudo supervisorctl restart uptime-monitor-checks:*
sudo supervisorctl restart uptime-monitor-notifications:*
```

---

## 🆘 Troubleshooting

### Workers Not Running

```bash
# Check supervisor status
sudo supervisorctl status

# Check supervisor logs
sudo tail -f /var/log/supervisor/supervisord.log

# Restart supervisor
sudo systemctl restart supervisor
```

### Database Connection Issues

```bash
# Test database connection
sudo -u www-data php artisan tinker
>>> DB::connection()->getPdo();

# Check PostgreSQL is running
sudo systemctl status postgresql

# Check credentials in .env
cat /var/www/uptime-monitor/.env | grep DB_
```

### Permission Denied Errors

```bash
# Reset permissions
sudo chown -R www-data:www-data /var/www/uptime-monitor
sudo chmod -R 775 /var/www/uptime-monitor/storage
sudo chmod -R 775 /var/www/uptime-monitor/bootstrap/cache
```

### High CPU Usage

```bash
# Check worker processes
ps aux | grep artisan

# Monitor system resources
htop

# Reduce worker count in supervisor config
sudo nano /etc/supervisor/conf.d/uptime-monitor.conf
# Reduce numprocs value
sudo supervisorctl reread
sudo supervisorctl update
```

---

## ✅ Post-Deployment Checklist

- [ ] Cron job configured and running
- [ ] Supervisor workers running (check with `supervisorctl status`)
- [ ] SSL certificate installed
- [ ] Firewall configured
- [ ] Database backups scheduled
- [ ] Log rotation configured
- [ ] Monitoring configured (optional: Sentry, NewRelic)
- [ ] Create admin user
- [ ] Test monitor creation
- [ ] Test notification channels
- [ ] Verify workers processing jobs

---

## 📞 Support

For issues or questions:
- Check logs: `/var/www/uptime-monitor/storage/logs/`
- Review Laravel documentation: https://laravel.com/docs
- Review Supervisor documentation: http://supervisord.org/

---

**🎉 Congratulations! Your Uptime Monitor is now running in production!**
