# 🤖 Dokumentasi Lengkap Telegram Bot - Input hingga Output

## 📋 Daftar Isi
- [Overview](#overview)
- [Arsitektur Sistem](#arsitektur-sistem)
- [Flow Diagram](#flow-diagram)
- [Input Processing](#input-processing)
- [Command Processing](#command-processing)
- [Notification Processing](#notification-processing)
- [Output Delivery](#output-delivery)
- [Available Commands](#available-commands)

---

## Overview

Telegram Bot pada sistem Uptime Monitor berfungsi untuk:
1. **Menerima perintah dari user** (interactive bot)
2. **Mengirim notifikasi otomatis** (alert system)
3. **Memberikan informasi real-time** tentang status monitor

---

## Arsitektur Sistem

```
┌─────────────────────────────────────────────────────────────┐
│                      TELEGRAM BOT SYSTEM                     │
└─────────────────────────────────────────────────────────────┘
                              │
              ┌───────────────┴───────────────┐
              │                               │
        ┌─────▼──────┐                 ┌──────▼─────┐
        │  INPUT     │                 │   OUTPUT   │
        │  HANDLER   │                 │  DELIVERY  │
        └─────┬──────┘                 └──────▲─────┘
              │                               │
    ┌─────────┴─────────┐           ┌────────┴────────┐
    │                   │           │                 │
┌───▼────┐     ┌────────▼────┐  ┌──▼───────┐  ┌──────▼─────┐
│Commands│     │  Callbacks  │  │  Alerts  │  │   Reports  │
└───┬────┘     └────────┬────┘  └──┬───────┘  └──────┬─────┘
    │                   │           │                 │
    └───────────────────┴───────────┴─────────────────┘
                        │
                ┌───────▼────────┐
                │   CONTROLLER   │
                │  TelegramWebhookController.php
                └───────┬────────┘
                        │
          ┌─────────────┼─────────────┐
          │             │             │
    ┌─────▼─────┐ ┌────▼─────┐ ┌────▼────┐
    │ Database  │ │   Jobs   │ │   API   │
    │  Models   │ │  Queue   │ │Telegram │
    └───────────┘ └──────────┘ └─────────┘
```

---

## Flow Diagram

### 1️⃣ **User Command Flow (Interactive)**

```
User mengetik /start di Telegram
         │
         ▼
┌─────────────────────────────────────────────┐
│  Telegram API mengirim webhook              │
│  POST /api/telegram/webhook                 │
└────────────────┬────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────┐
│  TelegramWebhookController::webhook()       │
│  - Terima request dari Telegram            │
│  - Parse update data                        │
│  - Identify type: message/callback          │
└────────────────┬────────────────────────────┘
                 │
         ┌───────┴────────┐
         │                │
    MESSAGE            CALLBACK
         │                │
         ▼                ▼
┌──────────────────┐  ┌──────────────────┐
│ handleCommand()  │  │ handleCallback() │
│ Parse /command   │  │ Parse button     │
└────────┬─────────┘  └────────┬─────────┘
         │                     │
         └──────────┬──────────┘
                    ▼
        ┌─────────────────────┐
        │  Execute Function   │
        │  - sendStart()      │
        │  - sendStatus()     │
        │  - sendMonitors()   │
        │  - sendIncidents()  │
        │  etc...             │
        └──────────┬──────────┘
                   │
                   ▼
        ┌─────────────────────┐
        │   Query Database    │
        │  - Monitor model    │
        │  - Incident model   │
        │  - Build response   │
        └──────────┬──────────┘
                   │
                   ▼
        ┌─────────────────────┐
        │   sendMessage()     │
        │  Format Markdown    │
        │  Add Inline Keyboard│
        └──────────┬──────────┘
                   │
                   ▼
        ┌─────────────────────┐
        │   Telegram API      │
        │  POST sendMessage   │
        │  bot{token}/        │
        │  sendMessage        │
        └──────────┬──────────┘
                   │
                   ▼
        ┌─────────────────────┐
        │   User Receives     │
        │   📱 Formatted Msg  │
        └─────────────────────┘
```

### 2️⃣ **Automatic Notification Flow**

```
Monitor Check Job Running
         │
         ▼
Monitor Status Changes (DOWN/UP)
         │
         ▼
┌─────────────────────────────────────────────┐
│  MonitorCheck.php                           │
│  - Detect status change                     │
│  - Create/Update Incident                   │
│  - Get notification channels                │
└────────────────┬────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────┐
│  Dispatch SendNotification Job              │
│  - Queue: notifications                     │
│  - Priority based notification              │
└────────────────┬────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────┐
│  SendNotification::handle()                 │
│  - Get enabled channels                     │
│  - Build message based on type              │
│  - Send to each channel                     │
└────────────────┬────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────┐
│  sendTelegram()                             │
│  - Get bot_token & chat_id from config      │
│  - Build Markdown formatted text            │
│  - Call Telegram sendMessage API            │
└────────────────┬────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────┐
│  Telegram API                               │
│  POST api.telegram.org/bot{token}/          │
│       sendMessage                           │
└────────────────┬────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────┐
│  User Receives Alert                        │
│  🚨 Monitor Down Alert                      │
│  ✅ Monitor Recovered                       │
└─────────────────────────────────────────────┘
```

---

## Input Processing

### A. **Webhook Input**

**Endpoint:** `POST /api/telegram/webhook`

**Input Structure:**
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
      "first_name": "John",
      "username": "john_doe",
      "type": "private"
    },
    "date": 1707091200,
    "text": "/start"
  }
}
```

**OR Callback Query:**
```json
{
  "update_id": 123456790,
  "callback_query": {
    "id": "callback_id_123",
    "from": { ... },
    "message": { ... },
    "chat_instance": "...",
    "data": "status"
  }
}
```

### B. **Input Types**

| Type | Trigger | Example |
|------|---------|---------|
| **Command** | User types `/command` | `/start`, `/status` |
| **Callback** | User clicks inline button | `status`, `monitors:1` |
| **Text** | Regular message | Currently not processed |

### C. **Input Validation**

```php
// File: app/Http/Controllers/Api/TelegramWebhookController.php

public function webhook(Request $request): JsonResponse
{
    try {
        $update = $request->all();
        Log::info('Telegram webhook received', ['update' => $update]);

        // Handle regular messages
        if (isset($update['message'])) {
            $message = $update['message'];
            $chatId = $message['chat']['id'];
            $text = $message['text'] ?? '';
            
            // Only process commands (starting with /)
            if (strpos($text, '/') === 0) {
                $this->handleCommand($chatId, $text);
            }
        } 
        // Handle inline keyboard callbacks
        elseif (isset($update['callback_query'])) {
            $callbackQuery = $update['callback_query'];
            $chatId = $callbackQuery['message']['chat']['id'];
            $data = $callbackQuery['data'];
            $callbackId = $callbackQuery['id'];
            
            $this->handleCallback($chatId, $data, $callbackId);
        }

        return response()->json(['ok' => true]);
    } catch (\Exception $e) {
        Log::error('Telegram webhook error', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        return response()->json(['ok' => false], 500);
    }
}
```

---

## Command Processing

### A. **Command Parser**

```php
private function handleCommand(string $chatId, string $command): void
{
    // Parse command and arguments
    $parts = explode(' ', trim($command), 2);
    $cmd = strtolower($parts[0]);
    $args = $parts[1] ?? '';
    
    Log::info('Handling Telegram command', [
        'chat_id' => $chatId, 
        'command' => $cmd, 
        'args' => $args
    ]);

    switch ($cmd) {
        case '/start':
            $this->sendStart($chatId);
            break;
        case '/status':
            $this->sendStatus($chatId);
            break;
        case '/monitors':
            $this->sendMonitors($chatId);
            break;
        case '/incidents':
            $this->sendIncidents($chatId, $args);
            break;
        // ... more commands
    }
}
```

### B. **Callback Parser**

```php
private function handleCallback(string $chatId, string $data, string $callbackId): void
{
    Log::info('Handling callback', ['chat_id' => $chatId, 'data' => $data]);
    
    // Answer callback query to remove loading state
    $this->answerCallback($callbackId);
    
    // Parse callback data (format: action:parameter)
    $parts = explode(':', $data, 2);
    $action = $parts[0];
    $param = $parts[1] ?? '';
    
    switch ($action) {
        case 'status':
            $this->sendStatus($chatId);
            break;
        case 'monitors':
            $this->sendMonitors($chatId);
            break;
        case 'group':
            $this->sendGroupMonitors($chatId, $param);
            break;
        // ... more actions
    }
}
```

### C. **Database Query**

Example: `/status` command
```php
private function sendStatus(string $chatId): void
{
    // Query all enabled monitors
    $monitors = Monitor::where('enabled', true)->get();
    
    if ($monitors->isEmpty()) {
        $this->sendMessage($chatId, "⚠️ Tidak ada monitor yang aktif.");
        return;
    }

    // Count by status
    $upCount = 0;
    $downCount = 0;
    $unknownCount = 0;
    
    foreach ($monitors as $monitor) {
        $status = $monitor->last_status ?? 'unknown';
        
        if ($status === 'up') {
            $upCount++;
        } elseif ($status === 'down') {
            $downCount++;
        } else {
            $unknownCount++;
        }
    }
    
    // Build and send message
    $message = "📊 STATUS MONITOR\n\n";
    $message .= "🟢 UP: {$upCount}\n";
    $message .= "🔴 DOWN: {$downCount}\n";
    $message .= "⚪ UNKNOWN: {$unknownCount}\n";
    
    $this->sendMessage($chatId, $message);
}
```

---

## Notification Processing

### A. **Trigger Event**

Notifikasi otomatis dipicu oleh:

```php
// File: app/Jobs/MonitorCheck.php

if ($statusChanged) {
    // Status changed, send notification
    $notificationType = $isUp ? 'up' : 'down';
    
    // Get notification channels
    $channels = $monitor->notificationChannels;
    
    if ($channels->isNotEmpty()) {
        SendNotification::dispatch(
            $monitor, 
            $notificationType, 
            $channels->pluck('id')->toArray(), 
            $incident
        );
    }
}
```

### B. **Build Message**

```php
// File: app/Jobs/SendNotification.php

protected function buildMessage(): array
{
    $baseInfo = [
        'monitor_name' => $this->monitor->name,
        'group_name' => $this->monitor->group_name ?? null,
        'monitor_type' => $this->monitor->type,
        'target' => $this->monitor->target,
        'timestamp' => now()->toISOString(),
    ];

    switch ($this->type) {
        case 'down':
            return array_merge($baseInfo, [
                'status' => '🔴 DOWN',
                'title' => "🚨 Monitor Down Alert",
                'message' => "**{$this->monitor->name}** is DOWN!\n\n" .
                           "📂 **Group:** {$this->monitor->group_name}\n" .
                           "🎯 **Target:** {$this->monitor->target}\n" .
                           "⏰ **Time:** " . now()->format('Y-m-d H:i:s'),
                'color' => '#ff4757',
            ]);

        case 'up':
            $duration = $this->incident ? 
                now()->diffInSeconds($this->incident->started_at) : 0;
            
            return array_merge($baseInfo, [
                'status' => '🟢 UP',
                'title' => "✅ Monitor Recovered",
                'message' => "**{$this->monitor->name}** is back UP!\n\n" .
                           "🎯 **Target:** {$this->monitor->target}\n" .
                           "⏰ **Recovered at:** " . now()->format('Y-m-d H:i:s') . "\n" .
                           "⏱️ **Downtime:** " . gmdate('H:i:s', $duration),
                'color' => '#2ed573',
            ]);
    }
}
```

### C. **Send to Telegram**

```php
protected function sendTelegram(NotificationChannel $channel, array $message): void
{
    $config = $channel->config;
    $botToken = $config['bot_token'] ?? '';
    $chatId = $config['chat_id'] ?? '';

    if (empty($botToken) || empty($chatId)) {
        throw new Exception("Telegram bot token or chat ID not configured");
    }

    $text = $message['message'];
    
    $response = Http::withOptions([
        'verify' => false,
    ])->timeout(30)
        ->post("https://api.telegram.org/bot{$botToken}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'disable_web_page_preview' => true,
        ]);

    if (!$response->successful()) {
        throw new Exception("Telegram API error: " . $response->body());
    }
}
```

---

## Output Delivery

### A. **Message Formatting**

**Markdown Support:**
- `**bold text**` → **bold text**
- `*italic*` → *italic*
- `` `code` `` → `code`
- `[link](url)` → [link](url)

**Example Output:**
```markdown
🚨 Monitor Down Alert

**Production API** is DOWN!

📂 **Group:** Production
🎯 **Target:** https://api.example.com
⏰ **Time:** 2026-02-05 14:30:00
📊 **Incident ID:** 123
```

### B. **Inline Keyboard**

```php
$keyboard = [
    'inline_keyboard' => [
        [
            ['text' => '📊 Status', 'callback_data' => 'status'],
            ['text' => '📋 Monitors', 'callback_data' => 'monitors'],
        ],
        [
            ['text' => '🚨 Incidents', 'callback_data' => 'incidents'],
            ['text' => '❓ Help', 'callback_data' => 'help'],
        ],
    ]
];

$this->sendMessage($chatId, $message, $keyboard);
```

**Result in Telegram:**
```
╔══════════════════════════╗
║  🤖 UPTIME MONITOR BOT   ║
╚══════════════════════════╝

[📊 Status] [📋 Monitors]
[🚨 Incidents] [❓ Help]
```

### C. **API Request**

```php
private function sendMessage(string $chatId, string $text, ?array $keyboard = null): void
{
    // Get bot token
    $channel = NotificationChannel::where('type', 'telegram')
        ->where('is_enabled', true)
        ->first();

    if (!$channel) {
        return;
    }

    $config = is_string($channel->config) 
        ? json_decode($channel->config, true) 
        : $channel->config;
    $botToken = $config['bot_token'] ?? '';

    // Build payload
    $payload = [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'Markdown',
        'disable_web_page_preview' => true,
    ];
    
    if ($keyboard !== null) {
        $payload['reply_markup'] = json_encode($keyboard);
    }
    
    // Send to Telegram
    $response = Http::withOptions(['verify' => false])
        ->timeout(30)
        ->post("https://api.telegram.org/bot{$botToken}/sendMessage", $payload);

    if (!$response->successful()) {
        Log::error('Failed to send Telegram message', [
            'status' => $response->status(),
            'response' => $response->body()
        ]);
    }
}
```

---

## Available Commands

### 📊 Monitoring Commands

| Command | Fungsi | Output |
|---------|--------|--------|
| `/start` | Welcome message & menu | Menu utama dengan inline keyboard |
| `/status` | Lihat status semua monitor | Summary UP/DOWN/UNKNOWN |
| `/monitors` | Daftar semua monitor | List semua monitor dengan status |
| `/monitor {nama}` | Detail monitor tertentu | Info lengkap 1 monitor |
| `/groups` | Daftar group monitor | List semua group |
| `/group {nama}` | Monitor dalam group | List monitor per group |
| `/search {keyword}` | Cari monitor | Monitor yang match keyword |

### 🚨 Incident Commands

| Command | Fungsi | Output |
|---------|--------|--------|
| `/incidents` | 10 incident terbaru | List incident terbaru |
| `/incidents open` | Incident masih aktif | List incident open |
| `/incidents resolved` | Incident sudah resolved | List incident resolved |
| `/incidents today` | Incident hari ini | List incident hari ini |

### 📈 Statistics Commands

| Command | Fungsi | Output |
|---------|--------|--------|
| `/uptime` | Statistik uptime | Uptime % semua monitor |
| `/ping` | Test bot connection | Pong! dengan timestamp |
| `/help` | Panduan lengkap | List semua command |

### ⚙️ Subscription Commands

| Command | Fungsi | Output |
|---------|--------|--------|
| `/subscribe` | Cara subscribe notif | Instruksi setup |
| `/unsubscribe` | Cara unsubscribe | Instruksi disable |

---

## Contoh Flow Lengkap

### Scenario 1: User Request Status

```
┌──────────────────────────────────────────────────────┐
│ 1. USER ACTION                                       │
│    User mengetik: /status                            │
└────────────────────┬─────────────────────────────────┘
                     │
┌────────────────────▼─────────────────────────────────┐
│ 2. TELEGRAM API                                      │
│    POST /api/telegram/webhook                        │
│    {                                                 │
│      "message": {                                    │
│        "chat": {"id": 123456},                       │
│        "text": "/status"                             │
│      }                                               │
│    }                                                 │
└────────────────────┬─────────────────────────────────┘
                     │
┌────────────────────▼─────────────────────────────────┐
│ 3. WEBHOOK CONTROLLER                                │
│    TelegramWebhookController::webhook()              │
│    - Detect message type                             │
│    - Extract chatId: 123456                          │
│    - Extract text: "/status"                         │
│    - Call handleCommand(123456, "/status")           │
└────────────────────┬─────────────────────────────────┘
                     │
┌────────────────────▼─────────────────────────────────┐
│ 4. COMMAND HANDLER                                   │
│    handleCommand()                                   │
│    - Parse: cmd = "/status", args = ""               │
│    - Switch case: /status                            │
│    - Call sendStatus(123456)                         │
└────────────────────┬─────────────────────────────────┘
                     │
┌────────────────────▼─────────────────────────────────┐
│ 5. DATABASE QUERY                                    │
│    sendStatus()                                      │
│    - Query: Monitor::where('enabled', true)->get()   │
│    - Result: 15 monitors                             │
│    - Count: UP=12, DOWN=2, UNKNOWN=1                 │
└────────────────────┬─────────────────────────────────┘
                     │
┌────────────────────▼─────────────────────────────────┐
│ 6. BUILD MESSAGE                                     │
│    Format Markdown:                                  │
│    ╔══════════════════════════╗                      │
│    ║   📊 STATUS MONITOR      ║                      │
│    ╚══════════════════════════╝                      │
│                                                      │
│    🟢 UP: 12 monitors                                │
│    🔴 DOWN: 2 monitors                               │
│    ⚪ UNKNOWN: 1 monitor                             │
│                                                      │
│    [Details...] (dengan inline keyboard)            │
└────────────────────┬─────────────────────────────────┘
                     │
┌────────────────────▼─────────────────────────────────┐
│ 7. SEND TO TELEGRAM                                  │
│    sendMessage(123456, message, keyboard)            │
│    POST api.telegram.org/bot{TOKEN}/sendMessage      │
│    {                                                 │
│      "chat_id": 123456,                              │
│      "text": "...",                                  │
│      "parse_mode": "Markdown",                       │
│      "reply_markup": {...}                           │
│    }                                                 │
└────────────────────┬─────────────────────────────────┘
                     │
┌────────────────────▼─────────────────────────────────┐
│ 8. TELEGRAM DELIVERS                                 │
│    User receives formatted message                   │
│    📱 With clickable buttons                         │
└──────────────────────────────────────────────────────┘
```

### Scenario 2: Automatic Alert

```
┌──────────────────────────────────────────────────────┐
│ 1. MONITOR CHECK                                     │
│    MonitorCheck Job (Queue worker)                   │
│    - Check monitor target                            │
│    - Detect: Status changed from UP to DOWN          │
└────────────────────┬─────────────────────────────────┘
                     │
┌────────────────────▼─────────────────────────────────┐
│ 2. CREATE INCIDENT                                   │
│    Incident::create([                                │
│      'monitor_id' => 1,                              │
│      'status' => 'open',                             │
│      'started_at' => now()                           │
│    ])                                                │
└────────────────────┬─────────────────────────────────┘
                     │
┌────────────────────▼─────────────────────────────────┐
│ 3. GET CHANNELS                                      │
│    $channels = $monitor->notificationChannels;       │
│    Result: [Telegram Channel #1, Discord #2]         │
└────────────────────┬─────────────────────────────────┘
                     │
┌────────────────────▼─────────────────────────────────┐
│ 4. DISPATCH NOTIFICATION JOB                         │
│    SendNotification::dispatch(                       │
│      $monitor, 'down', [1, 2], $incident             │
│    )                                                 │
│    → Queued to 'notifications' queue                 │
└────────────────────┬─────────────────────────────────┘
                     │
┌────────────────────▼─────────────────────────────────┐
│ 5. PROCESS NOTIFICATION                              │
│    SendNotification::handle()                        │
│    - Get enabled channels                            │
│    - Build message for 'down' type                   │
│    - Loop each channel                               │
└────────────────────┬─────────────────────────────────┘
                     │
┌────────────────────▼─────────────────────────────────┐
│ 6. BUILD DOWN MESSAGE                                │
│    buildMessage()                                    │
│    Return:                                           │
│    {                                                 │
│      "status": "🔴 DOWN",                            │
│      "title": "🚨 Monitor Down Alert",               │
│      "message": "**API Server** is DOWN!\n\n..."     │
│    }                                                 │
└────────────────────┬─────────────────────────────────┘
                     │
┌────────────────────▼─────────────────────────────────┐
│ 7. SEND TELEGRAM                                     │
│    sendTelegram($channel, $message)                  │
│    - Get bot_token & chat_id from config             │
│    - POST to Telegram API                            │
└────────────────────┬─────────────────────────────────┘
                     │
┌────────────────────▼─────────────────────────────────┐
│ 8. TELEGRAM API REQUEST                              │
│    POST api.telegram.org/bot{TOKEN}/sendMessage      │
│    {                                                 │
│      "chat_id": "987654321",                         │
│      "text": "🚨 Monitor Down Alert\n\n**API...",   │
│      "parse_mode": "Markdown"                        │
│    }                                                 │
└────────────────────┬─────────────────────────────────┘
                     │
┌────────────────────▼─────────────────────────────────┐
│ 9. USER RECEIVES ALERT                               │
│    📱 Notification appears instantly                 │
│    🚨 Monitor Down Alert                             │
│    **API Server** is DOWN!                           │
│    🎯 Target: https://api.example.com                │
│    ⏰ Time: 2026-02-05 14:30:00                      │
└──────────────────────────────────────────────────────┘
```

---

## 🔑 Key Points

1. **Input dari User** → Telegram API → Webhook Controller
2. **Processing** → Parse command/callback → Query database → Build response
3. **Output ke User** → Format Markdown → Send via Telegram API → Delivered

4. **Automatic Alerts** → Monitor check → Status change → Queue job → Build message → Send to all channels

5. **Real-time** → Webhook untuk command interaktif, Queue untuk notifikasi otomatis

6. **Logging** → Semua aktivitas di-log untuk debugging dan audit

---

## 📚 File References

| Component | File Path |
|-----------|-----------|
| Webhook Handler | `app/Http/Controllers/Api/TelegramWebhookController.php` |
| Notification Job | `app/Jobs/SendNotification.php` |
| Monitor Check | `app/Jobs/MonitorCheck.php` |
| Channel Model | `app/Models/NotificationChannel.php` |
| Monitor Model | `app/Models/Monitor.php` |
| Incident Model | `app/Models/Incident.php` |
| Routes | `routes/api.php` |
| Observer | `app/Observers/NotificationChannelObserver.php` |

---

**📅 Last Updated:** February 5, 2026  
**📝 Version:** 1.0  
**✍️ Author:** System Documentation
