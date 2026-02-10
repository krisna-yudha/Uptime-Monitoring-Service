# 🔖 Developer Quick Reference

Referensi cepat untuk development Uptime Monitor.

---

## 🚀 Quick Commands

### Development

```bash
# Start all services
start-monitoring.bat

# Backend only
php artisan serve

# Workers
php artisan worker:monitor-checks --verbose
php artisan worker:notifications --verbose

# Frontend
cd ../uptime-frontend && npm run dev

# Database
php artisan migrate
php artisan migrate:fresh --seed
php artisan db:seed
```

### Testing

```bash
# Run all tests
php artisan test

# Run specific test
php artisan test --filter MonitorTest

# With coverage
php artisan test --coverage

# Frontend tests
npm run test
```

### Debugging

```bash
# Clear cache
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Check queue
php artisan queue:work --once

# Monitor logs
tail -f storage/logs/laravel.log

# Tinker (REPL)
php artisan tinker
>>> Monitor::count()
>>> User::first()
```

---

## 📁 Key File Locations

```
Backend:
├── Routes: routes/api.php
├── Controllers: app/Http/Controllers/Api/
├── Models: app/Models/
├── Jobs: app/Jobs/
├── Commands: app/Console/Commands/
├── Migrations: database/migrations/
├── Config: config/
└── .env

Frontend:
├── Views: src/views/
├── Components: src/components/
├── Stores: src/stores/
├── API: src/services/api.js
├── Router: src/router/index.js
└── .env
```

---

## 🔌 Common API Calls

### Authentication

```javascript
// Login
POST /api/auth/login
{ email, password }

// Get user
GET /api/auth/me
Headers: { Authorization: 'Bearer TOKEN' }

// Logout
POST /api/auth/logout
```

### Monitors

```javascript
// List monitors
GET /api/monitors

// Create monitor
POST /api/monitors
{
  name: 'My Service',
  type: 'http',
  target: 'https://example.com',
  interval_seconds: 60,
  enabled: true,
  is_public: false
}

// Update monitor
PUT /api/monitors/{id}

// Delete monitor
DELETE /api/monitors/{id}

// Pause monitor
POST /api/monitors/{id}/pause
{ duration_minutes: 60 }
```

### Incidents

```javascript
// List incidents
GET /api/incidents

// Acknowledge
POST /api/incidents/{id}/acknowledge
{ note: 'Investigating...' }

// Resolve
POST /api/incidents/{id}/resolve
{ resolution_notes: 'Fixed by restarting server' }
```

---

## 🗃️ Database Quick Queries

```sql
-- Check monitor status
SELECT id, name, last_status, last_checked_at 
FROM monitors 
WHERE enabled = true;

-- Active incidents
SELECT i.*, m.name as monitor_name
FROM incidents i
JOIN monitors m ON i.monitor_id = m.id
WHERE i.status = 'open'
ORDER BY i.started_at DESC;

-- Recent checks
SELECT mc.*, m.name
FROM monitor_checks mc
JOIN monitors m ON mc.monitor_id = m.id
WHERE mc.checked_at > NOW() - INTERVAL '1 hour'
ORDER BY mc.checked_at DESC
LIMIT 100;

-- Monitor uptime (last 24h)
SELECT 
    m.name,
    COUNT(*) as total_checks,
    SUM(CASE WHEN mc.status = 'up' THEN 1 ELSE 0 END) as successful,
    ROUND(SUM(CASE WHEN mc.status = 'up' THEN 1 ELSE 0 END)::numeric / COUNT(*) * 100, 2) as uptime_pct
FROM monitors m
LEFT JOIN monitor_checks mc ON m.id = mc.monitor_id 
    AND mc.checked_at > NOW() - INTERVAL '24 hours'
GROUP BY m.id, m.name;
```

---

## 🎨 Frontend Store Usage

### Auth Store

```javascript
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()

// Login
await authStore.login({ email, password })

// Get current user
const user = authStore.user

// Logout
authStore.logout()

// Check if authenticated
if (authStore.isAuthenticated) { ... }
```

### Monitor Store

```javascript
import { useMonitorStore } from '@/stores/monitors'

const monitorStore = useMonitorStore()

// Fetch all monitors
await monitorStore.fetchMonitors()

// Access monitors
const monitors = monitorStore.monitors

// Create monitor
await monitorStore.createMonitor(data)

// Update monitor
await monitorStore.updateMonitor(id, data)

// Delete monitor
await monitorStore.deleteMonitor(id)
```

---

## 🔧 Common Code Patterns

### Creating a Job

