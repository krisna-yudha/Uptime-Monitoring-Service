# Monitor Type Separation - HTTP vs HTTPS

## Overview
HTTP and HTTPS have been separated into distinct monitor types for better clarity and configuration management.

## Changes Made

### 1. Monitor Type Options
**Before:**
- `HTTP/HTTPS` - Single option for both protocols

**After:**
- `HTTP` - For HTTP-only monitoring
- `HTTPS` - For HTTPS-only monitoring

### 2. Affected Views

#### EditMonitorView.vue
- Split monitor type dropdown options
- Updated conditional rendering: `v-if="form.type === 'http' || form.type === 'https'"`
- Dynamic section title based on selected type

#### CreateMonitorView.vue
- Split monitor type dropdown options
- Already supports both types with `['http', 'https', 'keyword'].includes(form.type)`

### 3. Notification Channels Enhancement

#### Previous Behavior:
- Channels that were already bound to a monitor were auto-checked and disabled
- Users could not uncheck these channels

#### New Behavior:
- Removed `:disabled` attribute from checkboxes
- Channels remain checkable/uncheckable even if previously bound
- Visual indicator "✓ Connected" still shows which channels are currently bound
- Green background styling maintained for visual feedback

## Benefits

1. **Clearer Configuration**: Users explicitly choose HTTP or HTTPS
2. **Better Security Awareness**: Distinguishes between secure and non-secure protocols
3. **Flexible Notifications**: Users can now remove notification channels when editing monitors
4. **Improved UX**: Visual indicators without restricting user actions

## Backward Compatibility

### Database Consideration
Existing monitors with type `http` will continue to work. The frontend now treats:
- `http` = HTTP protocol
- `https` = HTTPS protocol

If backend validation requires updates, ensure the monitor type field accepts both values.

## Testing Checklist

- [ ] Create new HTTP monitor
- [ ] Create new HTTPS monitor
- [ ] Edit existing monitor and change type from HTTP to HTTPS
- [ ] Verify HTTP/HTTPS settings section displays correctly
- [ ] Add notification channels to monitor
- [ ] Edit monitor and verify channels can be unchecked
- [ ] Verify "✓ Connected" badge appears for bound channels
- [ ] Save monitor with modified notification channels

## Date
January 12, 2026
