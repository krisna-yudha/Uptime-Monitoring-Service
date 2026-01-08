# 📡 Uptime Monitor - Complete API Documentation

**Version:** 1.0  
**Base URL (Development):** `http://localhost:8000/api`  
**Base URL (Production):** `https://your-domain.com/api`

---

## 📋 Table of Contents

1. [Authentication](#1-authentication)
2. [Public Endpoints](#2-public-endpoints)
3. [Dashboard](#3-dashboard)
4. [Monitors](#4-monitors)
5. [Notification Channels](#5-notification-channels)
6. [Monitor Checks](#6-monitor-checks)
7. [Incidents](#7-incidents)
8. [Monitoring Logs](#8-monitoring-logs)
9. [Settings](#9-settings)
10. [User Management](#10-user-management-admin-only)
11. [Telegram Webhook](#11-telegram-webhook)

---

## 🔐 Authentication

All protected endpoints require JWT authentication. Include the token in the Authorization header:

```http
Authorization: Bearer YOUR_JWT_TOKEN
```

**Token Expiry:** 60 minutes  
**Refresh:** Use `/auth/refresh` endpoint

---

## 1. Authentication

### 1.1 Register

Create a new user account.

```http
POST /auth/register
Content-Type: application/json
```

**Request:**
```json
{
    "name": "John Doe",
    "email": "john@example.com",
    "password": "SecurePass123",
    "password_confirmation": "SecurePass123",
    "role": "user"
}
```

**Response (201):**
```json
{
    "success": true,
    "message": "Registration successful",
    "data": {
        "user": {
            "id": 5,
            "name": "John Doe",
            "email": "john@example.com",
            "role": "user",
            "created_at": "2026-01-06T10:00:00Z"
        },
        "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
        "token_type": "bearer",
        "expires_in": 3600
    }
}
```

---

### 1.2 Login

Authenticate and receive JWT token.

```http
POST /auth/login
Content-Type: application/json
```

**Request:**
```json
{
    "email": "admin@uptimemonitor.local",
    "password": "password"
}
```

**Response (200):**
```json
{
    "success": true,
    "message": "Login successful",
    "data": {
        "user": {
            "id": 1,
            "name": "Admin User",
            "email": "admin@uptimemonitor.local",
            "role": "admin"
        },
        "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
        "token_type": "bearer",
        "expires_in": 3600
    }
}
```

---

### 1.3 Get Current User

```http
GET /auth/me
Authorization: Bearer YOUR_JWT_TOKEN
```

**Response (200):**
```json
{
    "success": true,
    "data": {
        "id": 1,
        "name": "Admin User",
        "email": "admin@uptimemonitor.local",
        "role": "admin",
        "created_at": "2026-01-01T00:00:00Z"
    }
}
```

---

### 1.4 Logout

```http
POST /auth/logout
Authorization: Bearer YOUR_JWT_TOKEN
```

**Response (200):**
```json
{
    "success": true,
    "message": "Successfully logged out"
}
```

---

### 1.5 Refresh Token

```http
POST /auth/refresh
Authorization: Bearer YOUR_JWT_TOKEN
```

**Response (200):**
```json
{
    "success": true,
    "data": {
        "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
        "token_type": "bearer",
        "expires_in": 3600
    }
}
```

---

## 2. Public Endpoints

### 2.1 Server Test

Test server connectivity (no authentication required).

```http
GET /test
```

**Response (200):**
```json
{
    "success": true,
    "message": "Server is running",
    "timestamp": "2026-01-06T10:00:00Z",
    "server": "Laravel 11.x"
}
```

---

### 2.2 Public Monitors List

Get all public monitors.

```http
GET /public/monitors
```

**Response (200):**
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "name": "Google Homepage",
            "type": "http",
            "last_status": "up",
            "uptime_percentage": 99.95,
            "avg_response_time": 125
        }
    ]
}
```

---

### 2.3 Public Statistics

```http
GET /public/monitors/statistics
```

**Response (200):**
```json
{
    "success": true,
    "data": {
        "total_monitors": 10,
        "monitors_up": 9,
        "monitors_down": 1,
        "overall_uptime": 99.5
    }
}
```

---

### 2.4 Public Monitor Details

```http
GET /public/monitors/{id}
```

**Response (200):**
```json
{
    "success": true,
    "data": {
        "id": 1,
        "name": "Google Homepage",
        "type": "http",
        "target": "https://google.com",
        "last_status": "up",
        "last_checked_at": "2026-01-06T10:00:00Z",
        "uptime_percentage": 99.95,
        "avg_response_time": 125,
        "recent_checks": []
    }
}
```

---

### 2.5 Heartbeat (Push Monitor)

Send heartbeat for push-type monitors.

```http
POST /heartbeat/{heartbeat_key}
Content-Type: application/json
```

**Request:**
```json
{
    "status": "up",
    "message": "Service running normally"
}
```

**Response (200):**
```json
{
    "success": true,
    "message": "Heartbeat received"
}
```

---

## 3. Dashboard

### 3.1 Overview

```http
GET /dashboard/overview
Authorization: Bearer YOUR_JWT_TOKEN
```

**Response (200):**
```json
{
    "success": true,
    "data": {
        "total_monitors": 15,
        "monitors_up": 13,
        "monitors_down": 2,
        "monitors_paused": 0,
        "total_incidents": 5,
        "open_incidents": 2,
        "resolved_incidents": 3,
        "avg_uptime": 99.5,
        "avg_response_time": 125,
        "recent_incidents": []
    }
}
```

---

### 3.2 Response Time Stats

```http
GET /dashboard/response-time-stats?period=24h&monitor_id=1
Authorization: Bearer YOUR_JWT_TOKEN
```

**Query Parameters:**
- `period` - 24h|7d|30d (default: 24h)
- `monitor_id` - Filter by specific monitor (optional)

**Response (200):**
```json
{
    "success": true,
    "data": {
        "period": "24h",
        "data_points": [
            {
                "timestamp": "2026-01-06T09:00:00Z",
                "avg_response_time": 120,
                "min_response_time": 80,
                "max_response_time": 200
            }
        ]
    }
}
```

---

### 3.3 Uptime Stats

```http
GET /dashboard/uptime-stats?period=7d
Authorization: Bearer YOUR_JWT_TOKEN
```

**Query Parameters:**
- `period` - 24h|7d|30d (default: 7d)
- `monitor_id` - Filter by specific monitor (optional)

---

### 3.4 Incident History

```http
GET /dashboard/incident-history?monitor_id=1&resolved=false
Authorization: Bearer YOUR_JWT_TOKEN
```

**Query Parameters:**
- `monitor_id` - Filter by monitor (optional)
- `resolved` - true|false (optional)
- `per_page` - Results per page (default: 15)

---

### 3.5 Check History

```http
GET /dashboard/check-history?monitor_id=1&status=down
Authorization: Bearer YOUR_JWT_TOKEN
```

**Query Parameters:**
- `monitor_id` - Monitor ID (required)
- `status` - up|down|unknown (optional)
- `start_date` - YYYY-MM-DD (optional)
- `end_date` - YYYY-MM-DD (optional)
- `per_page` - Results per page (default: 50)

---

### 3.6 SSL Report

```http
GET /dashboard/ssl-report
Authorization: Bearer YOUR_JWT_TOKEN
```

**Response (200):**
```json
{
    "success": true,
    "data": [
        {
            "monitor_id": 1,
            "monitor_name": "Production Website",
            "ssl_valid": true,
            "ssl_expires_at": "2026-12-31T23:59:59Z",
            "days_until_expiry": 360,
            "issuer": "Let's Encrypt"
        }
    ]
}
```

---

## 4. Monitors

### 4.1 List Monitors

```http
GET /monitors?status=up&type=http&enabled=true
Authorization: Bearer YOUR_JWT_TOKEN
```

**Query Parameters:**
- `status` - up|down|unknown
- `type` - http|https|tcp|ping|keyword|push
- `enabled` - true|false
- `search` - Search term
- `per_page` - Results per page (default: 15)

**Response (200):**
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "name": "Google Homepage",
            "type": "http",
            "target": "https://google.com",
            "interval_seconds": 60,
            "timeout_ms": 5000,
            "retries": 3,
            "enabled": true,
            "is_public": false,
            "last_status": "up",
            "last_checked_at": "2026-01-06T10:00:00Z",
            "uptime_percentage": 99.95,
            "avg_response_time": 125,
            "notification_channels": [1, 2],
            "created_at": "2026-01-01T00:00:00Z"
        }
    ],
    "meta": {
        "current_page": 1,
        "total": 15,
        "per_page": 15
    }
}
```

---

### 4.2 Get Monitor Groups

```http
GET /monitors/groups
Authorization: Bearer YOUR_JWT_TOKEN
```

**Response (200):**
```json
{
    "success": true,
    "data": [
        {
            "group_name": "Production",
            "monitor_count": 5
        },
        {
            "group_name": "Staging",
            "monitor_count": 3
        }
    ]
}
```

---

### 4.3 Get Grouped Monitors

```http
GET /monitors/grouped
Authorization: Bearer YOUR_JWT_TOKEN
```

**Response (200):**
```json
{
    "success": true,
    "data": {
        "Production": [
            {
                "id": 1,
                "name": "API Server",
                "type": "http",
                "last_status": "up"
            }
        ],
        "Staging": [...]
    }
}
```

---

### 4.4 Create Monitor

```http
POST /monitors
Authorization: Bearer YOUR_JWT_TOKEN
Content-Type: application/json
```

**Request:**
```json
{
    "name": "My Website",
    "type": "http",
    "target": "https://example.com",
    "interval_seconds": 60,
    "timeout_ms": 5000,
    "retries": 3,
    "notify_after_retries": 2,
    "enabled": true,
    "is_public": false,
    "group_name": "Production",
    "config": {
        "expected_status_code": 200,
        "expected_content": "Welcome",
        "follow_redirects": true
    },
    "notification_channels": [1, 2],
    "tags": ["production", "critical"]
}
```

**Response (201):**
```json
{
    "success": true,
    "message": "Monitor created successfully",
    "data": {
        "id": 10,
        "name": "My Website",
        "type": "http",
        "target": "https://example.com",
        "interval_seconds": 60,
        "enabled": true,
        "created_at": "2026-01-06T10:00:00Z"
    }
}
```

---

### 4.5 Get Monitor

```http
GET /monitors/{id}
Authorization: Bearer YOUR_JWT_TOKEN
```

**Response (200):**
```json
{
    "success": true,
    "data": {
        "id": 1,
        "name": "My Website",
        "type": "http",
        "target": "https://example.com",
        "interval_seconds": 60,
        "timeout_ms": 5000,
        "retries": 3,
        "notify_after_retries": 2,
        "enabled": true,
        "is_public": false,
        "last_status": "up",
        "last_checked_at": "2026-01-06T10:00:00Z",
        "next_check_at": "2026-01-06T10:01:00Z",
        "uptime_percentage": 99.95,
        "avg_response_time": 125,
        "config": {},
        "notification_channels": [1, 2],
        "recent_checks": []
    }
}
```

---

### 4.6 Update Monitor

```http
PUT /monitors/{id}
Authorization: Bearer YOUR_JWT_TOKEN
Content-Type: application/json
```

**Request:**
```json
{
    "name": "Updated Monitor Name",
    "enabled": false,
    "interval_seconds": 120
}
```

**Response (200):**
```json
{
    "success": true,
    "message": "Monitor updated successfully",
    "data": {
        "id": 1,
        "name": "Updated Monitor Name",
        "enabled": false,
        "interval_seconds": 120
    }
}
```

---

### 4.7 Delete Monitor

```http
DELETE /monitors/{id}
Authorization: Bearer YOUR_JWT_TOKEN
```

**Response (200):**
```json
{
    "success": true,
    "message": "Monitor deleted successfully"
}
```

---

### 4.8 Pause Monitor

```http
POST /monitors/{id}/pause
Authorization: Bearer YOUR_JWT_TOKEN
Content-Type: application/json
```

**Request:**
```json
{
    "duration_minutes": 60
}
```

**Response (200):**
```json
{
    "success": true,
    "message": "Monitor paused for 60 minutes",
    "data": {
        "id": 1,
        "paused_until": "2026-01-06T11:00:00Z"
    }
}
```

---

### 4.9 Resume Monitor

```http
POST /monitors/{id}/resume
Authorization: Bearer YOUR_JWT_TOKEN
```

**Response (200):**
```json
{
    "success": true,
    "message": "Monitor resumed",
    "data": {
        "id": 1,
        "enabled": true
    }
}
```

---

### 4.10 Bulk Action

```http
POST /monitors/bulk-action
Authorization: Bearer YOUR_JWT_TOKEN
Content-Type: application/json
```

**Request:**
```json
{
    "action": "pause",
    "monitor_ids": [1, 2, 3],
    "duration_minutes": 30
}
```

**Actions:** `pause`, `resume`, `enable`, `disable`, `delete`

**Response (200):**
```json
{
    "success": true,
    "message": "Bulk action completed",
    "data": {
        "affected": 3
    }
}
```

---

## 5. Notification Channels

### 5.1 List Channels

```http
GET /notification-channels
Authorization: Bearer YOUR_JWT_TOKEN
```

**Response (200):**
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "name": "Discord Alerts",
            "type": "discord",
            "enabled": true,
            "config": {
                "webhook_url": "https://discord.com/api/webhooks/..."
            },
            "created_at": "2026-01-01T00:00:00Z"
        }
    ]
}
```

---

### 5.2 Create Channel - Discord

```http
POST /notification-channels
Authorization: Bearer YOUR_JWT_TOKEN
Content-Type: application/json
```

**Request:**
```json
{
    "name": "Discord Alerts",
    "type": "discord",
    "enabled": true,
    "config": {
        "webhook_url": "https://discord.com/api/webhooks/..."
    }
}
```

---

### 5.3 Create Channel - Telegram

```http
POST /notification-channels
Authorization: Bearer YOUR_JWT_TOKEN
Content-Type: application/json
```

**Request:**
```json
{
    "name": "Telegram Bot",
    "type": "telegram",
    "enabled": true,
    "config": {
        "bot_token": "1234567890:ABCdefGHIjklMNOpqrsTUVwxyz",
        "chat_id": "-1001234567890"
    }
}
```

---

### 5.4 Create Channel - Slack

```http
POST /notification-channels
Authorization: Bearer YOUR_JWT_TOKEN
Content-Type: application/json
```

**Request:**
```json
{
    "name": "Slack Alerts",
    "type": "slack",
    "enabled": true,
    "config": {
        "webhook_url": "https://hooks.slack.com/services/..."
    }
}
```

---

### 5.5 Create Channel - Custom Webhook

```http
POST /notification-channels
Authorization: Bearer YOUR_JWT_TOKEN
Content-Type: application/json
```

**Request:**
```json
{
    "name": "Custom Webhook",
    "type": "webhook",
    "enabled": true,
    "config": {
        "webhook_url": "https://your-server.com/webhook",
        "headers": {
            "X-API-Key": "your-api-key",
            "Content-Type": "application/json"
        }
    }
}
```

**Response (201):**
```json
{
    "success": true,
    "message": "Notification channel created successfully",
    "data": {
        "id": 5,
        "name": "Custom Webhook",
        "type": "webhook",
        "enabled": true
    }
}
```

---

### 5.6 Get Channel

```http
GET /notification-channels/{id}
Authorization: Bearer YOUR_JWT_TOKEN
```

---

### 5.7 Update Channel

```http
PUT /notification-channels/{id}
Authorization: Bearer YOUR_JWT_TOKEN
Content-Type: application/json
```

**Request:**
```json
{
    "name": "Updated Channel Name",
    "enabled": false
}
```

---

### 5.8 Delete Channel

```http
DELETE /notification-channels/{id}
Authorization: Bearer YOUR_JWT_TOKEN
```

---

### 5.9 Test Channel

```http
POST /notification-channels/{id}/test
Authorization: Bearer YOUR_JWT_TOKEN
```

**Response (200):**
```json
{
    "success": true,
    "message": "Test notification sent successfully"
}
```

---

### 5.10 Toggle Channel

```http
POST /notification-channels/{id}/toggle
Authorization: Bearer YOUR_JWT_TOKEN
```

**Response (200):**
```json
{
    "success": true,
    "message": "Channel toggled",
    "data": {
        "id": 1,
        "enabled": false
    }
}
```

---

## 6. Monitor Checks

### 6.1 List Checks

```http
GET /monitor-checks?monitor_id=1&status=down&per_page=20
Authorization: Bearer YOUR_JWT_TOKEN
```

**Query Parameters:**
- `monitor_id` - Filter by monitor (optional)
- `status` - up|down|unknown (optional)
- `per_page` - Results per page (default: 15)

**Response (200):**
```json
{
    "success": true,
    "data": [
        {
            "id": 1001,
            "monitor_id": 1,
            "status": "up",
            "response_time": 125,
            "status_code": 200,
            "error_message": null,
            "checked_at": "2026-01-06T10:00:00Z"
        }
    ]
}
```

---

### 6.2 Get Check

```http
GET /monitor-checks/{id}
Authorization: Bearer YOUR_JWT_TOKEN
```

---

## 7. Incidents

### 7.1 List Incidents

```http
GET /incidents?monitor_id=1&resolved=false
Authorization: Bearer YOUR_JWT_TOKEN
```

**Query Parameters:**
- `monitor_id` - Filter by monitor (optional)
- `resolved` - true|false (optional)
- `per_page` - Results per page (default: 15)

**Response (200):**
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "monitor_id": 5,
            "monitor_name": "Production API",
            "status": "open",
            "severity": "critical",
            "started_at": "2026-01-06T08:00:00Z",
            "acknowledged_at": null,
            "resolved_at": null,
            "description": "Service timeout",
            "resolution_notes": null
        }
    ]
}
```

---

### 7.2 Get Incident

```http
GET /incidents/{id}
Authorization: Bearer YOUR_JWT_TOKEN
```

---

### 7.3 Mark Pending

```http
POST /incidents/{id}/pending
Authorization: Bearer YOUR_JWT_TOKEN
Content-Type: application/json
```

**Request:**
```json
{
    "note": "Investigation started"
}
```

---

### 7.4 Mark Done

```http
POST /incidents/{id}/done
Authorization: Bearer YOUR_JWT_TOKEN
Content-Type: application/json
```

**Request:**
```json
{
    "note": "Issue resolved"
}
```

---

### 7.5 Acknowledge Incident

```http
POST /incidents/{id}/acknowledge
Authorization: Bearer YOUR_JWT_TOKEN
Content-Type: application/json
```

**Request:**
```json
{
    "acknowledged_by": "John Doe",
    "note": "Looking into this issue"
}
```

**Response (200):**
```json
{
    "success": true,
    "message": "Incident acknowledged",
    "data": {
        "id": 1,
        "status": "acknowledged",
        "acknowledged_at": "2026-01-06T10:05:00Z"
    }
}
```

---

### 7.6 Resolve Incident

```http
POST /incidents/{id}/resolve
Authorization: Bearer YOUR_JWT_TOKEN
Content-Type: application/json
```

**Request:**
```json
{
    "resolution_notes": "Server restarted, service is back online"
}
```

**Response (200):**
```json
{
    "success": true,
    "message": "Incident resolved",
    "data": {
        "id": 1,
        "status": "resolved",
        "resolved_at": "2026-01-06T10:15:00Z"
    }
}
```

---

### 7.7 Reopen Incident

```http
POST /incidents/{id}/reopen
Authorization: Bearer YOUR_JWT_TOKEN
Content-Type: application/json
```

**Request:**
```json
{
    "note": "Issue occurred again"
}
```

---

### 7.8 Add Note

```http
POST /incidents/{id}/notes
Authorization: Bearer YOUR_JWT_TOKEN
Content-Type: application/json
```

**Request:**
```json
{
    "note": "Checked server logs, found database connection issue"
}
```

---

### 7.9 Get Alert Log

```http
GET /incidents/{id}/alert-log
Authorization: Bearer YOUR_JWT_TOKEN
```

**Response (200):**
```json
{
    "success": true,
    "data": [
        {
            "timestamp": "2026-01-06T08:00:00Z",
            "channel": "Discord",
            "status": "sent",
            "message": "Service down detected"
        }
    ]
}
```

---

## 8. Monitoring Logs

### 8.1 Recent Logs

```http
GET /logs/recent?limit=100
Authorization: Bearer YOUR_JWT_TOKEN
```

**Query Parameters:**
- `limit` - Number of logs (default: 50)
- `status` - Filter by status (optional)

---

### 8.2 Log Filters

```http
GET /logs/filters
Authorization: Bearer YOUR_JWT_TOKEN
```

**Response (200):**
```json
{
    "success": true,
    "data": {
        "monitors": [
            {"id": 1, "name": "Google"},
            {"id": 2, "name": "API Server"}
        ],
        "statuses": ["up", "down", "unknown"],
        "date_range": {
            "min": "2026-01-01",
            "max": "2026-01-06"
        }
    }
}
```

---

### 8.3 Monitor Logs

```http
GET /logs/monitor/{monitorId}?status=down&start_date=2026-01-01
Authorization: Bearer YOUR_JWT_TOKEN
```

**Query Parameters:**
- `status` - Filter by status (optional)
- `start_date` - YYYY-MM-DD (optional)
- `end_date` - YYYY-MM-DD (optional)
- `per_page` - Results per page (default: 50)

---

### 8.4 Log Stats

```http
GET /logs/monitor/{monitorId}/stats
Authorization: Bearer YOUR_JWT_TOKEN
```

---

### 8.5 Export Logs

```http
GET /logs/monitor/{monitorId}/export?format=csv
Authorization: Bearer YOUR_JWT_TOKEN
```

**Query Parameters:**
- `format` - csv|json|xlsx (default: csv)
- `start_date` - YYYY-MM-DD (optional)
- `end_date` - YYYY-MM-DD (optional)

**Response:** File download

---

## 9. Settings

### 9.1 Get Settings

```http
GET /settings
Authorization: Bearer YOUR_JWT_TOKEN
```

**Response (200):**
```json
{
    "success": true,
    "data": {
        "retention": {
            "rawChecks": 30,
            "rawLogs": 30,
            "minuteAggregates": 30,
            "hourAggregates": 90,
            "dayAggregates": 365
        },
        "aggregation": {
            "enabled": true,
            "intervals": ["minute", "hour", "day"]
        }
    }
}
```

---

### 9.2 Update Settings

```http
PUT /settings
Authorization: Bearer YOUR_JWT_TOKEN
Content-Type: application/json
```

**Request:**
```json
{
    "retention": {
        "rawChecks": 7,
        "rawLogs": 30
    }
}
```

---

### 9.3 Run Aggregation

```http
POST /settings/aggregate
Authorization: Bearer YOUR_JWT_TOKEN
Content-Type: application/json
```

**Request:**
```json
{
    "interval": "minute",
    "date": "2026-01-05"
}
```

**Response (200):**
```json
{
    "success": true,
    "message": "Aggregation completed",
    "data": {
        "records_aggregated": 1440
    }
}
```

---

### 9.4 Run Cleanup

```http
POST /settings/cleanup
Authorization: Bearer YOUR_JWT_TOKEN
```

**Response (200):**
```json
{
    "success": true,
    "message": "Cleanup completed",
    "data": {
        "deleted_checks": 5000,
        "deleted_logs": 1000
    }
}
```

---

## 10. User Management (Admin Only)

### 10.1 List Users

```http
GET /users
Authorization: Bearer YOUR_JWT_TOKEN
```

**Requires:** Admin role

---

### 10.2 Create User

```http
POST /users
Authorization: Bearer YOUR_JWT_TOKEN
Content-Type: application/json
```

**Request:**
```json
{
    "name": "New User",
    "email": "newuser@example.com",
    "password": "SecurePass123",
    "role": "user"
}
```

**Requires:** Admin role

---

### 10.3 Update User

```http
PUT /users/{id}
Authorization: Bearer YOUR_JWT_TOKEN
Content-Type: application/json
```

**Request:**
```json
{
    "name": "Updated Name",
    "role": "admin"
}
```

**Requires:** Admin role

---

### 10.4 Delete User

```http
DELETE /users/{id}
Authorization: Bearer YOUR_JWT_TOKEN
```

**Requires:** Admin role

---

## 11. Telegram Webhook

### 11.1 Webhook Endpoint

```http
POST /telegram/webhook
Content-Type: application/json
```

**Note:** This endpoint is called by Telegram servers, not directly by users.

**Request:** Telegram webhook payload

**Response (200):**
```json
{
    "ok": true
}
```

---

## 📊 Response Codes

| Code | Description |
|------|-------------|
| 200 | Success |
| 201 | Created |
| 400 | Bad Request |
| 401 | Unauthorized |
| 403 | Forbidden |
| 404 | Not Found |
| 422 | Validation Error |
| 500 | Server Error |

---

## 🔒 Error Response Format

```json
{
    "success": false,
    "message": "Error description",
    "errors": {
        "field_name": ["Error message"]
    }
}
```

---

## 💡 Best Practices

1. **Always include JWT token** in Authorization header for protected endpoints
2. **Refresh tokens** before they expire (60 minutes)
3. **Handle rate limiting** - Respect API limits
4. **Use pagination** for large data sets
5. **Check response codes** - Handle errors gracefully
6. **Validate input** before sending requests
7. **Use HTTPS** in production
8. **Store tokens securely** - Never expose in client-side code

---

## 🧪 Testing with Postman

1. Import API collection
2. Set base URL variable: `{{base_url}}`
3. Login to get token
4. Set token in Authorization header
5. Test endpoints

**Default Credentials:**
- Admin: `admin@uptimemonitor.local` / `password`
- User: `user@uptimemonitor.local` / `password`

---

## 📞 Support

For issues or questions:
- Check logs: `storage/logs/laravel.log`
- API documentation: `/docs`
- GitHub Issues

---

**Last Updated:** January 6, 2026  
**API Version:** 1.0