```php
namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class MyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $queue = 'my-queue';
    public $tries = 3;
    public $timeout = 60;

    public function handle()
    {
        // Job logic here
    }
}

// Dispatch
MyJob::dispatch($data);
```

### Creating a Controller

```php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class MyController extends Controller
{
    public function index()
    {
        $data = Model::all();
        
        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $item = Model::create($validated);

        return response()->json([
            'success' => true,
            'data' => $item,
            'message' => 'Created successfully'
        ], 201);
    }
}
```

### Creating a Vue Component

```vue
<template>
  <div class="my-component">
    <h1>{{ title }}</h1>
    <button @click="handleClick">Click Me</button>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'

const props = defineProps({
  title: String
})

const count = ref(0)

const doubleCount = computed(() => count.value * 2)

function handleClick() {
  count.value++
}

onMounted(() => {
  console.log('Component mounted')
})
</script>

<style scoped>
.my-component {
  padding: 20px;
}
</style>
```

---

## 🐛 Common Issues & Solutions

### Issue: Workers not processing jobs

```bash
# Check if workers are running
tasklist | findstr "php"

# Check queue table
SELECT * FROM jobs;

# Restart workers
stop-monitoring.bat
start-monitoring.bat
```

### Issue: Database connection failed

```bash
# Check PostgreSQL is running
# Windows: Services → PostgreSQL
# Linux: sudo systemctl status postgresql

# Check .env credentials
cat .env | grep DB_

# Test connection
php artisan tinker
>>> DB::connection()->getPdo();
```

### Issue: Frontend can't connect to backend

```bash
# Check backend is running
curl http://localhost:8000/api/monitors

# Check CORS in backend
# config/cors.php should allow frontend origin

# Check frontend .env
cat ../uptime-frontend/.env
# VITE_BACKEND_URL should be http://localhost:8000/api
```

### Issue: JWT token expired

```javascript
// Backend: Refresh token endpoint
POST /api/auth/refresh

// Frontend: Auto-refresh in axios interceptor
api.interceptors.response.use(
  response => response,
  async error => {
    if (error.response?.status === 401) {
      // Refresh token or redirect to login
    }
  }
)
```

---

## 📊 Performance Tips

```php
// ❌ Bad: N+1 Query
$monitors = Monitor::all();
foreach ($monitors as $monitor) {
    echo $monitor->creator->name; // N queries
}

// ✅ Good: Eager Loading
$monitors = Monitor::with('creator')->get();
foreach ($monitors as $monitor) {
    echo $monitor->creator->name; // 1 query
}

// ❌ Bad: Loading all data
$checks = MonitorCheck::all(); // Memory issue

// ✅ Good: Chunking
MonitorCheck::chunk(100, function ($checks) {
    // Process in batches
});

// ❌ Bad: No caching
$stats = Monitor::selectRaw('COUNT(*) as total')->first();

// ✅ Good: Caching
$stats = Cache::remember('monitor_stats', 300, function () {
    return Monitor::selectRaw('COUNT(*) as total')->first();
});
```

---

## 🔐 Security Checklist

- [ ] Validate all inputs
- [ ] Use parameterized queries
- [ ] Escape output in templates
- [ ] Use HTTPS in production
- [ ] Keep .env secret
- [ ] Use strong passwords
- [ ] Enable CSRF protection
- [ ] Rate limit API endpoints
- [ ] Hash sensitive data
- [ ] Keep dependencies updated

---

## 📞 Quick Help

### Laravel Artisan Commands

```bash
php artisan list              # List all commands
php artisan help migrate      # Help for specific command
php artisan route:list        # List all routes
php artisan queue:work        # Start queue worker
php artisan queue:failed      # List failed jobs
php artisan queue:retry all   # Retry failed jobs
```

### Git Commands

```bash
git status                    # Check status
git add .                     # Stage all changes
git commit -m "message"       # Commit changes
git push                      # Push to remote
git pull                      # Pull from remote
git checkout -b feature/name  # Create branch
git merge feature/name        # Merge branch
```

### Composer Commands

```bash
composer install              # Install dependencies
composer update               # Update dependencies
composer require package      # Add package
composer remove package       # Remove package
composer dump-autoload        # Regenerate autoload
```

### NPM Commands

```bash
npm install                   # Install dependencies
npm run dev                   # Start dev server
npm run build                 # Build for production
npm run preview               # Preview production build
npm install package           # Add package
```

---

**Need more help? Check [RESEARCH_AND_DEVELOPMENT.md](RESEARCH_AND_DEVELOPMENT.md) for detailed guides!**
