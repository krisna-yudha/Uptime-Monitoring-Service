# 📡 Uptime Monitor API Testing Guide

> **⚠️ DEPRECATED:** Dokumentasi ini telah dipindahkan ke [API_DOCUMENTATION.md](API_DOCUMENTATION.md)
>
> Silakan gunakan **API_DOCUMENTATION.md** untuk dokumentasi API yang lebih lengkap dan up-to-date.
> File ini tetap dipertahankan untuk backward compatibility sementara waktu.

---

Complete API reference for Uptime Monitor backend.

**Base URL:** `http://localhost:8000/api`  
**Production URL:** `https://your-domain.com/api`

---

## 📋 Table of Contents

1. [Authentication](#1-authentication-endpoints)
2. [Public Endpoints](#2-public-endpoints)
3. [Dashboard](#3-dashboard-endpoints)
4. [Monitors](#4-monitor-endpoints)
5. [Notification Channels](#5-notification-channel-endpoints)
6. [Monitor Checks](#6-monitor-check-endpoints)
7. [Incidents](#7-incident-endpoints)
8. [Monitoring Logs](#8-monitoring-logs-endpoints)
9. [Settings](#9-settings-endpoints)
10. [User Management](#10-user-management-endpoints)
11. [Telegram Webhook](#11-telegram-webhook-endpoint)

---

## 🔐 Authentication

All protected endpoints require JWT authentication. Include the token in the Authorization header:

```
Authorization: Bearer YOUR_JWT_TOKEN
```

---

## 1. Authentication Endpoints

### 1.1 Register User

```http
POST /auth/register
Content-Type: application/json
```

**Request Body:**
```json
{
    "name": "John Doe",
    "email": "john@example.com",
    "password": "password123",
    "password_confirmation": "password123",
    "role": "user"
}
```

**Response:**
```json
{
    "success": true,
    "message": "Registration successful",
    "data": {
        "user": {
            "id": 1,
            "name": "John Doe",
            "email": "john@example.com",
            "role": "user"
        },
        "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
        "token_type": "bearer",
        "expires_in": 3600
    }
}
```

---

### 1.2 Login

```http
POST /auth/login
Content-Type: application/json
```

**Request Body:**
```json
{
    "email": "admin@uptimemonitor.local",
    "password": "password"
}
```

**Response:**
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
        "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
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

**Response:**
```json
{
    "success": true,
    "data": {
        "id": 1,
        "name": "Admin User",
        "email": "admin@uptimemonitor.local",
        "role": "admin",
        "created_at": "2026-01-01T00:00:00.000000Z"
    }
}
```

---

### 1.4 Logout

```http
POST /auth/logout
Authorization: Bearer YOUR_JWT_TOKEN
```

**Response:**
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

**Response:**
```json
{
    "success": true,
    "data": {
        "token": "NEW_JWT_TOKEN",
        "token_type": "bearer",
        "expires_in": 3600
    }
}
```

---

## 2. Public Endpoints

### 2.1 Test Server Connectivity

```http
GET /test
```

**Response:**
```json
{
    "success": true,
    "message": "Server is running",
    "timestamp": "2026-01-06T10:00:00.000000Z",
    "server": "Laravel 11.x"
}
```

---

### 2.2 Public Monitor Status

```http
GET /public/monitors
```

**Response:**
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

### 2.3 Public Monitor Statistics

```http
GET /public/monitors/statistics
```

**Response:**
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

**Response:**
```json
{
    "success": true,
    "data": {
        "id": 1,
        "name": "Google Homepage",
        "type": "http",
        "last_status": "up",
        "last_checked_at": "2026-01-06T10:00:00.000000Z",
        "uptime_percentage": 99.95,
        "avg_response_time": 125,
        "recent_checks": [...]
    }
}
```

---

### 2.5 Heartbeat Endpoint

```http
POST /heartbeat/{heartbeat_key}
Content-Type: application/json
```

**Request Body:**
```json
{
    "status": "up",
    "message": "Service running normally"
}
```

**Response:**
```json
{
    "success": true,
    "message": "Heartbeat received"
}
```

---

## 3. Dashboard Endpoints

### 3.1 Dashboard Overview

```http
GET /dashboard/overview
Authorization: Bearer YOUR_JWT_TOKEN
```

**Response:**
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
        "avg_response_time": 125
    }
}
```

---

### 3.2 Response Time Statistics

```http
GET /dashboard/response-time-stats
Authorization: Bearer YOUR_JWT_TOKEN
```

**Query Parameters:**
- `period` - 24h|7d|30d (default: 24h)
- `monitor_id` - Filter by monitor ID (optional)

---

### 3.3 Uptime Statistics

```http
GET /dashboard/uptime-stats
Authorization: Bearer YOUR_JWT_TOKEN
```

**Query Parameters:**
- `period` - 24h|7d|30d (default: 7d)
- `monitor_id` - Filter by monitor ID (optional)

---

### 3.4 Incident History

```http
GET /dashboard/incident-history
Authorization: Bearer YOUR_JWT_TOKEN
```

**Query Parameters:**
- `monitor_id` - Filter by monitor ID (optional)
- `resolved` - true|false (optional)
- `per_page` - Results per page (default: 15)

---

### 3.5 Check History

```http
GET /dashboard/check-history
Authorization: Bearer YOUR_JWT_TOKEN
```

**Query Parameters:**
- `monitor_id` - Monitor ID (required)
- `status` - up|down|unknown (optional)
- `start_date` - YYYY-MM-DD (optional)
- `end_date` - YYYY-MM-DD (optional)
- `per_page` - Results per page (default: 50)

---

### 3.6 SSL Certificate Report

```http
GET /dashboard/ssl-report
Authorization: Bearer YOUR_JWT_TOKEN
```

---

## 4. Monitor Endpoints

### 4.1 Get All Monitors

```http
GET /monitors
Authorization: Bearer YOUR_JWT_TOKEN
```

**Query Parameters:**
- `status` - up|down|unknown
- `type` - http|https|tcp|ping|keyword|push
- `enabled` - true|false
- `search` - search term
- `per_page` - Results per page (default: 15)

**Response:**
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
            "enabled": true,
            "last_status": "up",
            "last_checked_at": "2026-01-06T10:00:00Z",
            "uptime_percentage": 99.95,
            "avg_response_time": 125
        }
    ]
}
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