# Enhanced Critical Alert & Incident Management System

## Overview
Sistem ini menambahkan kemampuan manajemen incident yang komprehensif dengan status tracking dan alert logging untuk critical alerts yang terjadi setelah 20 consecutive failures.

## 🚀 New Features Implemented

### 1. **Incident Status Management**
- **Open**: Incident baru yang belum ditangani
- **Pending**: Incident sedang dalam investigasi
- **Resolved**: Incident telah selesai ditangani

### 2. **Alert Status Tracking**
- **none**: Belum ada alert
- **notified**: Alert biasa sudah dikirim
- **critical_sent**: Critical alert sudah dikirim
- **acknowledged**: Incident sudah diakui/ditangani
- **escalated**: Incident sudah dieskalasi

### 3. **Comprehensive Logging**
- Alert log dengan timestamp detail
- Metadata tracking untuk setiap action
- User tracking untuk acknowledgment
- Duration tracking untuk resolution

## 📋 Database Schema Changes

### New Migration: `add_status_fields_to_incidents_table.php`
```sql
-- New fields added to incidents table:
ALTER TABLE incidents ADD COLUMN status ENUM('open', 'investigating', 'pending', 'resolved') DEFAULT 'open';
ALTER TABLE incidents ADD COLUMN alert_status ENUM('none', 'notified', 'critical_sent', 'acknowledged', 'escalated') DEFAULT 'none';
ALTER TABLE incidents ADD COLUMN acknowledged_at TIMESTAMP NULL;
ALTER TABLE incidents ADD COLUMN acknowledged_by VARCHAR(255) NULL;
ALTER TABLE incidents ADD COLUMN alert_log JSON NULL;
```

### New Migration: `add_critical_alert_tracking_to_monitors_table.php`
```sql
-- New field for tracking critical alerts:
ALTER TABLE monitors ADD COLUMN last_critical_alert_sent TIMESTAMP NULL;
```

## 🔧 Implementation Details

### 1. **Enhanced ProcessMonitorCheck Job**
**File**: `app/Jobs/ProcessMonitorCheck.php`

**Key Features**:
- Automatic incident status progression
- Critical alert detection at 20 consecutive failures
- Anti-spam protection for critical alerts
- Comprehensive alert logging

**New Methods**:
- `sendCriticalDownAlert()` - Sends critical notifications
- `hasCriticalAlertBeenSent()` - Prevents duplicate alerts
- `buildCriticalAlertMessage()` - Creates detailed alert messages
- `calculateDowntimeDuration()` - Estimates downtime

### 2. **Enhanced Incident Model**
**File**: `app/Models/Incident.php`

**New Methods**:
- `isPending()` - Check if incident is pending
- `isDone()` - Check if incident is resolved
- `hasCriticalAlertBeenSent()` - Check critical alert status
- `markAsPending()` - Mark incident as pending
- `markAsDone()` - Mark incident as resolved
- `logAlert()` - Add entry to alert log
- `updateAlertStatus()` - Update alert status with logging

### 3. **Incident Management API**
**File**: `app/Http/Controllers/Api/IncidentController.php`

**Endpoints**:
- `GET /api/incidents` - List incidents with filters
- `GET /api/incidents/{id}` - Show incident details
- `POST /api/incidents/{id}/pending` - Mark as pending
- `POST /api/incidents/{id}/done` - Mark as done  
- `GET /api/incidents/{id}/alert-log` - Get alert log

**API Routes**:
```php
Route::prefix('incidents')->group(function () {
    Route::get('/', [IncidentController::class, 'index']);
    Route::get('{incident}', [IncidentController::class, 'show']);
    Route::post('{incident}/pending', [IncidentController::class, 'markPending']);
    Route::post('{incident}/done', [IncidentController::class, 'markDone']);
    Route::get('{incident}/alert-log', [IncidentController::class, 'getAlertLog']);
});
```

### 4. **CLI Management Tools**
**File**: `app/Console/Commands/ManageIncidents.php`

**Command**: `php artisan incidents:manage {action}`

**Actions**:
- `list` - List incidents
- `show {id}` - Show incident details
- `pending {id}` - Mark as pending
- `done {id}` - Mark as resolved
- `log {id}` - Show alert log

**Options**:
- `--show-all` - Include resolved incidents
- `--status={status}` - Filter by status
- `--note={note}` - Add note when updating

## 📋 Installation & Setup

### 1. Run Migrations
```bash
# Run the new migrations
php artisan migrate

# Or force run in production
php artisan migrate --force
```

### 2. Test Critical Alert System
```bash
# Test with existing monitor
php artisan test:critical-alert 1 --simulate-20-failures

# This will:
# - Set consecutive_failures to 19
# - Trigger one more failure to reach 20
# - Send critical alert
# - Create incident with proper status
# - Log all activities
```

