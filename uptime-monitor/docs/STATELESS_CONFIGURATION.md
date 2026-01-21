# Stateless Configuration Guide

## Environment Variables

### Session
```env
# Stateful (default)
SESSION_DRIVER=database

# Stateless
SESSION_DRIVER=cookie
```

### Cache
```env
# Stateful (default)
CACHE_STORE=database

# Stateless
CACHE_STORE=redis
REDIS_CACHE_DB=1
```

### Queue
```env
# Stateful (default)
QUEUE_CONNECTION=database

# Stateless
QUEUE_CONNECTION=redis
REDIS_QUEUE_DB=2
```

### File Storage
```env
# Stateful (default)
FILESYSTEM_DISK=local

# Stateless
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=your-key
AWS_SECRET_ACCESS_KEY=your-secret
AWS_BUCKET=your-bucket
AWS_DEFAULT_REGION=us-east-1
```

### Redis Configuration
```env
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=null
REDIS_DB=0
REDIS_CACHE_DB=1
REDIS_QUEUE_DB=2
```

## Config Files

### config/database.php
Tambahkan Redis queue connection:
```php
'queue' => [
    'url' => env('REDIS_URL'),
    'host' => env('REDIS_HOST', '127.0.0.1'),
    'username' => env('REDIS_USERNAME'),
    'password' => env('REDIS_PASSWORD'),
    'port' => env('REDIS_PORT', '6379'),
    'database' => env('REDIS_QUEUE_DB', '2'),
],
```

### config/queue.php
Update Redis connection:
```php
'redis' => [
    'driver' => 'redis',
    'connection' => env('REDIS_QUEUE_CONNECTION', 'queue'),
    'queue' => env('REDIS_QUEUE', 'default'),
    'retry_after' => 90,
    'block_for' => null,
    'after_commit' => true,
],
```

## Production Setup

### Redis
```bash
# Install
apt-get install redis-server

# Configure
requirepass your-password
maxmemory 2gb
maxmemory-policy allkeys-lru
```

### S3
```bash
# AWS S3
aws s3 mb s3://your-bucket

# MinIO
docker run -d -p 9000:9000 minio/minio server /data
```

### Load Balancer (Apache2)
```apache
# Enable modules
a2enmod proxy proxy_http proxy_balancer lbmethod_byrequests headers

# /etc/apache2/sites-available/uptime.conf
<VirtualHost *:80>
    ServerName uptime.example.com

    <Proxy "balancer://backend">
        BalancerMember http://127.0.0.1:8000
        BalancerMember http://127.0.0.1:8001
        BalancerMember http://127.0.0.1:8002
        ProxySet lbmethod=byrequests
    </Proxy>

    ProxyPreserveHost On
    ProxyPass / balancer://backend/
    ProxyPassReverse / balancer://backend/

    ErrorLog ${APACHE_LOG_DIR}/uptime_error.log
    CustomLog ${APACHE_LOG_DIR}/uptime_access.log combined
</VirtualHost>
```

### Queue Worker
```bash
php artisan queue:work redis --sleep=3 --tries=3
```

## Incident Storage (Stateless)

Incident sudah otomatis stateless karena disimpan di database PostgreSQL.
Database dapat di-scale dengan:
- PostgreSQL replication (master-slave)
- Managed database service (AWS RDS, DigitalOcean, etc)
- Connection pooling (PgBouncer)

Notification queue menggunakan Redis queue, sehingga incident notifications juga stateless.

## Clear Cache After Changes
```bash
php artisan config:clear
php artisan cache:clear
```

## Benefits
- Horizontal scaling
- No sticky sessions
- High availability
- Shared state across instances

## Rollback
Set semua ke nilai default (database/local) dan clear cache.
