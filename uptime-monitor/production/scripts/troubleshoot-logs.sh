#!/bin/bash
# Troubleshooting script untuk monitoring logs

echo "======================================================================"
echo "UPTIME MONITOR - TROUBLESHOOTING LOGS"
echo "======================================================================"
echo ""

APP_DIR="/var/www/monitoring/Uptime-Monitoring-Service/uptime-monitor"

echo "1. Checking Redis Connection..."
redis-cli ping && echo "✓ Redis: OK" || echo "✗ Redis: FAILED"
echo ""

echo "2. Checking Queue Size..."
redis-cli << EOF
LLEN queues:monitor-checks-priority
LLEN queues:monitor-checks
LLEN queues:notifications
LLEN queues:default
EOF
echo ""

echo "3. Checking Supervisor Workers..."
sudo supervisorctl status | grep uptime
echo ""

echo "4. Recent Laravel Logs (last 50 lines)..."
tail -50 $APP_DIR/storage/logs/laravel.log
echo ""

echo "5. Recent Monitoring Logs from Database..."
cd $APP_DIR
php artisan tinker << 'TINKER'
\App\Models\MonitoringLog::orderBy('created_at', 'desc')->limit(10)->get(['id', 'monitor_id', 'event_type', 'status', 'created_at', 'meta'])->toArray();
exit
TINKER
echo ""

echo "6. Recent Monitor Checks..."
cd $APP_DIR
php artisan tinker << 'TINKER'
\App\Models\MonitorCheck::orderBy('checked_at', 'desc')->limit(5)->get(['id', 'monitor_id', 'status', 'checked_at', 'error_message'])->toArray();
exit
TINKER
echo ""

echo "======================================================================"
echo "TROUBLESHOOTING COMPLETED"
echo "======================================================================"