### 3. Manage Incidents via CLI
```bash
# List all active incidents
php artisan incidents:manage list

# Show incident details
php artisan incidents:manage show 1

# Mark incident as pending (under investigation)
php artisan incidents:manage pending 1 --note="Investigating server issues"

# Mark incident as done (resolved)
php artisan incidents:manage done 1 --note="Server fixed, monitoring resumed"

# View incident alert log
php artisan incidents:manage log 1
```

### 4. API Usage Examples
```bash
# List incidents
curl -X GET "http://your-domain/api/incidents"

# Mark as pending
curl -X POST "http://your-domain/api/incidents/1/pending" \
  -H "Content-Type: application/json" \
  -d '{"note": "Investigating network connectivity"}'

# Mark as done
curl -X POST "http://your-domain/api/incidents/1/done" \
  -H "Content-Type: application/json" \
  -d '{"note": "Issue resolved - server restarted"}'

# Get alert log
curl -X GET "http://your-domain/api/incidents/1/alert-log"
```

## 🔄 Workflow Examples

### Scenario 1: Critical Alert Workflow
1. **Service goes down** → `status: 'open'`, `alert_status: 'none'`
2. **Regular notifications sent** → `alert_status: 'notified'`
3. **20 consecutive failures reached** → `alert_status: 'critical_sent'`, `status: 'pending'`
4. **Admin acknowledges** → `alert_status: 'acknowledged'`
5. **Admin resolves** → `status: 'resolved'`, `resolved: true`

### Scenario 2: Manual Resolution
1. **Incident created** → `status: 'open'`
2. **Admin marks pending** → `status: 'pending'`, `acknowledged_by: 'Admin'`
3. **Admin marks done** → `status: 'resolved'`, `resolved: true`

### Scenario 3: Auto Resolution
1. **Incident created** → `status: 'open'`
2. **Service comes back online** → `status: 'resolved'`, `resolved: true`
3. **Auto-log created** → Alert log entry added

## 📊 Alert Log Structure

### Example Alert Log Entry
```json
{
  "type": "incident_marked_pending",
  "message": "Incident marked as pending for investigation",
  "metadata": {
    "acknowledged_by": "CLI Admin",
    "acknowledged_at": "2025-12-05T03:47:41.000000Z",
    "note": "Investigating server connectivity issues"
  },
  "timestamp": "2025-12-05T03:47:41.000000Z",
  "consecutive_failures": 20
}
```

### Alert Log Types
- `incident_created` - New incident created
- `incident_marked_pending` - Marked as pending
- `incident_marked_resolved` - Manually resolved
- `incident_auto_resolved` - Automatically resolved
- `incident_escalated` - Escalated due to critical alert
- `alert_status_changed` - Alert status updated
- `critical_alert_sent` - Critical alert dispatched

## 🚨 Critical Alert Message Format

```
🚨 CRITICAL SERVICE OUTAGE ALERT 🚨

Service: Production API
Target: https://api.production.com
Status: DOWN for 20 consecutive checks
Estimated Downtime: ~20 minutes
Last Error: Connection timeout after 5000ms

⚠️ IMMEDIATE ACTION REQUIRED ⚠️
This service has been unresponsive for an extended period.
Please investigate and resolve this issue immediately.

Incident Time: 2025-12-05 15:30:00 UTC
Alert Generated: 2025-12-05T15:30:00.000000Z
Incident ID: 123
```

## 🔍 Monitoring & Troubleshooting

### Check Incident Status
```bash
# List all open incidents
php artisan incidents:manage list

# List all incidents with specific status
php artisan incidents:manage list --status=pending

# Show all incidents including resolved
php artisan incidents:manage list --show-all
```

### Monitor Alert Logs
```bash
# Check application logs for critical alerts
grep "CRITICAL" storage/logs/laravel.log

# Check specific incident logs
php artisan incidents:manage log {incident_id}
```

### API Health Check
```bash
# Test API endpoints
curl -X GET "http://your-domain/api/incidents"
curl -X GET "http://your-domain/api/incidents/1"
```

## 🎯 Benefits

1. **Proactive Alert Management**: Critical alerts after 20 failures ensure immediate attention
2. **Status Tracking**: Clear workflow from open → pending → done
3. **Audit Trail**: Comprehensive logging of all incident activities  
4. **Anti-Spam**: Prevents duplicate critical alerts for same outage
5. **Flexible Management**: Both API and CLI interfaces for management
6. **Auto Resolution**: Automatic resolution when service recovers

## 📈 Future Enhancements

1. **Email Integration**: Direct email alerts for critical incidents
2. **SLA Tracking**: Monitor incident resolution times against SLA
3. **Dashboard Integration**: Web UI for incident management
4. **Escalation Rules**: Automatic escalation based on time thresholds
5. **Team Assignment**: Assign incidents to specific team members
6. **Incident Templates**: Pre-defined incident types and procedures

This implementation provides a robust foundation for critical alert management and incident tracking, ensuring that serious service outages receive immediate attention and proper documentation throughout their lifecycle.