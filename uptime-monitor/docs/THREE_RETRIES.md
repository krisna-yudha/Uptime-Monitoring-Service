# 3 Retries Notification System

## Konfigurasi
- Incident dibuat setelah **3 consecutive failures**
- Notifikasi terkirim setelah **3 consecutive failures**
- Default threshold: **3** (hardcoded minimum)

## Logic Flow

```
Check #1 (DOWN) → consecutive_failures = 1 → ❌ No incident, no notification
Check #2 (DOWN) → consecutive_failures = 2 → ❌ No incident, no notification  
Check #3 (DOWN) → consecutive_failures = 3 → ✅ CREATE INCIDENT + SEND NOTIFICATION
```

## Code Changes

### ProcessMonitorCheck.php
```php
// Line ~593
if ($currentStatus === 'down' && !$lastIncident) {
    if ($this->monitor->consecutive_failures >= 3) {
        $incident = Incident::create([...]);
        SendNotification::dispatch($this->monitor, 'down', $incident);
    } else {
        Log::info("Monitor down but not creating incident yet", [
            'consecutive_failures' => $this->monitor->consecutive_failures,
            'threshold' => 3
        ]);
    }
}
```

### Database
```sql
UPDATE monitors SET notify_after_retries = 3;
```

## Verifikasi

```bash
# Check threshold
php -r "require 'vendor/autoload.php'; \$app = require 'bootstrap/app.php'; \$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap(); echo App\Models\Monitor::first()->notify_after_retries . PHP_EOL;"

# Output: 3
```

## Critical Alert
Tetap ada alert khusus setelah **20 consecutive failures** untuk downtime berkepanjangan.
