# Icon Scraping Feature

## Fitur
Auto-scrape favicon dari website saat monitor dibuat.

## Cara Kerja

### 1. Auto-Fetch saat Create Monitor
```php
// MonitorController.php - line ~167
if (!isset($data['icon_url']) && in_array($data['type'], ['http', 'https', 'keyword'])) {
    $iconUrl = \App\Jobs\ProcessMonitorCheck::getFaviconUrl($data['target']);
    if ($iconUrl) {
        $data['icon_url'] = $iconUrl;
    }
}
```

### 2. Metode Scraping (Priority Order)
1. **Parse HTML** → Cari apple-touch-icon, icon dengan sizes, shortcut icon
2. **Try /favicon.ico** → Check standard location
3. **Google Favicon Service** → Fallback `https://www.google.com/s2/favicons?domain={host}&sz=64`

### 3. Database
```sql
ALTER TABLE monitors ADD COLUMN icon_url VARCHAR(255) AFTER target;
```

## Frontend Display

```vue
<div class="monitor-icon-wrapper">
  <img v-if="monitor.icon_url" :src="monitor.icon_url" class="monitor-icon" />
  <span v-else class="monitor-type-icon">{{ getTypeIcon(monitor.type) }}</span>
</div>
```

Fallback emoji: 🌐 (http), 🔒 (https), 🔌 (tcp), 📡 (ping), 🔍 (keyword)

## Update Existing Monitors

```bash
# Bulk update
php update_monitor_icons.php

# Manual single
php artisan tinker
>>> $m = App\Models\Monitor::find(1);
>>> $m->update(['icon_url' => App\Jobs\ProcessMonitorCheck::getFaviconUrl($m->target)]);
```

## API Response
```json
{
  "id": 1,
  "name": "Google",
  "target": "https://www.google.com",
  "icon_url": "https://www.google.com/favicon.ico"
}
```
