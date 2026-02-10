# 🏗️ Perancangan Sistem Telegram Bot - Uptime Monitor

## 📋 Daftar Isi
- [Executive Summary](#executive-summary)
- [Analisis Kebutuhan](#analisis-kebutuhan)
- [Arsitektur Sistem](#arsitektur-sistem)
- [Desain Database](#desain-database)
- [Desain API](#desain-api)
- [Perancangan Fitur](#perancangan-fitur)
- [Technology Stack](#technology-stack)
- [Security Design](#security-design)
- [Scalability & Performance](#scalability--performance)
- [Deployment Architecture](#deployment-architecture)
- [Testing Strategy](#testing-strategy)

---

## Executive Summary

### Tujuan Sistem
Mengembangkan Telegram Bot yang terintegrasi dengan sistem Uptime Monitor untuk:
- **Mengirim notifikasi real-time** saat service down/up
- **Menyediakan interface interaktif** untuk query status monitor
- **Memberikan akses mudah** ke informasi monitoring tanpa login dashboard

### Ruang Lingkup
1. **Interactive Bot**: Command-based interface untuk query data
2. **Alert System**: Push notification otomatis
3. **Webhook Integration**: Real-time processing
4. **Multi-channel Support**: Support multiple notification channels

---

## Analisis Kebutuhan

### 1. Kebutuhan Functional

#### A. Notifikasi Otomatis
| Requirement | Priority | Status |
|-------------|----------|--------|
| Kirim alert saat monitor DOWN | ⚡ Critical | ✅ Implemented |
| Kirim alert saat monitor UP (recovery) | ⚡ Critical | ✅ Implemented |
| Support critical alert (consecutive failures) | 🔥 High | ✅ Implemented |
| Customizable notification format | 📊 Medium | ✅ Implemented |
| Support multiple chat recipients | 📊 Medium | ✅ Implemented |

#### B. Interactive Commands
| Feature | Priority | Status |
|---------|----------|--------|
| `/start` - Welcome & menu | ⚡ Critical | ✅ Implemented |
| `/status` - Overall status | ⚡ Critical | ✅ Implemented |
| `/monitors` - List monitors | 🔥 High | ✅ Implemented |
| `/incidents` - View incidents | 🔥 High | ✅ Implemented |
| `/help` - Documentation | 🔥 High | ✅ Implemented |
| `/monitor {name}` - Detail view | 📊 Medium | ✅ Implemented |
| `/groups` - Group listing | 📊 Medium | ✅ Implemented |
| `/search` - Search monitors | 📊 Medium | ✅ Implemented |
| `/uptime` - Statistics | 📊 Medium | ✅ Implemented |

#### C. Administration
| Feature | Priority | Status |
|---------|----------|--------|
| Auto webhook setup | ⚡ Critical | ✅ Implemented |
| Manual connect via dashboard | 🔥 High | ✅ Implemented |
| Channel enable/disable | 🔥 High | ✅ Implemented |
| Webhook health monitoring | 📊 Medium | ✅ Implemented |

### 2. Kebutuhan Non-Functional

#### Performance
- **Response Time**: < 3 detik untuk command response
- **Notification Delay**: < 5 detik dari detection
- **Concurrent Users**: Support 100+ simultaneous users
- **Message Queue**: Handle 1000+ messages/hour

#### Reliability
- **Uptime**: 99.9% availability
- **Retry Mechanism**: Auto-retry failed notifications
- **Error Handling**: Graceful degradation
- **Logging**: Comprehensive audit trail

#### Security
- **Authentication**: Bot token validation
- **Authorization**: Chat ID verification
- **Data Privacy**: No sensitive data in logs
- **SSL/TLS**: Encrypted communication

---

## Arsitektur Sistem

### 1. High-Level Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                    TELEGRAM BOT SYSTEM                       │
└─────────────────────────────────────────────────────────────┘
                              │
        ┌─────────────────────┼─────────────────────┐
        │                     │                     │
┌───────▼────────┐   ┌────────▼────────┐   ┌───────▼────────┐
│  PRESENTATION  │   │    BUSINESS     │   │  DATA ACCESS   │
│     LAYER      │   │     LOGIC       │   │     LAYER      │
└───────┬────────┘   └────────┬────────┘   └───────┬────────┘
        │                     │                     │
┌───────▼─────────────────────▼─────────────────────▼────────┐
│              EXTERNAL SERVICES & INFRASTRUCTURE              │
└──────────────────────────────────────────────────────────────┘
```

### 2. Layered Architecture

```
┌──────────────────────────────────────────────────────────────┐
│ LAYER 1: EXTERNAL INTERFACE                                  │
├──────────────────────────────────────────────────────────────┤
│  ┌─────────────────┐  ┌─────────────────┐                   │
│  │  Telegram API   │  │  Web Dashboard  │                   │
│  │   (Webhook)     │  │   (Frontend)    │                   │
│  └────────┬────────┘  └────────┬────────┘                   │
└───────────┼──────────────────────┼────────────────────────────┘
            │                      │
┌───────────▼──────────────────────▼────────────────────────────┐
│ LAYER 2: API GATEWAY                                          │
├──────────────────────────────────────────────────────────────┤
│  ┌────────────────────────────────────────────┐              │
│  │   Laravel Routes (routes/api.php)          │              │
│  │   - /api/telegram/webhook                  │              │
│  │   - /api/notification-channels/{id}/connect│              │
│  └────────────────┬───────────────────────────┘              │
└────────────────────┼──────────────────────────────────────────┘
                     │
┌────────────────────▼──────────────────────────────────────────┐
│ LAYER 3: CONTROLLERS                                          │
├──────────────────────────────────────────────────────────────┤
│  ┌───────────────────────────┐  ┌──────────────────────────┐ │
│  │ TelegramWebhookController │  │ NotificationChannel      │ │
│  │  - webhook()              │  │   Controller             │ │
│  │  - handleCommand()        │  │  - connectTelegram()     │ │
│  │  - handleCallback()       │  │  - store()               │ │
│  └──────────┬────────────────┘  └──────────┬───────────────┘ │
└─────────────┼────────────────────────────────┼─────────────────┘
              │                                │
┌─────────────▼────────────────────────────────▼─────────────────┐
│ LAYER 4: BUSINESS LOGIC                                        │
├──────────────────────────────────────────────────────────────┤
│  ┌──────────────────┐  ┌──────────────────┐                  │
│  │  Jobs            │  │  Services         │                  │
│  │  - SendNotif     │  │  - MonitorService │                  │
│  │  - MonitorCheck  │  │  - IncidentSvc    │                  │
│  └────────┬─────────┘  └────────┬──────────┘                  │
│           │                     │                              │
│  ┌────────▼─────────────────────▼──────────┐                  │
│  │      Queue System (Redis)               │                  │
│  │      - notifications queue              │                  │
│  │      - monitoring queue                 │                  │
│  └─────────────────────────────────────────┘                  │
└──────────────────────────────────────────────────────────────┘
              │
┌─────────────▼──────────────────────────────────────────────────┐
│ LAYER 5: DATA ACCESS                                           │
├──────────────────────────────────────────────────────────────┤
│  ┌────────────────┐  ┌────────────────┐  ┌──────────────┐    │
│  │ Eloquent ORM   │  │  Observers     │  │  Migrations  │    │
│  │  - Monitor     │  │  - ChannelObs  │  │              │    │
│  │  - Incident    │  │                │  │              │    │
│  │  - NotifChan   │  │                │  │              │    │
│  └────────┬───────┘  └────────────────┘  └──────────────┘    │
└───────────┼────────────────────────────────────────────────────┘
            │
┌───────────▼────────────────────────────────────────────────────┐
│ LAYER 6: DATABASE                                              │
├──────────────────────────────────────────────────────────────┤
│           ┌──────────────────────────┐                         │
│           │   MySQL Database         │                         │
│           │   - monitors             │                         │
│           │   - incidents            │                         │
│           │   - notification_channels│                         │
│           │   - jobs / failed_jobs   │                         │
│           └──────────────────────────┘                         │
└──────────────────────────────────────────────────────────────┘
            │
┌───────────▼────────────────────────────────────────────────────┐
│ LAYER 7: EXTERNAL SERVICES                                     │
├──────────────────────────────────────────────────────────────┤
│  ┌─────────────┐  ┌──────────────┐  ┌────────────────────┐   │
│  │ Telegram    │  │  HTTP Client │  │  Monitoring        │   │
│  │ Bot API     │  │  (Guzzle)    │  │  Targets           │   │
│  └─────────────┘  └──────────────┘  └────────────────────┘   │
└──────────────────────────────────────────────────────────────┘
```

### 3. Component Interaction Diagram

```
┌─────────┐                                      ┌──────────────┐
│  User   │                                      │   Monitor    │
│ (Tele)  │                                      │   Checker    │
└────┬────┘                                      └──────┬───────┘
     │                                                  │
     │ /status                                          │ Cron/Schedule
     ├──────────────────────────┐                       │
     │                          │                       │ Check targets
     │                     ┌────▼───────┐               │
     │                     │  Telegram  │               │
     │                     │    API     │               ├──────────────┐
     │                     └────┬───────┘               │              │
     │                          │                       │              │
     │                          │ Webhook               │         ┌────▼─────┐
     │                          │ POST                  │         │  Target  │
     │                     ┌────▼────────────┐          │         │  Server  │
     │                     │   Laravel App   │          │         └──────────┘
     │                     │   (Webhook)     │          │
     │                     └────┬────────────┘          │
     │                          │                       │
     │                     ┌────▼────────────┐          │
     │                     │  Controller     │          │ Status change
     │                     │  - Parse cmd    │          │
     │                     │  - Query DB     │◄─────────┤
     │                     └────┬────────────┘          │
     │                          │                       │
     │                     ┌────▼────────────┐     ┌────▼────────┐
     │                     │   Database      │     │    Queue    │
     │                     │   - Monitors    │     │  (Notif)    │
     │                     │   - Incidents   │     └────┬────────┘
     │                     └────┬────────────┘          │
     │                          │                       │
     │                     ┌────▼────────────┐          │
     │                     │  Build Response │          │
     │                     │  - Format MD    │     ┌────▼────────┐
     │                     │  - Add keyboard │     │   Worker    │
     │                     └────┬────────────┘     │  (Process)  │
     │                          │                  └────┬────────┘
     │                          │                       │
     │                     ┌────▼────────────┐          │
     │                     │  Telegram API   │◄─────────┤
     │                     │  sendMessage    │          │
     │                     └────┬────────────┘          │
     │                          │                       │
     │◄─────────────────────────┤                       │
     │  Formatted response      │                       │
     │                          │                       │
     │◄─────────────────────────┴───────────────────────┤
     │  Push notification (alert)
     │
```

---

## Desain Database

### 1. Entity Relationship Diagram

```
┌──────────────────────────┐
│      monitors            │
├──────────────────────────┤
│ PK  id                   │
│     name                 │
│     type (http/tcp/...)  │
│     target               │
│     enabled              │
│     last_status          │
│     last_checked_at      │
│     uptime_percentage    │
│     consecutive_failures │
│     group_name           │
│     created_at           │
│     updated_at           │
└──────┬───────────────────┘
       │
       │ 1:N
       │
       ├─────────────────────────┐
       │                         │
┌──────▼───────────────┐  ┌──────▼────────────────────┐
│   incidents          │  │ monitor_notification_     │
├──────────────────────┤  │ channel                   │
│ PK  id               │  ├───────────────────────────┤
│ FK  monitor_id       │  │ FK  monitor_id            │
│     status (open/    │  │ FK  notification_channel_ │
│          resolved)   │  │     id                    │
│     started_at       │  │     created_at            │
│     resolved_at      │  └──────┬────────────────────┘
│     error_message    │         │
│     created_at       │         │ N:1
│     updated_at       │         │
└──────────────────────┘  ┌──────▼────────────────────┐
                          │ notification_channels     │
                          ├───────────────────────────┤
                          │ PK  id                    │
                          │     name                  │
                          │     type (telegram/       │
                          │          discord/etc)     │
                          │     config (JSON)         │
                          │     │ - bot_token         │
                          │     │ - chat_id           │
                          │     is_enabled            │
                          │     created_at            │
                          │     updated_at            │
                          └───────────────────────────┘
```

### 2. Table Schemas

#### Table: `notification_channels`
```sql
CREATE TABLE notification_channels (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    type ENUM('telegram', 'discord', 'slack', 'webhook') NOT NULL,
    config JSON NOT NULL COMMENT 'Channel-specific config',
    is_enabled BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    INDEX idx_type (type),
    INDEX idx_enabled (is_enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Config JSON Structure for Telegram:**
```json
{
  "bot_token": "1234567890:ABCdefGHIjklMNOpqrsTUVwxyz",
  "chat_id": "987654321"
}
```

#### Table: `monitor_notification_channel` (Pivot)
```sql
CREATE TABLE monitor_notification_channel (
    monitor_id BIGINT UNSIGNED NOT NULL,
    notification_channel_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NULL,
    
    PRIMARY KEY (monitor_id, notification_channel_id),
    FOREIGN KEY (monitor_id) REFERENCES monitors(id) ON DELETE CASCADE,
    FOREIGN KEY (notification_channel_id) REFERENCES notification_channels(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 3. Queue Tables

#### Table: `jobs`
```sql
CREATE TABLE jobs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    queue VARCHAR(255) NOT NULL,
    payload LONGTEXT NOT NULL,
    attempts TINYINT UNSIGNED NOT NULL,
    reserved_at INT UNSIGNED NULL,
    available_at INT UNSIGNED NOT NULL,
    created_at INT UNSIGNED NOT NULL,
    
    INDEX idx_queue (queue),
    INDEX idx_reserved_at (reserved_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### Table: `failed_jobs`
```sql
CREATE TABLE failed_jobs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid VARCHAR(255) UNIQUE NOT NULL,
    connection TEXT NOT NULL,
    queue TEXT NOT NULL,
    payload LONGTEXT NOT NULL,
    exception LONGTEXT NOT NULL,
    failed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_failed_at (failed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## Desain API

### 1. Webhook Endpoint

#### POST `/api/telegram/webhook`

**Purpose:** Menerima webhook dari Telegram Bot API

**Headers:**
```
Content-Type: application/json
```

**Request Body (Message):**
```json
{
  "update_id": 123456789,
  "message": {
    "message_id": 123,
    "from": {
      "id": 987654321,
      "is_bot": false,
      "first_name": "John",
      "username": "john_doe"
    },
    "chat": {
      "id": 987654321,
      "type": "private"
    },
    "date": 1707091200,
    "text": "/status"
  }
}
```

**Request Body (Callback Query):**
```json
{
  "update_id": 123456790,
  "callback_query": {
    "id": "callback_123",
    "from": { ... },
    "message": { ... },
    "data": "status"
  }
}
```

**Response:**
```json
{
  "ok": true
}
```

**Error Response:**
```json
{
  "ok": false,
  "error": "Error message"
}
```

**Processing Flow:**
1. Validate request from Telegram (signature check)
2. Parse update type (message/callback_query)
3. Extract chat_id and command/callback data
4. Route to appropriate handler
5. Execute business logic
6. Send response via Telegram API
7. Return HTTP 200 OK to Telegram

### 2. Channel Management Endpoint

#### POST `/api/notification-channels/{id}/connect`

**Purpose:** Setup dan test Telegram bot connection

**Headers:**
```
Content-Type: application/json
Authorization: Bearer {token}
```

**Response:**
```json
{
  "success": true,
  "message": "Bot connected successfully!",
  "data": {
    "bot_username": "uptime_monitor_bot",
    "webhook_url": "https://domain.com/api/telegram/webhook",
    "webhook_set": true,
    "pending_updates": 0,
    "commands_ready": true,
    "last_error": null
  }
}
```

**Error Response:**
```json
{
  "success": false,
  "message": "Invalid bot token"
}
```

### 3. Telegram Bot API Integration

#### POST `https://api.telegram.org/bot{token}/sendMessage`

**Purpose:** Mengirim message ke user

**Request:**
```json
{
  "chat_id": 987654321,
  "text": "**Monitor Down**\n\nAPI Server is DOWN!",
  "parse_mode": "Markdown",
  "disable_web_page_preview": true,
  "reply_markup": {
    "inline_keyboard": [
      [
        {"text": "📊 Status", "callback_data": "status"},
        {"text": "❓ Help", "callback_data": "help"}
      ]
    ]
  }
}
```

**Response:**
```json
{
  "ok": true,
  "result": {
    "message_id": 456,
    "from": { ... },
    "chat": { ... },
    "date": 1707091200,
    "text": "..."
  }
}
```

#### POST `https://api.telegram.org/bot{token}/setWebhook`

**Purpose:** Setup webhook URL

**Request:**
```json
{
  "url": "https://domain.com/api/telegram/webhook",
  "allowed_updates": ["message", "callback_query"],
  "drop_pending_updates": false
}
```

#### GET `https://api.telegram.org/bot{token}/getWebhookInfo`

**Purpose:** Check webhook status

**Response:**
```json
{
  "ok": true,
  "result": {
    "url": "https://domain.com/api/telegram/webhook",
    "has_custom_certificate": false,
    "pending_update_count": 0,
    "last_error_date": 0,
    "max_connections": 40
  }
}
```

---

## Perancangan Fitur

### 1. Command System Design

#### Command Structure
```php
interface TelegramCommand {
    public function handle(string $chatId, string $args): void;
    public function getDescription(): string;
    public function getSyntax(): string;
}
```

#### Command Registry
```php
class CommandRegistry {
    protected array $commands = [
        '/start' => StartCommand::class,
        '/status' => StatusCommand::class,
        '/monitors' => MonitorsCommand::class,
        // ...
    ];
    
    public function execute(string $command, string $chatId, string $args): void {
        $handler = $this->commands[$command] ?? UnknownCommand::class;
        (new $handler)->handle($chatId, $args);
    }
}
```

### 2. Notification Queue Design

#### Priority Levels
```php
enum NotificationPriority {
    case CRITICAL;  // Consecutive failures >= 3
    case HIGH;      // Monitor down
    case NORMAL;    // Monitor up, test
    case LOW;       // Informational
}
```

#### Queue Configuration
```php
// config/queue.php
'connections' => [
    'redis' => [
        'notifications' => [
            'connection' => 'default',
            'queue' => 'notifications',
            'retry_after' => 90,
            'block_for' => null,
        ],
    ],
],
```

#### Job Structure
```php
class SendNotification implements ShouldQueue {
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    public $tries = 3;
    public $timeout = 30;
    public $backoff = [10, 30, 60]; // Retry delays
    
    public function __construct(
        public Monitor $monitor,
        public string $type,
        public array $channelIds,
        public ?Incident $incident = null,
    ) {}
    
    public function handle(): void {
        // Send to all channels
    }
    
    public function failed(\Throwable $exception): void {
        // Log failed notification
    }
}
```

### 3. Webhook Auto-Setup Design

#### Observer Pattern
```php
class NotificationChannelObserver {
    public function created(NotificationChannel $channel): void {
        if ($channel->type === 'telegram' && $channel->is_enabled) {
            $this->setupTelegramWebhook($channel);
        }
    }
    
    public function updated(NotificationChannel $channel): void {
        if ($channel->type === 'telegram' && $channel->is_enabled) {
            $this->setupTelegramWebhook($channel);
        }
    }
    
    private function setupTelegramWebhook(NotificationChannel $channel): void {
        // Auto-configure webhook
        $webhookUrl = config('app.url') . '/api/telegram/webhook';
        
        // Call Telegram setWebhook API
        // Send test message
    }
}
```

### 4. Message Formatting Design

#### Template System
```php
class MessageFormatter {
    public function formatDownAlert(Monitor $monitor, ?Incident $incident): string {
        return <<<MARKDOWN
        🚨 **Monitor Down Alert**
        
        **{$monitor->name}** is DOWN!
        
        📂 **Group:** {$monitor->group_name}
        🎯 **Target:** {$monitor->target}
        ⏰ **Time:** {$this->formatTime(now())}
        📊 **Incident ID:** {$incident?->id}
        🔧 **Monitor Type:** {$monitor->type}
        MARKDOWN;
    }
    
    public function formatUpAlert(Monitor $monitor, ?Incident $incident): string {
        $downtime = $incident ? now()->diffForHumans($incident->started_at) : 'N/A';
        
        return <<<MARKDOWN
        ✅ **Monitor Recovered**
        
        **{$monitor->name}** is back UP!
        
        🎯 **Target:** {$monitor->target}
        ⏰ **Recovered at:** {$this->formatTime(now())}
        ⏱️ **Downtime:** {$downtime}
        MARKDOWN;
    }
}
```

### 5. Inline Keyboard Design

#### Keyboard Builder
```php
class KeyboardBuilder {
    protected array $buttons = [];
    
    public function addButton(string $text, string $callbackData): self {
        $this->buttons[] = ['text' => $text, 'callback_data' => $callbackData];
        return $this;
    }
    
    public function addRow(): self {
        $this->buttons[] = []; // New row
        return $this;
    }
    
    public function build(): array {
        return ['inline_keyboard' => $this->formatRows()];
    }
    
    private function formatRows(): array {
        // Group buttons into rows
    }
}

// Usage
$keyboard = (new KeyboardBuilder())
    ->addButton('📊 Status', 'status')
    ->addButton('📋 Monitors', 'monitors')
    ->addRow()
    ->addButton('🚨 Incidents', 'incidents')
    ->addButton('❓ Help', 'help')
    ->build();
```

---

## Technology Stack

### Backend Framework
```yaml
Framework: Laravel 10.x
Language: PHP 8.2+
Architecture: MVC + Service Layer

Core Components:
  - Eloquent ORM: Database abstraction
  - Queue System: Background job processing
  - Event/Observer: Reactive programming
  - HTTP Client: External API calls
  - Validation: Input sanitization
  - Logging: Monolog
```

### Database
```yaml
Primary Database: MySQL 8.0+
  - Stores: Monitors, Incidents, Channels
  - Transactions: ACID compliance
  - Indexing: Optimized queries

Cache/Queue: Redis 7.x
  - Queue: Job processing
  - Cache: Session, API responses
  - Pub/Sub: Real-time events
```

### External Services
```yaml
Telegram Bot API:
  - Version: Bot API 6.0+
  - Protocol: HTTPS
  - Format: JSON

HTTP Client:
  - Library: Guzzle 7.x
  - Features: 
    - Timeout handling
    - Retry logic
    - SSL verification
```

### Development Tools
```yaml
Version Control: Git
Package Manager: Composer
Task Runner: Laravel Artisan
API Testing: Postman / Insomnia
Code Style: PSR-12
Debugging: Laravel Telescope
```

### Infrastructure
```yaml
Web Server: 
  - Apache 2.4+ / Nginx 1.20+
  - PHP-FPM

Process Manager:
  - Queue Worker: Laravel Queue Worker
  - Supervisor: Process monitoring

Deployment:
  - Method: Manual / CI/CD
  - Environment: .env configuration
  - Assets: Public directory
```

---

## Security Design

### 1. Authentication & Authorization

#### Bot Token Security
```php
// Store in .env, never commit
TELEGRAM_BOT_TOKEN=1234567890:ABCdefGHIjklMNOpqrsTUVwxyz

// Encrypt in database
$channel->config = encrypt([
    'bot_token' => $request->bot_token,
    'chat_id' => $request->chat_id,
]);

// Decrypt when using
$config = decrypt($channel->config);
```

#### Webhook Validation
```php
// Verify requests come from Telegram
class TelegramWebhookMiddleware {
    public function handle($request, Closure $next) {
        // Check X-Telegram-Bot-Api-Secret-Token if set
        $secretToken = config('services.telegram.webhook_secret');
        
        if ($secretToken && $request->header('X-Telegram-Bot-Api-Secret-Token') !== $secretToken) {
            abort(403, 'Invalid webhook token');
        }
        
        return $next($request);
    }
}
```

#### Chat ID Verification
```php
// Only respond to authorized chat IDs
class AuthorizedChatMiddleware {
    public function handle($chatId, Closure $next) {
        $channel = NotificationChannel::where('type', 'telegram')
            ->where('is_enabled', true)
            ->whereJsonContains('config->chat_id', (string)$chatId)
            ->first();
        
        if (!$channel) {
            Log::warning('Unauthorized chat ID', ['chat_id' => $chatId]);
            return;
        }
        
        return $next($chatId);
    }
}
```

### 2. Data Protection

#### Sensitive Data Handling
```php
// Never log sensitive data
Log::info('Sending message', [
    'chat_id' => $chatId,
    'bot_token_length' => strlen($botToken), // Don't log token itself
    'message_length' => strlen($message),
]);

// Sanitize input
$command = strip_tags($request->input('message.text'));
$args = htmlspecialchars($args);
```

#### SQL Injection Prevention
```php
// Use Eloquent ORM (parameterized queries)
Monitor::where('name', $name)->first(); // Safe

// Never use raw queries with user input
// DB::raw("SELECT * WHERE name = '{$name}'"); // DANGEROUS
```

### 3. Rate Limiting

#### API Rate Limiting
```php
// routes/api.php
Route::middleware(['throttle:60,1'])->group(function () {
    Route::post('/telegram/webhook', [TelegramWebhookController::class, 'webhook']);
});

// config/app.php
'telegram_throttle' => env('TELEGRAM_THROTTLE', '30:1'), // 30 requests per minute
```

#### Telegram API Limits
```php
class TelegramRateLimiter {
    // Telegram limits: 30 messages/second per chat
    private const MAX_MESSAGES_PER_SECOND = 30;
    
    public function checkLimit(string $chatId): bool {
        $key = "telegram:rate:{$chatId}";
        $count = Cache::get($key, 0);
        
        if ($count >= self::MAX_MESSAGES_PER_SECOND) {
            return false; // Rate limited
        }
        
        Cache::put($key, $count + 1, now()->addSecond());
        return true;
    }
}
```

### 4. Error Handling

#### Graceful Degradation
```php
try {
    $this->sendMessage($chatId, $message);
} catch (TelegramApiException $e) {
    Log::error('Telegram API error', [
        'error' => $e->getMessage(),
        'chat_id' => $chatId,
    ]);
    
    // Don't expose error to user
    // Queue for retry
    SendNotification::dispatch(...)->delay(now()->addMinutes(5));
}
```

#### Information Disclosure Prevention
```php
// Don't expose stack traces in production
if (app()->environment('production')) {
    return response()->json([
        'ok' => false,
        'message' => 'Internal server error'
    ], 500);
} else {
    return response()->json([
        'ok' => false,
        'message' => $exception->getMessage(),
        'trace' => $exception->getTraceAsString()
    ], 500);
}
```

---

## Scalability & Performance

### 1. Horizontal Scaling

```
┌────────────────────────────────────────────┐
│          Load Balancer (Nginx)             │
└────────┬──────────────┬────────────────────┘
         │              │
    ┌────▼────┐    ┌────▼────┐
    │ App #1  │    │ App #2  │
    │ Worker  │    │ Worker  │
    └────┬────┘    └────┬────┘
         │              │
         └──────┬───────┘
                │
         ┌──────▼──────┐
         │   Redis     │
         │   (Queue)   │
         └──────┬──────┘
                │
         ┌──────▼──────┐
         │   MySQL     │
         │  (Master)   │
         └─────────────┘
```

### 2. Queue Optimization

#### Worker Configuration
```bash
# supervisor-uptime-monitor.conf
[program:uptime-queue-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/artisan queue:work redis --queue=notifications,monitoring --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
numprocs=4  # Multiple workers
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/queue-worker.log
stopwaitsecs=3600
```

#### Queue Priority
```php
// High priority for critical alerts
SendNotification::dispatch($monitor, 'critical_down', $channels, $incident)
    ->onQueue('notifications-high');

// Normal priority for up alerts
SendNotification::dispatch($monitor, 'up', $channels, $incident)
    ->onQueue('notifications-normal');
```

### 3. Database Optimization

#### Indexing Strategy
```sql
-- Monitors table
CREATE INDEX idx_enabled_status ON monitors(enabled, last_status);
CREATE INDEX idx_last_checked ON monitors(last_checked_at);
CREATE INDEX idx_group_name ON monitors(group_name);

-- Incidents table
CREATE INDEX idx_monitor_status ON incidents(monitor_id, status);
CREATE INDEX idx_started_at ON incidents(started_at);

-- Notification channels
CREATE INDEX idx_type_enabled ON notification_channels(type, is_enabled);
```

#### Query Optimization
```php
// Eager loading to prevent N+1 queries
$monitors = Monitor::with(['notificationChannels', 'latestIncident'])
    ->where('enabled', true)
    ->get();

// Use select to limit columns
$monitors = Monitor::select(['id', 'name', 'type', 'last_status'])
    ->where('enabled', true)
    ->get();

// Chunk large result sets
Monitor::where('enabled', true)->chunk(100, function ($monitors) {
    foreach ($monitors as $monitor) {
        // Process
    }
});
```

### 4. Caching Strategy

#### Response Caching
```php
// Cache monitor list for 1 minute
$monitors = Cache::remember('monitors:list', 60, function () {
    return Monitor::where('enabled', true)->get();
});

// Cache statistics for 5 minutes
$stats = Cache::remember('monitors:stats', 300, function () {
    return [
        'total' => Monitor::count(),
        'up' => Monitor::where('last_status', 'up')->count(),
        'down' => Monitor::where('last_status', 'down')->count(),
    ];
});
```

#### Cache Invalidation
```php
// Clear cache on monitor update
class MonitorObserver {
    public function saved(Monitor $monitor): void {
        Cache::forget('monitors:list');
        Cache::forget('monitors:stats');
    }
}
```

### 5. Performance Monitoring

#### Metrics to Track
```php
// Response time
Log::info('Command processed', [
    'command' => $command,
    'duration_ms' => (microtime(true) - $startTime) * 1000,
]);

// Queue metrics
Log::info('Queue status', [
    'pending_jobs' => Queue::size('notifications'),
    'failed_jobs' => DB::table('failed_jobs')->count(),
]);

// API call metrics
Log::info('Telegram API call', [
    'endpoint' => 'sendMessage',
    'status_code' => $response->status(),
    'duration_ms' => $response->transferStats->getTransferTime() * 1000,
]);
```

---

## Deployment Architecture

### 1. Production Environment

```
┌─────────────────────────────────────────────────────────┐
│                    Internet                              │
└────────────────────┬────────────────────────────────────┘
                     │
                     │ HTTPS (SSL/TLS)
                     │
┌────────────────────▼────────────────────────────────────┐
│              Reverse Proxy (Nginx)                       │
│  - SSL Termination                                       │
│  - Load Balancing                                        │
│  - Static file serving                                   │
└────────────────────┬────────────────────────────────────┘
                     │
         ┌───────────┴───────────┐
         │                       │
┌────────▼──────────┐   ┌────────▼──────────┐
│  App Server #1    │   │  App Server #2    │
│  - PHP-FPM        │   │  - PHP-FPM        │
│  - Laravel App    │   │  - Laravel App    │
│  - Queue Workers  │   │  - Queue Workers  │
└────────┬──────────┘   └────────┬──────────┘
         │                       │
         └───────────┬───────────┘
                     │
         ┌───────────┴───────────┐
         │                       │
┌────────▼──────────┐   ┌────────▼──────────┐
│   Redis Server    │   │   MySQL Server    │
│   - Queue         │   │   - Database      │
│   - Cache         │   │   - Replication   │
└───────────────────┘   └───────────────────┘
```

### 2. Environment Configuration

#### `.env.production`
```bash
APP_NAME="Uptime Monitor"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://monitor.yourdomain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=uptime_monitor
DB_USERNAME=uptime_user
DB_PASSWORD=secure_password

QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=redis_password
REDIS_PORT=6379

TELEGRAM_BOT_TOKEN=your_bot_token_here
TELEGRAM_WEBHOOK_SECRET=random_secret_key

LOG_CHANNEL=daily
LOG_LEVEL=info
```

### 3. Web Server Configuration

#### Nginx Configuration
```nginx
server {
    listen 80;
    server_name monitor.yourdomain.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name monitor.yourdomain.com;
    root /var/www/uptime-monitor/public;

    ssl_certificate /etc/ssl/certs/monitor.crt;
    ssl_certificate_key /etc/ssl/private/monitor.key;
    ssl_protocols TLSv1.2 TLSv1.3;

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

### 4. Supervisor Configuration

#### Queue Worker
```ini
[program:uptime-queue-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/uptime-monitor/artisan queue:work redis --queue=notifications,monitoring --sleep=3 --tries=3
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
numprocs=2
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/uptime-monitor/storage/logs/worker.log
stopwaitsecs=3600
```

#### Scheduler
```ini
[program:uptime-scheduler]
process_name=%(program_name)s
command=/bin/bash -c "while [ true ]; do (php /var/www/uptime-monitor/artisan schedule:run --verbose --no-interaction &); sleep 60; done"
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/uptime-monitor/storage/logs/scheduler.log
```

### 5. Deployment Checklist

```markdown
## Pre-Deployment
- [ ] Run tests: `php artisan test`
- [ ] Check code style: `./vendor/bin/phpcs`
- [ ] Review security: `composer audit`
- [ ] Optimize autoloader: `composer install --optimize-autoloader --no-dev`
- [ ] Clear development dependencies

## Deployment Steps
- [ ] Pull latest code: `git pull origin main`
- [ ] Install dependencies: `composer install --no-dev`
- [ ] Run migrations: `php artisan migrate --force`
- [ ] Clear caches: `php artisan config:cache`
- [ ] Optimize routes: `php artisan route:cache`
- [ ] Optimize views: `php artisan view:cache`
- [ ] Restart queue workers: `php artisan queue:restart`
- [ ] Restart PHP-FPM: `sudo systemctl restart php8.2-fpm`

## Post-Deployment
- [ ] Test webhook: `curl -X POST https://domain.com/api/telegram/webhook`
- [ ] Check logs: `tail -f storage/logs/laravel.log`
- [ ] Monitor queue: `php artisan queue:monitor`
- [ ] Verify bot commands: Send `/start` in Telegram
- [ ] Test notifications: Trigger test alert
```

---

## Testing Strategy

### 1. Unit Testing

```php
// tests/Unit/NotificationTest.php
class NotificationTest extends TestCase {
    public function test_telegram_message_format() {
        $monitor = Monitor::factory()->create([
            'name' => 'Test API',
            'target' => 'https://api.test.com',
        ]);
        
        $formatter = new MessageFormatter();
        $message = $formatter->formatDownAlert($monitor, null);
        
        $this->assertStringContainsString('Test API', $message);
        $this->assertStringContainsString('DOWN', $message);
        $this->assertStringContainsString('https://api.test.com', $message);
    }
    
    public function test_command_parser() {
        $parser = new CommandParser();
        [$command, $args] = $parser->parse('/monitor API Server');
        
        $this->assertEquals('/monitor', $command);
        $this->assertEquals('API Server', $args);
    }
}
```

### 2. Feature Testing

```php
// tests/Feature/TelegramWebhookTest.php
class TelegramWebhookTest extends TestCase {
    public function test_webhook_receives_command() {
        $payload = [
            'update_id' => 123456789,
            'message' => [
                'message_id' => 1,
                'from' => ['id' => 987654321],
                'chat' => ['id' => 987654321],
                'text' => '/start',
            ],
        ];
        
        $response = $this->postJson('/api/telegram/webhook', $payload);
        
        $response->assertStatus(200)
                 ->assertJson(['ok' => true]);
    }
    
    public function test_send_notification_job() {
        $monitor = Monitor::factory()->create();
        $channel = NotificationChannel::factory()->telegram()->create();
        
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);
        
        SendNotification::dispatch($monitor, 'down', [$channel->id], null);
        
        Http::assertSent(function ($request) {
            return $request->url() == 'https://api.telegram.org/bot.../sendMessage' &&
                   $request['chat_id'] !== null;
        });
    }
}
```

### 3. Integration Testing

```php
// tests/Integration/TelegramBotTest.php
class TelegramBotTest extends TestCase {
    public function test_full_notification_flow() {
        // Setup
        $monitor = Monitor::factory()->create(['last_status' => 'up']);
        $channel = NotificationChannel::factory()->telegram()->create();
        $monitor->notificationChannels()->attach($channel->id);
        
        // Mock Telegram API
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);
        
        // Simulate monitor going down
        $monitor->update(['last_status' => 'down']);
        
        // Trigger notification
        $incident = Incident::factory()->create([
            'monitor_id' => $monitor->id,
            'status' => 'open',
        ]);
        
        SendNotification::dispatchSync($monitor, 'down', [$channel->id], $incident);
        
        // Assert
        Http::assertSentCount(1);
        $this->assertDatabaseHas('incidents', [
            'monitor_id' => $monitor->id,
            'status' => 'open',
        ]);
    }
}
```

### 4. Manual Testing Scenarios

#### Test Case 1: Command Processing
```
Precondition: Bot configured and webhook set
Steps:
1. Open Telegram and find bot
2. Send: /start
3. Verify: Welcome message with inline keyboard
4. Click: Status button
5. Verify: Status report displayed
Expected: All commands respond within 3 seconds
```

#### Test Case 2: Alert Notification
```
Precondition: Monitor configured with Telegram channel
Steps:
1. Simulate monitor going down (disable target server)
2. Wait for check interval (10 seconds)
3. Verify: Telegram alert received
4. Re-enable target server
5. Wait for check interval
6. Verify: Recovery alert received
Expected: Alerts received within 15 seconds of status change
```

#### Test Case 3: Webhook Reconnection
```
Precondition: Bot exists but webhook not set
Steps:
1. Open dashboard
2. Go to Notification Channels
3. Click Connect button on Telegram channel
4. Verify: Success message
5. Send /start in Telegram
6. Verify: Bot responds
Expected: Webhook auto-configured and functional
```

### 5. Load Testing

```php
// tests/Load/TelegramLoadTest.php
class TelegramLoadTest extends TestCase {
    public function test_concurrent_webhooks() {
        $requests = 100;
        $responses = [];
        
        // Simulate 100 concurrent webhook requests
        for ($i = 0; $i < $requests; $i++) {
            $responses[] = $this->postJson('/api/telegram/webhook', [
                'update_id' => $i,
                'message' => [
                    'chat' => ['id' => 123456],
                    'text' => '/status',
                ],
            ]);
        }
        
        // Assert all succeed
        foreach ($responses as $response) {
            $response->assertStatus(200);
        }
    }
}
```

---

## Monitoring & Maintenance

### 1. Health Checks

```php
// app/Http/Controllers/HealthController.php
class HealthController extends Controller {
    public function check(): JsonResponse {
        $checks = [
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
            'telegram' => $this->checkTelegram(),
            'queue' => $this->checkQueue(),
        ];
        
        $healthy = !in_array(false, $checks, true);
        
        return response()->json([
            'status' => $healthy ? 'healthy' : 'unhealthy',
            'checks' => $checks,
            'timestamp' => now(),
        ], $healthy ? 200 : 503);
    }
    
    private function checkTelegram(): bool {
        try {
            $channel = NotificationChannel::where('type', 'telegram')
                ->where('is_enabled', true)
                ->first();
            
            if (!$channel) return false;
            
            $config = decrypt($channel->config);
            $response = Http::timeout(5)
                ->get("https://api.telegram.org/bot{$config['bot_token']}/getMe");
            
            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }
}
```

### 2. Logging Strategy

```php
// config/logging.php
'channels' => [
    'telegram' => [
        'driver' => 'daily',
        'path' => storage_path('logs/telegram.log'),
        'level' => 'info',
        'days' => 14,
    ],
    
    'notifications' => [
        'driver' => 'daily',
        'path' => storage_path('logs/notifications.log'),
        'level' => 'info',
        'days' => 30,
    ],
];

// Usage
Log::channel('telegram')->info('Command received', ['command' => $command]);
Log::channel('notifications')->error('Failed to send', ['error' => $e->getMessage()]);
```

### 3. Metrics Dashboard

```php
// Display queue metrics
php artisan queue:monitor notifications,monitoring --max=100

// Monitor failed jobs
SELECT COUNT(*) FROM failed_jobs WHERE created_at > NOW() - INTERVAL 1 HOUR;

// Check webhook status
curl https://api.telegram.org/bot{TOKEN}/getWebhookInfo
```

---

## Appendix

### A. Glossary

| Term | Definition |
|------|------------|
| **Webhook** | HTTP callback yang dipanggil saat ada event |
| **Inline Keyboard** | Tombol interaktif di bawah message Telegram |
| **Callback Query** | Data yang dikirim saat user klik inline button |
| **Chat ID** | Unique identifier untuk Telegram chat |
| **Bot Token** | Secret key untuk autentikasi bot |
| **Queue** | Antrian job untuk background processing |
| **Observer** | Pattern untuk listen model events |

### B. References

- [Telegram Bot API Documentation](https://core.telegram.org/bots/api)
- [Laravel Queue Documentation](https://laravel.com/docs/10.x/queues)
- [Laravel Events Documentation](https://laravel.com/docs/10.x/events)
- [PHP PSR-12 Coding Standard](https://www.php-fig.org/psr/psr-12/)

### C. Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.0 | 2026-02-05 | Initial system design |

---

**📅 Document Created:** February 5, 2026  
**📝 Version:** 1.0  
**✍️ Author:** System Architecture Team  
**📧 Contact:** For questions, refer to project repository
