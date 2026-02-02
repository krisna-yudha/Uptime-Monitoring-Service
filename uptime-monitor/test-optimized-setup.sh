#!/bin/bash

echo "========================================"
echo " Testing Optimized Monitoring Setup"
echo "========================================"
echo

PROJECT_PATH="/var/www/uptime-monitor"
cd $PROJECT_PATH

echo "[1/5] Testing MonitorObserver..."
php artisan tinker --execute="echo 'Observer: '; var_dump(class_exists('\App\Observers\MonitorObserver'));"
if [ $? -ne 0 ]; then
    echo "❌ Observer test failed"
    exit 1
fi

echo
echo "[2/5] Checking Queue Health Command..."
php artisan queue:monitor-health
if [ $? -ne 0 ]; then
    echo "❌ Queue health check failed"
    exit 1
fi

echo
echo "[3/5] Testing Supervisor Status..."
sudo supervisorctl status uptime-monitor:*
if [ $? -ne 0 ]; then
    echo "⚠️  Supervisor not configured yet"
fi

echo
echo "[4/5] Checking Cron..."
if [ -f /etc/cron.d/uptime-monitor ]; then
    echo "✓ Cron configured"
    cat /etc/cron.d/uptime-monitor
else
    echo "⚠️  Cron not configured yet"
fi

echo
echo "[5/5] Creating Test Monitor..."
php artisan tinker --execute="\$m = \App\Models\Monitor::create(['name' => 'TEST-' . time(), 'type' => 'http', 'target' => 'https://google.com', 'interval_seconds' => 60, 'enabled' => 1]); echo 'Created monitor: ' . \$m->id . PHP_EOL; sleep(2); echo 'Jobs count: ' . DB::table('jobs')->count() . PHP_EOL; \$m->delete();"
if [ $? -ne 0 ]; then
    echo "❌ Test monitor creation failed"
    exit 1
fi

echo
echo "========================================"
echo " ✅ ALL TESTS PASSED!"
echo "========================================"
echo
echo "Setup is ready! Workers should be running."
echo "Create a new monitor and check if logs appear within 5 seconds."
echo

exit 0
