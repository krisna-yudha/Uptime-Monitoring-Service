# Notification Channels Bug Fix

## Problem
When editing a monitor and unchecking notification bot/channel, after saving and re-opening the edit form, the channel would be checked again. This happened because the backend was not processing the `notification_channel_ids` field during monitor updates.

## Root Cause
The `MonitorController@update()` method was not handling the `notification_channel_ids` array sent from the frontend. When a user unchecked channels and saved, the backend ignored this field, leaving the existing `notification_channels` unchanged in the database.

## Solution

### Backend Changes (MonitorController.php)

#### 1. Added Validation for notification_channel_ids
```php
'notification_channel_ids' => 'sometimes|array',
'notification_channel_ids.*' => 'integer|exists:notification_channels,id'
```

#### 2. Convert notification_channel_ids to notification_channels
```php
if (isset($data['notification_channel_ids'])) {
    $data['notification_channels'] = $data['notification_channel_ids'];
    unset($data['notification_channel_ids']);
}
```

This ensures when the frontend sends `notification_channel_ids`, it gets mapped to the database field `notification_channels`.

#### 3. Enhanced update() Method Validation
Added support for all monitor-specific fields:
- **HTTP fields**: `http_method`, `http_headers`, `http_body`, `http_expected_status_codes`, `http_follow_redirects`, `http_verify_ssl`
- **Keyword fields**: `keyword_text`, `keyword_case_sensitive`
- **Heartbeat fields**: `heartbeat_grace_period_minutes`
- **Field mappings**: `is_enabled` → `enabled`, `timeout_seconds` → `timeout_ms`, `retry_count` → `retries`

#### 4. Config Field Extraction in show() Method
When returning monitor data, config fields are extracted to top-level:
```php
$monitorData['http_method'] = $monitor->config['http_method'] ?? 'GET';
$monitorData['timeout_seconds'] = intval($monitorData['timeout_ms'] / 1000);
```

This ensures frontend receives data in the expected format.

### Frontend Changes
No changes needed! Frontend was already correctly sending `notification_channel_ids` as an array. The checkbox binding with `v-model="form.notification_channel_ids"` automatically:
- Adds channel ID when checked
- Removes channel ID when unchecked
- Sends empty array `[]` when all unchecked

## Database Schema
The `monitors` table has a `notification_channels` column that stores an array (JSON) of notification channel IDs. This field is cast to array in the Monitor model:
```php
'notification_channels' => 'array',
```

## Testing Checklist
- [x] Edit monitor and uncheck all notification channels → Save → Reopen edit → Channels should remain unchecked
- [x] Edit monitor and check some channels → Save → Reopen edit → Only checked channels should remain checked
- [x] Edit monitor and change HTTP settings → Save → Settings should persist
- [x] Bulk assign notifications → Channels should update correctly

## Impact
This fix resolves the notification channel persistence issue and also improves overall data handling between frontend and backend, ensuring all monitor configuration fields are properly saved and retrieved.

## Date
January 12, 2026
