# Monitoring Protocols Documentation

**File:** `app/Jobs/ProcessMonitorCheck.php`  
**Last Updated:** January 12, 2026

## 📋 Overview

Sistem monitoring menggunakan job queue untuk melakukan pengecekan berbagai tipe monitor. Setiap monitor memiliki protokol checking yang berbeda sesuai dengan tipenya.

---

## 🔄 Monitoring Flow

```
┌─────────────────────────────────────────────────────────────┐
│ 1. Job Dispatch: ProcessMonitorCheck                       │
│    - Timeout: 300 seconds                                   │
│    - Retry: 3 times                                         │
│    - Advisory Lock: Prevent concurrent checks (PostgreSQL)  │
└──────────────────────┬──────────────────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────────────────┐
│ 2. Pre-Check Validation                                     │
│    - Check if monitor enabled                               │
│    - Check if monitor paused                                │
│    - Validate service on first check (last_status = null)   │
└──────────────────────┬──────────────────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────────────────┐
│ 3. Protocol Dispatch (switch by monitor type)               │
│    ├─ HTTP/HTTPS  → checkHttp()                             │
│    ├─ TCP         → checkTcp()                              │
│    ├─ Ping        → checkPing()                             │
│    └─ Keyword     → checkKeyword()                          │
└──────────────────────┬──────────────────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────────────────┐
│ 4. Result Processing                                        │
│    - Create MonitorCheck record                             │
│    - Update monitor status                                  │
│    - Handle incidents (up → down, down → up)                │
│    - Send notifications if threshold reached                │
└──────────────────────┬──────────────────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────────────────┐
│ 5. Auto-Requeue                                             │
│    - Schedule next check based on interval_seconds          │
│    - Only if monitor still enabled and not paused           │
└─────────────────────────────────────────────────────────────┘
```

---

## 🌐 HTTP/HTTPS Monitoring

### Protocol Details

**Method:** `checkHttp()`  
**Location:** Lines 346-419

### Configuration

```php
// Timeout Configuration
$timeoutSeconds = $monitor->timeout_ms / 1000;           // Total request timeout
$connectTimeoutSeconds = min($timeoutSeconds, 10);       // Connection phase timeout (max 10s)

// HTTP Client Setup
Http::timeout($timeoutSeconds)
    ->connectTimeout($connectTimeoutSeconds)             // ✅ Critical for non-standard ports
    ->retry($monitor->retries, 1000)
    ->withOptions(['allow_redirects' => true])
```

### Features

| Feature | Description | Config Key |
|---------|-------------|------------|
| **Custom Headers** | Add HTTP headers to request | `config['headers']` |
| **Basic Auth** | Username/password authentication | `config['auth']['type'] = 'basic'` |
| **SSL Verification** | Verify SSL certificate (default: false) | `config['verify_ssl']` |
| **Expected Status** | Validate HTTP status code | `config['expected_status_code']` |
| **Expected Content** | Search for keyword in response body | `config['expected_content']` |
| **SSL Expiry Check** | Check SSL certificate expiration (HTTPS only) | Auto for HTTPS |

### Success Criteria

```php
// Default behavior
if ($httpStatus >= 400) {
    $status = 'down';
} else {
    $status = 'up';  // 2xx, 3xx considered UP
}

// With expected_status_code
if ($httpStatus !== $config['expected_status_code']) {
    $status = 'down';
}

// With expected_content
if (!str_contains($body, $config['expected_content'])) {
    $status = 'down';
}
```

### Return Data

```php
return [
    'status' => 'up|down',
    'latency' => 123.45,                    // milliseconds
    'http_status' => 200,
    'response_size' => 5432,                // bytes
    'meta' => [
        'response_headers' => [...],        // First 10 headers
        'body_snippet' => '...',            // First 500 chars (if expected_content)
        'ssl_expiry' => '2026-12-31',       // HTTPS only
    ],
];
```

### Critical: Port Monitoring Fix

**Problem:** Monitors with non-standard ports (e.g., `192.168.88.241:8080`) were hanging and only checking once.

**Root Cause:** Missing `connectTimeout()` configuration.

**Solution:**
```php
// ✅ connectTimeout prevents hanging on connection phase
$connectTimeoutSeconds = min($timeoutSeconds, 10);
Http::timeout($timeoutSeconds)
    ->connectTimeout($connectTimeoutSeconds)  // Essential for different ports
```

