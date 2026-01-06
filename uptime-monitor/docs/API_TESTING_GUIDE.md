# Uptime Monitor API - Postman Testing Guide

Base URL: `http://localhost:8000/api`

## 1. Authentication Endpoints

### Register User
```
POST /auth/register
Content-Type: application/json

{
    "name": "Test User",
    "email": "test@example.com",
    "password": "password123",
    "password_confirmation": "password123",
    "role": "user"
}
```

### Login (Get JWT Token)
```
POST /auth/login
Content-Type: application/json

{
    "email": "admin@uptimemonitor.local",
    "password": "password"
}
```

Response will contain JWT token:
```json
{
    "success": true,
    "message": "Login successful",
    "data": {
        "user": {...},
        "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
        "token_type": "bearer",
        "expires_in": 3600
    }
}
```

### Get Current User
```
GET /auth/me
Authorization: Bearer YOUR_JWT_TOKEN
```

### Logout
```
POST /auth/logout
Authorization: Bearer YOUR_JWT_TOKEN
```

### Refresh Token
```
POST /auth/refresh
Authorization: Bearer YOUR_JWT_TOKEN
```

## 2. Monitor Endpoints (Requires Authentication)

### Get All Monitors
```
GET /monitors
Authorization: Bearer YOUR_JWT_TOKEN

Query Parameters:
- status: up|down|unknown
- type: http|https|tcp|ping|keyword|push
- enabled: true|false
- search: search_term
- per_page: 15
```

### Create Monitor
```
POST /monitors
Authorization: Bearer YOUR_JWT_TOKEN
Content-Type: application/json

{
    "name": "Google Homepage",
    "type": "https",
    "target": "https://google.com",
    "interval_seconds": 60,
    "timeout_ms": 5000,
    "retries": 3,
    "notify_after_retries": 2,
    "enabled": true,
    "config": {
        "expected_status_code": 200,
        "expected_content": "Google"
    },
    "tags": ["production", "critical"],
    "notification_channels": [1, 2]
}
```

### Get Specific Monitor
```
GET /monitors/{id}
Authorization: Bearer YOUR_JWT_TOKEN
```

### Update Monitor
```
PUT /monitors/{id}
Authorization: Bearer YOUR_JWT_TOKEN
Content-Type: application/json

{
    "name": "Updated Monitor Name",
    "enabled": false
}
```

### Delete Monitor
```
DELETE /monitors/{id}
Authorization: Bearer YOUR_JWT_TOKEN
```

### Pause Monitor
```
POST /monitors/{id}/pause
Authorization: Bearer YOUR_JWT_TOKEN
Content-Type: application/json

{
    "duration_minutes": 60
}
```

### Resume Monitor
```
POST /monitors/{id}/resume
Authorization: Bearer YOUR_JWT_TOKEN
```

## 3. Dashboard Endpoints

### Dashboard Overview
```
GET /dashboard/overview
Authorization: Bearer YOUR_JWT_TOKEN
```

### Response Time Statistics
```
GET /dashboard/response-time-stats?period=24h&monitor_id=1
Authorization: Bearer YOUR_JWT_TOKEN

Query Parameters:
- period: 24h|7d|30d
- monitor_id: optional
```

### Uptime Statistics
```
GET /dashboard/uptime-stats?period=7d
Authorization: Bearer YOUR_JWT_TOKEN

Query Parameters:
- period: 24h|7d|30d
- monitor_id: optional
```

### Incident History
```
GET /dashboard/incident-history?monitor_id=1&resolved=false
Authorization: Bearer YOUR_JWT_TOKEN

Query Parameters:
- monitor_id: optional
- resolved: true|false (optional)
- per_page: 15 (optional)
```

### Check History
```
GET /dashboard/check-history?monitor_id=1&status=down
Authorization: Bearer YOUR_JWT_TOKEN

Query Parameters:
- monitor_id: required
- status: up|down|unknown (optional)
- start_date: YYYY-MM-DD (optional)
- end_date: YYYY-MM-DD (optional)
- per_page: 50 (optional)
```

### SSL Certificate Report
```
GET /dashboard/ssl-report
Authorization: Bearer YOUR_JWT_TOKEN
```

