# Critical Alert Feature Documentation

## Overview
The critical alert feature automatically sends high-priority notifications when a service has been down for 20 consecutive monitoring checks. This indicates a serious service outage that requires immediate attention from the server management team.

## How It Works

### 1. Monitoring Process
- Every monitor check tracks `consecutive_failures` counter
- When a check fails, the counter increments
- When a check succeeds, the counter resets to 0

### 2. Critical Alert Trigger
- Critical alert is triggered when `consecutive_failures` reaches exactly 20
- Only sent once per outage to prevent spam
- Uses high-priority notification with special message formatting

### 3. Alert Prevention Logic
To prevent spam, the system ensures:
- Critical alert is only sent once per outage
- Tracks `last_critical_alert_sent` timestamp
- Compares with last successful check to determine if alert is for current outage

## Implementation Details

### Database Changes
**New field added to `monitors` table:**
```sql
ALTER TABLE monitors ADD COLUMN last_critical_alert_sent TIMESTAMP NULL;
```

### Key Components

#### 1. ProcessMonitorCheck Job
- **Location**: `app/Jobs/ProcessMonitorCheck.php`
- **Enhancement**: Added critical alert logic after line 464
- **Method**: `sendCriticalDownAlert()` - Handles critical notification dispatch
- **Method**: `hasCriticalAlertBeenSent()` - Prevents duplicate alerts

#### 2. SendNotification Job
- **Location**: `app/Jobs/SendNotification.php`
- **Enhancement**: Added support for `critical_down` notification type
- **Method**: `buildCriticalDownMessage()` - Creates detailed critical alert message

#### 3. Monitor Model
- **Location**: `app/Models/Monitor.php`
- **Enhancement**: Added `last_critical_alert_sent` to fillable fields and casts

### Critical Alert Message Format
The critical alert includes:
- 🚨 Clear "CRITICAL SERVICE OUTAGE" header
- Service name and target
- Number of consecutive failures (20)
- Estimated downtime duration
- Last error message
- Timestamp and incident information
- Clear call-to-action for immediate investigation

## Usage Examples

### Testing the Feature
Use the provided test command:
```bash
# List available monitors
php artisan test:critical-alert

# Test with specific monitor
php artisan test:critical-alert 1

# Simulate 20 failures to trigger critical alert
php artisan test:critical-alert 1 --simulate-20-failures
```

### Monitoring Logs
Critical alerts generate several log entries:
- **CRITICAL level log**: `"CRITICAL ALERT: Service down for 20 consecutive checks"`
- **MonitoringLog entry**: Type `critical_down_alert` with metadata
- **Notification dispatch log**: Standard notification logging

### Example Critical Alert Message
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

## Configuration

### Notification Channels
Critical alerts are sent to all configured notification channels for the monitor:
- Slack webhooks
- Discord webhooks  
- Telegram bots
- Custom webhooks

### Customization Options
1. **Failure Threshold**: Currently hardcoded to 20, can be made configurable
2. **Message Template**: Customize `buildCriticalDownMessage()` method
3. **Alert Frequency**: Currently once per outage, can be enhanced for repeat alerts

## Monitoring and Troubleshooting

### Health Checks
- Monitor `last_critical_alert_sent` field for alert activity
- Check `consecutive_failures` to track service stability
- Review `monitoring_logs` table for `critical_down_alert` events

### Common Issues
1. **Alerts not sending**: Check notification channel configuration
2. **Multiple alerts**: Verify `hasCriticalAlertBeenSent()` logic
3. **Missing alerts**: Ensure monitors have proper notification channels configured

### Log Analysis
```bash
# Check for critical alerts in logs
grep "CRITICAL ALERT" storage/logs/laravel.log

# Monitor specific service alerts  
grep "monitor_id.*123" storage/logs/laravel.log | grep critical
```

## Future Enhancements

### Possible Improvements
1. **Configurable threshold**: Allow setting custom failure count per monitor
2. **Escalation levels**: Multiple alert levels (10, 20, 50 failures)
3. **Alert suppression**: Ability to snooze critical alerts
4. **Recovery notifications**: Special notification when service recovers after critical alert
5. **SLA integration**: Track critical outages against SLA targets

### Integration Opportunities
- **PagerDuty**: Direct integration for on-call management
- **Incident Management**: Auto-create incidents in external systems
- **Metrics Dashboard**: Real-time critical alert tracking
- **Mobile Push**: Direct mobile notifications for critical alerts

## Security Considerations
- Notification channels should use secure webhook URLs (HTTPS)
- Alert messages may contain sensitive service information
- Access to critical alert logs should be restricted
- Consider rate limiting for notification endpoints