**Explanation:**
- `timeout()` → Total time for entire HTTP request + response
- `connectTimeout()` → Time limit for TCP connection establishment only
- Without `connectTimeout()`, connections to unavailable ports can hang indefinitely
- With `connectTimeout(10)`, connection attempts fail fast (max 10 seconds)

---

## 🔌 TCP Port Monitoring

### Protocol Details

**Method:** `checkTcp()`  
**Location:** Lines 421-452

### Implementation

```php
// Parse target
$target = "192.168.88.241:8080";
$parts = explode(':', $target);
$host = $parts[0];                          // "192.168.88.241"
$port = isset($parts[1]) ? (int)$parts[1] : 80;

// Attempt socket connection
$socket = @fsockopen(
    $host, 
    $port, 
    $errno,                                  // Error number
    $errstr,                                 // Error string
    $monitor->timeout_ms / 1000              // Timeout in seconds
);

if ($socket) {
    fclose($socket);
    $status = 'up';
} else {
    $status = 'down';
    throw new Exception("TCP connection failed: $errstr ($errno)");
}
```

### Use Cases

- Database port checks (MySQL: 3306, PostgreSQL: 5432)
- Application ports (Tomcat: 8080, Node.js: 3000)
- Custom services on non-standard ports
- Network device port availability

### Return Data

```php
return [
    'status' => 'up|down',
    'latency' => 45.67,                     // Connection time in milliseconds
    'meta' => [
        'host' => '192.168.88.241',
        'port' => 8080,
    ],
];
```

---

## 📡 Ping Monitoring

### Protocol Details

**Method:** `checkPing()`  
**Location:** Lines 454-491

### Implementation

```php
// Platform-specific ping command
if (PHP_OS_FAMILY === 'Windows') {
    exec("ping -n 1 -w {$timeout_ms} {$host}", $output, $returnCode);
} else {
    exec("ping -c 1 -W {$timeout_seconds} {$host}", $output, $returnCode);
}

$status = ($returnCode === 0) ? 'up' : 'down';
```

### Platform Differences

| OS | Command | Count Flag | Timeout Flag | Unit |
|----|---------|------------|--------------|------|
| **Windows** | `ping` | `-n 1` | `-w {ms}` | Milliseconds |
| **Linux/Unix** | `ping` | `-c 1` | `-W {s}` | Seconds |

### Latency Extraction

The system attempts to parse actual ping latency from command output using `extractPingLatency()` helper.

**Windows Output Example:**
```
Reply from 192.168.88.241: bytes=32 time=12ms TTL=64
```

**Linux Output Example:**
```
64 bytes from 192.168.88.241: icmp_seq=1 ttl=64 time=12.3 ms
```

### Return Data

```php
return [
    'status' => 'up|down',
    'latency' => 12.34,                     // Parsed from ping output
    'meta' => [
        'host' => '192.168.88.241',
        'ping_output' => '...',             // First 3 lines of output
    ],
];
```

---

## 🔍 Keyword Monitoring

### Protocol Details

**Method:** `checkKeyword()`  
**Location:** Lines 493-525

### Implementation

```php
// 1. Perform HTTP check first
$httpResult = $this->checkHttp();

// 2. If HTTP is up, check for keyword
if ($httpResult['status'] === 'up') {
    $response = Http::timeout($timeoutSeconds)
        ->connectTimeout($connectTimeoutSeconds)
        ->get($monitor->target);
    
    $body = $response->body();
    
    if (!str_contains($body, $keyword)) {
        $httpResult['status'] = 'down';
        $httpResult['meta']['keyword_found'] = false;
    } else {
        $httpResult['meta']['keyword_found'] = true;
    }
}

return $httpResult;
```

### Configuration

```php
$config = [
    'keyword' => 'System Online',           // Required: keyword to search
    // Inherits all HTTP monitoring config
];
```

### ⚠️ Performance Note

**Current Behavior:** Makes **2 HTTP requests**
1. First request in `checkHttp()`
2. Second request to check keyword

**Potential Optimization:** Could reuse response from first request

### Use Cases

- Check if specific content appears on page ("Success", "OK", "Running")
- Verify API response contains expected data
- Ensure error messages don't appear ("Error 500", "Exception")
- Monitor application health status

---

## ⏱️ Timeout Configuration

### Two-Level Timeout System

