# 🔬 Research & Development Guide

Panduan lengkap untuk pengembangan, riset, dan eksplorasi fitur baru pada Uptime Monitor.

---

## 📋 Table of Contents

1. [Architecture Overview](#architecture-overview)
2. [Technology Stack](#technology-stack)
3. [Development Environment Setup](#development-environment-setup)
4. [Code Structure & Design Patterns](#code-structure--design-patterns)
5. [Database Schema](#database-schema)
6. [Queue & Worker System](#queue--worker-system)
7. [API Design](#api-design)
8. [Testing Strategy](#testing-strategy)
9. [Feature Development Workflow](#feature-development-workflow)
10. [Performance Optimization](#performance-optimization)
11. [Security Considerations](#security-considerations)
12. [Contributing Guidelines](#contributing-guidelines)
13. [Future Roadmap](#future-roadmap)

---

## 🏗️ Architecture Overview

### System Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                         Frontend Layer                           │
│                  Vue.js 3 + Composition API                      │
│                  (Port 5173 - Development)                       │
└───────────────────────────┬─────────────────────────────────────┘
                            │ HTTP/REST API
                            │ JWT Authentication
┌───────────────────────────▼─────────────────────────────────────┐
│                      Backend API Layer                           │
│                    Laravel 11 (PHP 8.2+)                         │
│              Controllers → Services → Models                     │
└───────────────────────────┬─────────────────────────────────────┘
                            │
        ┌───────────────────┼───────────────────┐
        │                   │                   │
┌───────▼────────┐  ┌──────▼──────┐  ┌────────▼────────┐
│  Queue Workers  │  │  Scheduler  │  │    Database     │
│  (Background)   │  │   (Cron)    │  │   PostgreSQL    │
│                 │  │             │  │                 │
│ - Monitor Check │  │ - Metrics   │  │ - Monitors      │
│ - Notifications │  │ - Cleanup   │  │ - Incidents     │
│ - SSL Check     │  │ - Aggregate │  │ - Checks        │
└─────────────────┘  └─────────────┘  └─────────────────┘
        │                                       
        │  Notifications                        
        ▼                                       
┌─────────────────────────────────────────────┐
│        External Services                     │
│  - Discord Webhook                           │
│  - Telegram Bot API                          │
│  - Slack Webhook                             │
│  - Generic Webhooks                          │
└─────────────────────────────────────────────┘
```

### Request Flow

**Monitor Creation Flow:**
```
User → Frontend → POST /api/monitors → MonitorController@store
  → Monitor::create() → Job: ProcessMonitorCheck (immediate)
  → Database → Response → Frontend Update
```

**Monitoring Check Flow:**
```
Scheduler (every second) → RunMonitorChecks Command
  → Finds monitors due for check → Dispatch ProcessMonitorCheck Job
  → Queue Worker picks job → Execute health check (HTTP/PING/PORT)
  → Update monitor status → Create incident if down
  → Dispatch SendNotification Job → Notification Worker
  → Send to Discord/Telegram/Slack
```

**Incident Management Flow:**
```
Service Down → ProcessMonitorCheck detects failure
  → Create/Update Incident → Dispatch Notification
  → User receives alert → User acknowledges/resolves via UI
  → Update incident status → Send resolution notification
```

---

## 🛠️ Technology Stack

### Backend
- **Framework:** Laravel 11
- **Language:** PHP 8.2+
- **Database:** PostgreSQL 14+
- **Queue Driver:** Database (default) / Redis (production)
- **Authentication:** JWT (tymon/jwt-auth)
- **HTTP Client:** Guzzle
- **Validation:** Laravel Form Requests

### Frontend
- **Framework:** Vue.js 3
- **Build Tool:** Vite
- **State Management:** Pinia
- **Router:** Vue Router 4
- **HTTP Client:** Axios
- **Styling:** CSS3 (Custom gradients & glassmorphism)

### DevOps
- **Process Manager:** Supervisor (production)
- **Task Scheduler:** Laravel Scheduler + Cron
- **Web Server:** Nginx (recommended) / Apache
- **SSL:** Let's Encrypt (Certbot)

### External Integrations
- **Discord:** Webhook API
- **Telegram:** Bot API
- **Slack:** Webhook API

---

## 💻 Development Environment Setup

### Prerequisites
```bash
# Windows (XAMPP/Laragon)
- PHP 8.2+ with extensions: pgsql, mbstring, xml, curl, zip
- PostgreSQL 14+
- Composer 2.x
- Node.js 18+ & npm
- Git

# Linux/macOS
- Same as above
- Additional: supervisor, nginx/apache
```

### Initial Setup

```bash
# 1. Clone repository
git clone <repository-url>
cd uptime-monitor

# 2. Install backend dependencies
composer install

# 3. Setup environment
cp .env.example .env
php artisan key:generate
php artisan jwt:secret

# 4. Configure database (.env)
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=uptime_monitor_dev
DB_USERNAME=postgres
DB_PASSWORD=your_password

# 5. Run migrations
php artisan migrate

# 6. Seed test data (optional)
php artisan db:seed

# 7. Install frontend dependencies
cd ../uptime-frontend
npm install

# 8. Configure frontend (.env)
VITE_BACKEND_URL=http://localhost:8000/api
```

### Running Development Servers

**Option 1: Batch Scripts (Windows)**
```batch
# Start all services
start-monitoring.bat

# Stop all services
stop-monitoring.bat
```

**Option 2: Manual**
```bash
# Terminal 1: Backend API
php artisan serve

# Terminal 2: Monitor Checks Worker
php artisan worker:monitor-checks --verbose

# Terminal 3: Notification Worker
php artisan worker:notifications --verbose

# Terminal 4: Frontend Dev Server
cd ../uptime-frontend
npm run dev

# Terminal 5: Queue Worker (alternative to custom workers)
php artisan queue:work --queue=monitor-checks,notifications --verbose
```

---

## 📂 Code Structure & Design Patterns

### Backend Structure

```
app/
├── Console/Commands/          # Artisan commands
│   ├── RunMonitorChecks.php       # Main monitor worker
│   ├── RunNotificationWorker.php  # Notification worker
│   ├── AggregateMonitorMetrics.php
│   └── CleanupOldMonitorData.php
│
├── Http/Controllers/Api/      # API Controllers
│   ├── AuthController.php         # Authentication
│   ├── MonitorController.php      # CRUD monitors
│   ├── IncidentController.php     # Incident management
│   ├── NotificationChannelController.php
│   ├── PublicMonitorController.php # Public status page
│   └── UserController.php         # User management
│
├── Jobs/                      # Queue Jobs
│   ├── ProcessMonitorCheck.php    # Execute health checks
│   ├── SendNotification.php       # Send alerts
│   └── CheckSSLCertificate.php    # SSL validation
│
├── Models/                    # Eloquent Models
│   ├── Monitor.php               # Service monitors
│   ├── Incident.php              # Downtime incidents
│   ├── MonitorCheck.php          # Check history
│   ├── NotificationChannel.php   # Alert channels
│   ├── User.php                  # User accounts
│   └── MonitorMetricAggregated.php # Metrics data
│
├── Services/                  # Business Logic Services
│   ├── MonitorCheckService.php   # Check execution logic
│   ├── NotificationService.php   # Notification dispatch
│   └── MetricsAggregationService.php
│
└── Middleware/               # HTTP Middleware
    ├── Authenticate.php
    └── RoleMiddleware.php        # Role-based access
```

### Design Patterns Used

#### 1. **Repository Pattern** (Partial)
```php
// Models act as repositories with query scopes
class Monitor extends Model {
    public function scopeEnabled($query) {
        return $query->where('enabled', true);
    }
    
    public function scopeDueForCheck($query) {
        return $query->where('enabled', true)
            ->where(function($q) {
                $q->whereNull('next_check_at')
                  ->orWhere('next_check_at', '<=', now());
            });
    }
}
```

#### 2. **Job Pattern** (Queue Jobs)
```php
// ProcessMonitorCheck.php
class ProcessMonitorCheck implements ShouldQueue {
    public function handle() {
        // Execute health check
        // Update monitor status
        // Create incident if needed
        // Dispatch notification
    }
}
```

#### 3. **Service Pattern**
```php
// MonitorCheckService.php
class MonitorCheckService {
    public function executeCheck(Monitor $monitor) {
        return match($monitor->type) {
            'http' => $this->checkHttp($monitor),
            'ping' => $this->checkPing($monitor),
            'port' => $this->checkPort($monitor),
        };
    }
}
```

#### 4. **Observer Pattern**
```php
// MonitorObserver.php
class MonitorObserver {
    public function created(Monitor $monitor) {
        // Schedule first check
        ProcessMonitorCheck::dispatch($monitor);
    }
}
```

### Frontend Structure (Vue 3)

```
src/
├── components/           # Reusable components
│   ├── Navbar.vue
│   ├── MonitorCard.vue
│   └── IncidentTimeline.vue
│
├── views/               # Page components
│   ├── DashboardView.vue
│   ├── MonitorsView.vue
│   ├── IncidentsView.vue
│   ├── PublicMonitorsView.vue
│   └── LoginView.vue
│
├── stores/              # Pinia state management
│   ├── auth.js          # Authentication state
│   ├── monitors.js      # Monitor state
│   └── incidents.js     # Incident state
│
├── services/            # API services
│   └── api.js          # Axios instance & endpoints
│
├── router/              # Vue Router
│   └── index.js        # Route definitions
│
└── styles/             # Global styles
    └── main.css
```

---

## 🗄️ Database Schema

### Core Tables

#### `monitors`
```sql
- id (PK)
- name
- type (http/ping/port/ssl/heartbeat)
- target (URL/IP/hostname)
- group_name (for grouping)
- interval_seconds (check frequency)
- timeout_ms
- retries
- enabled (boolean)
- is_public (boolean - for public status page)
- last_status (up/down/unknown)
- last_checked_at
- next_check_at
- created_by (FK → users)
- timestamps
```

#### `incidents`
```sql
- id (PK)
- monitor_id (FK → monitors)
- status (open/acknowledged/resolved)
- severity (critical/warning/info)
- started_at
- acknowledged_at
- resolved_at
- description
- resolution_notes
- timestamps
```

#### `monitor_checks`
```sql
- id (PK)
- monitor_id (FK → monitors)
- status (up/down)
- response_time_ms
- status_code (for HTTP)
- error_message
- checked_at
- timestamps
```

#### `notification_channels`
```sql
- id (PK)
- name
- type (discord/telegram/slack/webhook)
- config (JSON - webhook URL, bot token, etc)
- enabled (boolean)
- created_by (FK → users)
- timestamps
```

#### `monitor_metric_aggregated`
```sql
- id (PK)
- monitor_id (FK → monitors)
- period (minute/hour/day)
- period_start
- total_checks
- successful_checks
- failed_checks
- avg_response_time
- min_response_time
- max_response_time
- uptime_percentage
- timestamps
```

### Relationships

```php
// Monitor.php
public function checks() {
    return $this->hasMany(MonitorCheck::class);
}

public function incidents() {
    return $this->hasMany(Incident::class);
}

public function creator() {
    return $this->belongsTo(User::class, 'created_by');
}

// Incident.php
public function monitor() {
    return $this->belongsTo(Monitor::class);
}
```

---

## 🔄 Queue & Worker System

### Queue Architecture

**Two Dedicated Workers:**

1. **Monitor Checks Worker** (`monitor-checks` queue)
   - Processes: Health checks, SSL validation
   - Priority: High
   - Concurrency: 2 processes (production)

2. **Notification Worker** (`notifications` queue)
   - Processes: Send alerts to Discord/Telegram/Slack
   - Priority: Medium
   - Concurrency: 1 process

### Custom Worker Commands

```php
// RunMonitorChecks.php
protected $signature = 'worker:monitor-checks {--verbose}';

public function handle() {
    while (true) {
        // Find monitors due for check
        $monitors = Monitor::dueForCheck()->get();
        
        foreach ($monitors as $monitor) {
            ProcessMonitorCheck::dispatch($monitor);
        }
        
        sleep(1); // Check every second
    }
}
```

### Job Implementation

```php
// ProcessMonitorCheck.php
class ProcessMonitorCheck implements ShouldQueue {
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    public $queue = 'monitor-checks';
    public $tries = 1;
    public $timeout = 30;
    
    public function handle() {
        // 1. Execute health check
        $result = $this->executeCheck();
        
        // 2. Save check result
        MonitorCheck::create([...]);
        
        // 3. Update monitor status
        $this->monitor->update([
            'last_status' => $result['status'],
            'last_checked_at' => now(),
            'next_check_at' => now()->addSeconds($this->monitor->interval_seconds)
        ]);
        
        // 4. Handle incidents
        if ($result['status'] === 'down') {
            $this->createOrUpdateIncident();
        }
        
        // 5. Send notifications if needed
        if ($this->shouldNotify()) {
            SendNotification::dispatch($incident);
        }
    }
}
```

---

## 🔌 API Design

### Authentication

**JWT-based authentication:**

```http
POST /api/auth/login
Content-Type: application/json

{
    "email": "admin@example.com",
    "password": "password"
}

Response:
{
    "success": true,
    "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "user": {
        "id": 1,
        "name": "Admin",
        "email": "admin@example.com",
        "role": "admin"
    }
}
```

**Subsequent requests:**
```http
GET /api/monitors
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc...
```

### RESTful Endpoints

#### Monitors
```http
GET    /api/monitors           # List all monitors
POST   /api/monitors           # Create monitor
GET    /api/monitors/{id}      # Get single monitor
PUT    /api/monitors/{id}      # Update monitor
DELETE /api/monitors/{id}      # Delete monitor
POST   /api/monitors/{id}/pause   # Pause monitoring
POST   /api/monitors/{id}/resume  # Resume monitoring
```

#### Incidents
```http
GET    /api/incidents                    # List incidents
GET    /api/incidents/{id}               # Get incident details
POST   /api/incidents/{id}/acknowledge   # Acknowledge incident
POST   /api/incidents/{id}/resolve       # Resolve incident
POST   /api/incidents/{id}/notes         # Add note
```

#### Public API (No Auth)
```http
GET    /api/public/monitors              # List public monitors
GET    /api/public/monitors/{id}         # Get public monitor details
GET    /api/public/monitors/statistics   # Overall statistics
```

### Response Format

**Success:**
```json
{
    "success": true,
    "data": { ... },
    "message": "Monitor created successfully"
}
```

**Error:**
```json
{
    "success": false,
    "message": "Validation error",
    "errors": {
        "name": ["The name field is required"],
        "target": ["The target must be a valid URL"]
    }
}
```

---

## 🧪 Testing Strategy

### Unit Tests

**Test Monitor Model:**
```php
// tests/Unit/MonitorTest.php
public function test_monitor_is_due_for_check() {
    $monitor = Monitor::factory()->create([
        'next_check_at' => now()->subMinute()
    ]);
    
    $this->assertTrue($monitor->isDueForCheck());
}

public function test_monitor_calculates_next_check_time() {
    $monitor = Monitor::factory()->create([
        'interval_seconds' => 60
    ]);
    
    $monitor->scheduleNextCheck();
    
    $this->assertEquals(
        now()->addSeconds(60)->timestamp,
        $monitor->next_check_at->timestamp,
        '', 5 // 5 second tolerance
    );
}
```

### Feature Tests

**Test Monitor API:**
```php
// tests/Feature/MonitorApiTest.php
public function test_authenticated_user_can_create_monitor() {
    $user = User::factory()->create();
    $token = JWTAuth::fromUser($user);
    
    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $token
    ])->postJson('/api/monitors', [
        'name' => 'Test Monitor',
        'type' => 'http',
        'target' => 'https://example.com',
        'interval_seconds' => 60
    ]);
    
    $response->assertStatus(201)
             ->assertJson([
                 'success' => true
             ]);
    
    $this->assertDatabaseHas('monitors', [
        'name' => 'Test Monitor',
        'target' => 'https://example.com'
    ]);
}
```

### Integration Tests

**Test Queue Job:**
```php
// tests/Feature/MonitorCheckJobTest.php
public function test_monitor_check_job_creates_incident_on_failure() {
    Queue::fake();
    
    $monitor = Monitor::factory()->create([
        'type' => 'http',
        'target' => 'https://nonexistent-domain-12345.com'
    ]);
    
    ProcessMonitorCheck::dispatch($monitor);
    
    $this->assertDatabaseHas('incidents', [
        'monitor_id' => $monitor->id,
        'status' => 'open'
    ]);
}
```

### Manual Testing Checklist

- [ ] Create monitor with valid data
- [ ] Create monitor with invalid data (validation)
- [ ] Monitor detects service up
- [ ] Monitor detects service down
- [ ] Incident created on service down
- [ ] Notification sent to Discord
- [ ] Notification sent to Telegram
- [ ] Notification sent to Slack
- [ ] SSL certificate check works
- [ ] Public status page displays monitors
- [ ] User can acknowledge incident
- [ ] User can resolve incident
- [ ] Metrics aggregation works
- [ ] Data cleanup works

---

## 🔧 Feature Development Workflow

### Step-by-Step Guide

#### Example: Adding "SMS Notification" Feature

**1. Planning Phase**
```markdown
Feature: SMS Notifications via Twilio
- Add SMS as notification channel type
- Integrate Twilio API
- Add SMS configuration UI
- Test SMS delivery
```

**2. Database Migration**
```php
// database/migrations/xxxx_add_sms_to_notification_channels.php
public function up() {
    Schema::table('notification_channels', function (Blueprint $table) {
        // Config JSON already supports any type
        // No schema changes needed, just add validation
    });
}
```

**3. Update Model**
```php
// app/Models/NotificationChannel.php
protected $casts = [
    'config' => 'array',
    'enabled' => 'boolean'
];

// Add validation
public function validateConfig() {
    if ($this->type === 'sms') {
        return isset($this->config['twilio_sid']) 
            && isset($this->config['twilio_token'])
            && isset($this->config['phone_number']);
    }
    // ... other types
}
```

**4. Create Service**
```php
// app/Services/SmsNotificationService.php
namespace App\Services;

use Twilio\Rest\Client;

class SmsNotificationService {
    public function send($channel, $message) {
        $client = new Client(
            $channel->config['twilio_sid'],
            $channel->config['twilio_token']
        );
        
        return $client->messages->create(
            $channel->config['phone_number'],
            [
                'from' => $channel->config['from_number'],
                'body' => $message
            ]
        );
    }
}
```

**5. Update Notification Job**
```php
// app/Jobs/SendNotification.php
public function handle() {
    $message = $this->formatMessage();
    
    foreach ($this->channels as $channel) {
        match($channel->type) {
            'discord' => $this->sendDiscord($channel, $message),
            'telegram' => $this->sendTelegram($channel, $message),
            'slack' => $this->sendSlack($channel, $message),
            'sms' => app(SmsNotificationService::class)->send($channel, $message),
            default => null
        };
    }
}
```

**6. Add Controller Endpoint**
```php
// app/Http/Controllers/Api/NotificationChannelController.php
public function test($id) {
    $channel = NotificationChannel::findOrFail($id);
    
    $message = "Test notification from Uptime Monitor";
    
    if ($channel->type === 'sms') {
        app(SmsNotificationService::class)->send($channel, $message);
    }
    
    return response()->json([
        'success' => true,
        'message' => 'Test SMS sent'
    ]);
}
```

**7. Frontend UI**
```vue
<!-- CreateNotificationChannel.vue -->
<div v-if="form.type === 'sms'">
  <div class="form-group">
    <label>Twilio Account SID</label>
    <input v-model="form.config.twilio_sid" />
  </div>
  <div class="form-group">
    <label>Twilio Auth Token</label>
    <input type="password" v-model="form.config.twilio_token" />
  </div>
  <div class="form-group">
    <label>Phone Number (To)</label>
    <input v-model="form.config.phone_number" placeholder="+1234567890" />
  </div>
  <div class="form-group">
    <label>From Number</label>
    <input v-model="form.config.from_number" placeholder="+1234567890" />
  </div>
</div>
```

**8. Testing**
```php
// tests/Feature/SmsNotificationTest.php
public function test_sms_notification_sends_successfully() {
    $channel = NotificationChannel::factory()->create([
        'type' => 'sms',
        'config' => [
            'twilio_sid' => 'test_sid',
            'twilio_token' => 'test_token',
            'phone_number' => '+1234567890',
            'from_number' => '+0987654321'
        ]
    ]);
    
    $incident = Incident::factory()->create();
    
    SendNotification::dispatch($incident, [$channel]);
    
    // Assert SMS was sent (mock Twilio)
}
```

**9. Documentation**
```markdown
# SMS Notifications

Configure SMS alerts via Twilio:

1. Create Twilio account at https://twilio.com
2. Get Account SID and Auth Token
3. Purchase phone number
4. Add SMS channel in UI
5. Test notification
```

---

## ⚡ Performance Optimization

### Database Optimization

**1. Add Indexes**
```php
// Migration
Schema::table('monitors', function (Blueprint $table) {
    $table->index('enabled');
    $table->index('next_check_at');
    $table->index(['enabled', 'next_check_at']); // Composite
});

Schema::table('monitor_checks', function (Blueprint $table) {
    $table->index(['monitor_id', 'checked_at']);
});
```

**2. Query Optimization**
```php
// Bad
foreach (Monitor::all() as $monitor) {
    $monitor->checks; // N+1 query
}

// Good
Monitor::with('checks')->get();

// Better (pagination)
Monitor::with('checks')->paginate(50);
```

**3. Use Chunking for Large Datasets**
```php
Monitor::chunk(100, function ($monitors) {
    foreach ($monitors as $monitor) {
        // Process
    }
});
```

### Cache Strategy

```php
// Cache monitor statistics
$stats = Cache::remember('monitor_stats', 300, function () {
    return Monitor::selectRaw('
        COUNT(*) as total,
        SUM(CASE WHEN last_status = "up" THEN 1 ELSE 0 END) as online,
        SUM(CASE WHEN last_status = "down" THEN 1 ELSE 0 END) as offline
    ')->first();
});

// Cache public monitors
$publicMonitors = Cache::remember('public_monitors', 60, function () {
    return Monitor::where('is_public', true)
        ->where('enabled', true)
        ->get();
});
```

### Queue Optimization

```php
// Use Redis for queues in production
QUEUE_CONNECTION=redis

// Prioritize queues
php artisan queue:work --queue=monitor-checks,notifications

// Multiple workers
supervisor numprocs=2
```

---

## 🔒 Security Considerations

### Input Validation

```php
// MonitorController.php
public function store(Request $request) {
    $request->validate([
        'name' => 'required|string|max:255',
        'target' => 'required|url', // Validate URL
        'interval_seconds' => 'integer|min:1|max:3600',
        'type' => 'in:http,ping,port,ssl'
    ]);
}
```

### SQL Injection Prevention

```php
// Bad
DB::select("SELECT * FROM monitors WHERE name = '{$name}'");

// Good (Parameter binding)
DB::table('monitors')->where('name', $name)->get();

// Good (Eloquent)
Monitor::where('name', $name)->get();
```

### XSS Prevention

```vue
<!-- Bad -->
<div v-html="monitor.name"></div>

<!-- Good -->
<div>{{ monitor.name }}</div>
```

### CSRF Protection

```php
// Automatic in Laravel for POST/PUT/DELETE
// Frontend must include CSRF token or use API tokens
```

### Rate Limiting

```php
// routes/api.php
Route::middleware('throttle:60,1')->group(function () {
    Route::post('/auth/login');
});

Route::middleware(['auth:api', 'throttle:120,1'])->group(function () {
    Route::apiResource('monitors', MonitorController::class);
});
```

### Environment Variables

```env
# Never commit .env to git
# Use .env.example as template

APP_KEY=base64:random_key_here
JWT_SECRET=random_secret_here
DB_PASSWORD=strong_password_here

# Sensitive API keys
TWILIO_SID=
TWILIO_TOKEN=
```

---

## 🤝 Contributing Guidelines

### Code Style

**PHP (PSR-12)**
```php
namespace App\Services;

use App\Models\Monitor;
use Illuminate\Support\Facades\Http;

class MonitorCheckService
{
    public function executeCheck(Monitor $monitor): array
    {
        return match ($monitor->type) {
            'http' => $this->checkHttp($monitor),
            'ping' => $this->checkPing($monitor),
            default => throw new \InvalidArgumentException("Unknown type: {$monitor->type}")
        };
    }
    
    private function checkHttp(Monitor $monitor): array
    {
        // Implementation
    }
}
```

**Vue.js (Composition API)**
```vue
<script setup>
import { ref, onMounted, computed } from 'vue'
import { useMonitorStore } from '@/stores/monitors'

const monitorStore = useMonitorStore()
const loading = ref(false)

const activeMonitors = computed(() => {
  return monitorStore.monitors.filter(m => m.enabled)
})

onMounted(async () => {
  loading.value = true
  await monitorStore.fetchMonitors()
  loading.value = false
})
</script>
```

### Git Workflow

```bash
# 1. Create feature branch
git checkout -b feature/sms-notifications

# 2. Make changes
# ... code changes ...

# 3. Commit with descriptive message
git add .
git commit -m "feat: add SMS notification support via Twilio"

# 4. Push to remote
git push origin feature/sms-notifications

# 5. Create Pull Request
# ... review and merge ...
```

### Commit Message Convention

```
feat: add SMS notification channel
fix: resolve memory leak in monitor worker
docs: update API documentation
refactor: simplify notification service
test: add unit tests for incident model
chore: update dependencies
```

### Pull Request Template

```markdown
## Description
Brief description of changes

## Type of Change
- [ ] Bug fix
- [ ] New feature
- [ ] Breaking change
- [ ] Documentation update

## Testing
- [ ] Unit tests pass
- [ ] Manual testing completed
- [ ] No regressions

## Checklist
- [ ] Code follows style guidelines
- [ ] Self-review completed
- [ ] Documentation updated
- [ ] No console errors
```

---

## 🚀 Future Roadmap

### Short Term (1-3 months)

- [ ] **SMS Notifications** - Twilio integration
- [ ] **Email Notifications** - SMTP support
- [ ] **Webhook Callbacks** - Custom HTTP webhooks
- [ ] **Mobile App** - React Native app
- [ ] **Status Page** - Public status page with custom domain
- [ ] **Advanced Metrics** - Response time graphs, uptime trends
- [ ] **Multi-region Monitoring** - Check from multiple locations
- [ ] **Heartbeat Monitoring** - Cron job monitoring

### Medium Term (3-6 months)

- [ ] **Alerting Rules** - Custom alert conditions
- [ ] **On-call Schedules** - Rotating notification schedules
- [ ] **Incident Timeline** - Detailed incident logs
- [ ] **API Documentation** - Swagger/OpenAPI docs
- [ ] **Webhooks** - Incident webhooks for integration
- [ ] **Custom Dashboards** - User-defined dashboards
- [ ] **Team Management** - Multi-user teams
- [ ] **Audit Logs** - Activity logging

### Long Term (6-12 months)

- [ ] **Machine Learning** - Anomaly detection
- [ ] **Predictive Alerts** - Predict incidents before they happen
- [ ] **SLA Reporting** - SLA compliance reports
- [ ] **Custom Integrations** - Jira, PagerDuty, Zendesk
- [ ] **Mobile Push Notifications** - Native mobile alerts
- [ ] **Maintenance Windows** - Scheduled maintenance
- [ ] **Multi-language Support** - i18n
- [ ] **White Label** - Rebrand for enterprise

### Research Ideas

- [ ] **GraphQL API** - Alternative to REST
- [ ] **Microservices** - Split into microservices
- [ ] **Kubernetes** - Deploy on K8s
- [ ] **Serverless** - AWS Lambda workers
- [ ] **Real-time Dashboard** - WebSocket updates
- [ ] **AI Assistant** - ChatGPT integration for incident analysis

---

## 📚 Learning Resources

### Laravel
- [Laravel Documentation](https://laravel.com/docs)
- [Laravel Queues](https://laravel.com/docs/queues)
- [Laravel Testing](https://laravel.com/docs/testing)

### Vue.js
- [Vue 3 Documentation](https://vuejs.org/)
- [Pinia Documentation](https://pinia.vuejs.org/)
- [Vue Router](https://router.vuejs.org/)

### PostgreSQL
- [PostgreSQL Documentation](https://www.postgresql.org/docs/)
- [PostgreSQL Performance](https://www.postgresql.org/docs/current/performance-tips.html)

### DevOps
- [Supervisor Documentation](http://supervisord.org/)
- [Nginx Documentation](https://nginx.org/en/docs/)
- [Let's Encrypt](https://letsencrypt.org/docs/)

---

## 📞 Getting Help

### Issue Reporting

```markdown
**Bug Report**
- Description: Clear description of the bug
- Steps to Reproduce: 1. ... 2. ... 3. ...
- Expected Behavior: What should happen
- Actual Behavior: What actually happens
- Environment: OS, PHP version, database version
- Logs: Relevant error logs
```

### Feature Request

```markdown
**Feature Request**
- Feature: Feature name
- Use Case: Why is this needed?
- Proposed Solution: How should it work?
- Alternatives: Other ways to achieve this
```

---

## 🎓 Best Practices Summary

1. **Always validate input** - Use Laravel validation
2. **Use queues for long tasks** - Don't block HTTP requests
3. **Index database columns** - Optimize query performance
4. **Cache expensive queries** - Reduce database load
5. **Write tests** - Ensure code quality
6. **Log important events** - Debug easier
7. **Use environment variables** - Keep secrets safe
8. **Follow PSR standards** - Maintain code consistency
9. **Document your code** - Help future developers
10. **Monitor performance** - Use Laravel Telescope/Debugbar

---

**Happy Coding! 🚀**