## 4. Notification Channel Endpoints

### Get All Notification Channels
```
GET /notification-channels
Authorization: Bearer YOUR_JWT_TOKEN
```

### Create Notification Channel

#### Telegram Channel
```
POST /notification-channels
Authorization: Bearer YOUR_JWT_TOKEN
Content-Type: application/json

{
    "name": "Telegram Alert",
    "type": "telegram",
    "config": {
        "bot_token": "YOUR_BOT_TOKEN",
        "chat_id": "YOUR_CHAT_ID"
    }
}
```

#### Discord Webhook
```
POST /notification-channels
Authorization: Bearer YOUR_JWT_TOKEN
Content-Type: application/json

{
    "name": "Discord Alert",
    "type": "discord",
    "config": {
        "webhook_url": "https://discord.com/api/webhooks/..."
    }
}
```

#### Slack Webhook
```
POST /notification-channels
Authorization: Bearer YOUR_JWT_TOKEN
Content-Type: application/json

{
    "name": "Slack Alert",
    "type": "slack",
    "config": {
        "webhook_url": "https://hooks.slack.com/services/..."
    }
}
```

#### Custom Webhook
```
POST /notification-channels
Authorization: Bearer YOUR_JWT_TOKEN
Content-Type: application/json

{
    "name": "Custom Webhook",
    "type": "webhook",
    "config": {
        "webhook_url": "https://your-server.com/webhook",
        "headers": {
            "X-API-Key": "your-api-key"
        }
    }
}
```

### Test Notification Channel
```
POST /notification-channels/{id}/test
Authorization: Bearer YOUR_JWT_TOKEN
```

### Update Notification Channel
```
PUT /notification-channels/{id}
Authorization: Bearer YOUR_JWT_TOKEN
Content-Type: application/json

{
    "name": "Updated Channel Name"
}
```

### Delete Notification Channel
```
DELETE /notification-channels/{id}
Authorization: Bearer YOUR_JWT_TOKEN
```

## 5. Monitor Check Endpoints

### Get Monitor Checks
```
GET /monitor-checks?monitor_id=1&status=down&per_page=20
Authorization: Bearer YOUR_JWT_TOKEN

Query Parameters:
- monitor_id: optional
- status: up|down|unknown (optional)
- per_page: 15 (optional)
```

### Get Specific Check
```
GET /monitor-checks/{id}
Authorization: Bearer YOUR_JWT_TOKEN
```

## 6. Incident Endpoints

### Get Incidents
```
GET /incidents?monitor_id=1&resolved=false
Authorization: Bearer YOUR_JWT_TOKEN

Query Parameters:
- monitor_id: optional
- resolved: true|false (optional)
- per_page: 15 (optional)
```

### Get Specific Incident
```
GET /incidents/{id}
Authorization: Bearer YOUR_JWT_TOKEN
```

### Update Incident (Resolve/Add Description)
```
PUT /incidents/{id}
Authorization: Bearer YOUR_JWT_TOKEN
Content-Type: application/json

{
    "resolved": true,
    "description": "Issue resolved by restarting server"
}
```

## 7. Heartbeat Endpoint (Public - No Auth Required)

### Send Heartbeat for Push Monitor
```
POST /heartbeat/{heartbeat_key}
Content-Type: application/json

{
    "status": "up",
    "message": "Service is running normally"
}
```

## Postman Setup Steps:

1. **Start Laravel Server:**
   ```bash
   cd c:\xampp\htdocs\prjctmgng\uptime-monitor
   php artisan serve
   ```

2. **Login to get JWT token:**
   - Use POST `/api/auth/login` with admin credentials
   - Copy the `token` from response

3. **Set Authorization Header:**
   - For protected endpoints, add header:
   - `Authorization: Bearer YOUR_JWT_TOKEN`

4. **Test Flow:**
   1. Login → Get token
   2. Create monitor
   3. Get dashboard overview
   4. Create notification channel
   5. Test notification

## Default Test Credentials:
- **Admin:** admin@uptimemonitor.local / password
- **User:** user@uptimemonitor.local / password