```php
┌─────────────────────────────────────────────────────────┐
│ HTTP Request Lifecycle                                  │
├─────────────────────────────────────────────────────────┤
│                                                         │
│  ┌────────────────────┐   ┌──────────────────────────┐ │
│  │ Connection Phase   │   │ Request/Response Phase   │ │
│  │                    │   │                          │ │
│  │ connectTimeout(10) │   │    timeout(30)           │ │
│  │                    │   │                          │ │
│  │ - DNS lookup       │   │ - Send request           │ │
│  │ - TCP handshake    │   │ - Wait for response      │ │
│  │ - SSL handshake    │   │ - Download body          │ │
│  └────────────────────┘   └──────────────────────────┘ │
│                                                         │
│  Max 10 seconds         Max (timeout - connectTimeout) │
└─────────────────────────────────────────────────────────┘
```

### Configuration Examples

```php
// Example 1: Fast check (10 second total)
$monitor->timeout_ms = 10000;
// Result: connectTimeout = 10s, timeout = 10s

// Example 2: Slow endpoint (30 second total)
$monitor->timeout_ms = 30000;
// Result: connectTimeout = 10s, timeout = 30s

// Example 3: Very patient (60 second total)
$monitor->timeout_ms = 60000;
// Result: connectTimeout = 10s, timeout = 60s
```

### Why connectTimeout is Critical

**Without connectTimeout:**
```php
Http::timeout(30)->get('http://192.168.88.241:8080');
// Problem: Can hang for minutes if port is firewalled
// Connection attempts with no response can take 2-3 minutes
```

**With connectTimeout:**
```php
Http::timeout(30)->connectTimeout(10)->get('http://192.168.88.241:8080');
// Solution: Connection phase limited to 10 seconds max
// If port unreachable, fails fast and returns 'down' status
```

---

## 🎯 Incident Management

### Status Change Detection

```php
if ($currentStatus === 'down' && $previousStatus !== 'down') {
    // Create incident when going DOWN
}

if ($currentStatus === 'up' && $previousStatus === 'down') {
    // Resolve incident when going UP
}
```

### Anti-Spam Protection (FR-16)

**Default Threshold:** 10 consecutive failures

```php
$notifyThreshold = $monitor->notify_after_retries ?? 10;
$effectiveThreshold = max(10, $notifyThreshold);

if ($monitor->consecutive_failures >= $effectiveThreshold) {
    SendNotification::dispatch($monitor, 'down', $incident);
}
```

**Purpose:** Prevent notification spam from temporary network glitches.

### Critical Error Immediate Alert

Certain errors trigger **immediate incident creation** without waiting for threshold:

```php
// Critical errors (bypass threshold)
- HTTP 500, 502, 503, 504 (Server errors)
- Connection refused
- Timeout
- DNS resolution failure

// These create incident immediately and send notifications
if ($isCriticalError) {
    $incident = Incident::create([...]);
    SendNotification::dispatch($monitor, 'down', $incident);
}
```

### Critical Down Alert (20 Consecutive Failures)

Special notification sent when service down **20 times consecutively**:

```php
if ($monitor->consecutive_failures == 20 && !$hasCriticalAlertBeenSent()) {
    $this->sendCriticalDownAlert($incident);
}
```

---

## 🔒 Concurrency Control

### Advisory Locks (PostgreSQL)

```php
// Acquire lock before checking
$lockKey = $monitor->id;
$lockAcquired = DB::select(
    "SELECT pg_try_advisory_lock(?) as acquired", 
    [$lockKey]
)[0]->acquired;

if (!$lockAcquired) {
    // Another worker is checking this monitor
    Log::info("Monitor check skipped - lock not acquired");
    return;
}

// ... perform check ...

// Always release lock
DB::select("SELECT pg_advisory_unlock(?)", [$lockKey]);
```

**Purpose:** Prevent duplicate checks when multiple workers running.

**Fallback:** Gracefully degrades on MySQL (advisory locks not supported).

---

## 🔄 Auto-Requeue Mechanism

After each check, the monitor automatically schedules the next check:

```php
$delay = $monitor->interval_seconds;

ProcessMonitorCheck::dispatch($monitor)
    ->delay(now()->addSeconds($delay));
```

### Conditions for Requeue

✅ Monitor still exists in database  
✅ Monitor is enabled (`enabled = true`)  
✅ Monitor is not paused (`pause_until = null` or past)  
✅ Monitor type is not `push` (heartbeat monitors don't auto-check)

### Example Timeline

```
10:00:00 - Check #1 completed
10:00:00 - Requeue for 10:00:10 (interval = 10s)
10:00:10 - Check #2 started
10:00:15 - Check #2 completed
10:00:15 - Requeue for 10:00:25
...
```

---

## 📊 Result Data Structure

All protocol checkers return consistent data format:

```php
return [
    'status' => string,              // 'up' | 'down' | 'unknown'
    'latency' => float|null,         // Milliseconds
    'http_status' => int|null,       // HTTP only: 200, 404, 500, etc.
    'response_size' => int|null,     // Bytes (HTTP/HTTPS only)
    'meta' => array,                 // Protocol-specific metadata
];
```

### Status Determination

| Protocol | UP Condition | DOWN Condition |
|----------|-------------|----------------|
| **HTTP** | Status 2xx/3xx (or expected_status_code) | Status >= 400, timeout, or wrong content |
| **HTTPS** | Same as HTTP + valid SSL | Same as HTTP or SSL expired |
| **TCP** | Socket connection successful | Connection refused/timeout |
| **Ping** | Return code 0 | Return code non-zero |
| **Keyword** | HTTP up + keyword found | HTTP down or keyword not found |

---

## 🛠️ Troubleshooting

### Monitor Not Continuing Checks

**Symptoms:** Monitor checks only once, then stops.

**Common Causes:**
1. ❌ Monitor disabled (`enabled = false`)
2. ❌ Monitor paused (`pause_until` in future)
3. ❌ Worker not running
4. ❌ Queue not processing jobs
5. ❌ Exception during requeue

**Solution:**
```bash
# Check worker status
php artisan queue:work

# Check monitor status
SELECT id, name, enabled, pause_until, last_checked_at 
FROM monitors 
WHERE id = X;

# Check queue jobs
SELECT * FROM jobs ORDER BY id DESC LIMIT 10;
```

### Port Monitoring Hanging

**Symptoms:** HTTP monitors with non-standard ports (`:8080`, `:3000`) hang or timeout.

**Cause:** Missing `connectTimeout()` configuration.

**Solution:** ✅ Already implemented in line 355
```php
->connectTimeout($connectTimeoutSeconds)
```

### Keyword Not Found but Page Loads

**Symptoms:** Keyword monitoring shows DOWN even though page is accessible.

**Debugging:**
```php
// Check meta data in MonitorCheck record
SELECT meta FROM monitor_checks 
WHERE monitor_id = X 
ORDER BY checked_at DESC 
LIMIT 1;

// Look for:
{
  "keyword_found": false,
  "body_snippet": "actual page content..."
}
```

**Common Issues:**
- Case sensitivity (use `strcasecmp()` for case-insensitive)
- Dynamic content (keyword appears after JavaScript execution)
- Encoding issues (UTF-8 vs other charsets)

### Ping Shows Down but Server Reachable

**Symptoms:** Ping monitoring fails but HTTP monitoring succeeds.

**Causes:**
1. ICMP blocked by firewall
2. Insufficient permissions (ping requires elevated rights on some systems)
3. Host blocking ICMP echo requests

**Alternative:** Use TCP monitoring on specific port instead.

---

## 📝 Development Notes

### Adding New Monitor Types

To add a new monitor type (e.g., `dns`, `smtp`):

1. **Create check method:**
```php
protected function checkDns(): array
{
    $startTime = microtime(true);
    
    // Your checking logic here
    
    return [
        'status' => 'up|down',
        'latency' => (microtime(true) - $startTime) * 1000,
        'meta' => [],
    ];
}
```

2. **Add to switch statement (line 171):**
```php
case 'dns':
    $result = $this->checkDns();
    break;
```

3. **Update monitor validation rules** in `MonitorController.php`

4. **Update frontend** monitor type options

### Performance Considerations

- **Keyword monitoring** makes 2 HTTP requests (could be optimized)
- **SSL expiry check** adds latency to HTTPS requests
- **Advisory locks** only work on PostgreSQL
- **Ping** requires system `ping` command availability

---

## 🔗 Related Documentation

- [API Documentation](./API_DOCUMENTATION.md)
- [Workers Configuration](../WORKERS_README.md)
- [Incident Management](./INCIDENT_MANAGEMENT.md)
- [Notification System](../NOTIFICATION_SYSTEM_READY.md)
- [Port Monitoring Fix](../PORT_MONITORING_FIX.md)

---

**Last Review:** January 12, 2026  
**Maintainer:** Development Team
