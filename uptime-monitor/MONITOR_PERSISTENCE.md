# Monitor Persistence on User Deletion

## Problem
Ketika user dihapus dari sistem, semua monitoring yang dibuat oleh user tersebut juga ikut terhapus karena foreign key constraint menggunakan `onDelete('cascade')`.

## Solution
Mengubah foreign key constraint dari `cascade delete` menjadi `set null` agar monitoring tetap ada ketika user dihapus.

## Changes Made

### Database Migration
Created migration: `2026_01_12_000001_modify_monitors_created_by_on_delete.php`

**Changes:**
1. `created_by` column made nullable
2. Foreign key constraint changed from `onDelete('cascade')` to `onDelete('set null')`

**Before:**
```php
$table->foreignId('created_by')->constrained('users'); // cascade delete (default)
```

**After:**
```php
$table->unsignedBigInteger('created_by')->nullable();
$table->foreign('created_by')
      ->references('id')
      ->on('users')
      ->onDelete('set null'); // Set to null instead of deleting monitor
```

## Behavior

### When User is Deleted

**Before Fix:**
- User dengan ID 5 dihapus
- Semua monitors dengan `created_by = 5` juga TERHAPUS
- Data monitoring history HILANG

**After Fix:**
- User dengan ID 5 dihapus
- Monitors dengan `created_by = 5` → `created_by = NULL`
- Monitoring tetap BERJALAN
- Data history tetap TERSIMPAN
- Monitor tetap bisa diakses oleh admin

### Monitor Display

Monitors dengan `created_by = NULL` akan:
- Tetap muncul di dashboard
- Tetap dijalankan oleh queue workers
- Admin masih bisa edit/delete
- Ditampilkan dengan label "Unknown User" atau "Deleted User"

## Model Handling

The Monitor model already has handling for deleted creators via `actual_created_by`:

```php
public function getCreatedByNameAttribute(): ?string
{
    // Prioritize actual_created_by (the real admin who created it)
    $userId = $this->actual_created_by ?? $this->created_by;
    
    if (!$userId) {
        return 'Unknown User'; // Display for deleted users
    }
    
    $creator = User::find($userId);
    return $creator ? $creator->name : 'Deleted User';
}
```

## Frontend Display

Update frontend to handle null `created_by`:

```vue
<!-- MonitorListView.vue -->
<template>
  <div class="creator">
    {{ monitor.created_by_name || 'Unknown User' }}
  </div>
</template>
```

## Admin Features

Monitors with deleted creators:
- ✅ Can still be edited by admins
- ✅ Can be reassigned to another user
- ✅ Continue running monitoring checks
- ✅ Send notifications normally
- ✅ Retain all historical data

## Migration Commands

```bash
# Run the migration
php artisan migrate

# Rollback if needed
php artisan migrate:rollback --step=1

# Check migration status
php artisan migrate:status
```

## Testing

### Test Scenario 1: Delete User with Monitors
```bash
# 1. Create test user
php artisan tinker
>>> $user = User::create(['name' => 'Test User', 'email' => 'test@example.com', 'password' => Hash::make('password')]);

# 2. Create monitor for that user
>>> $monitor = Monitor::create(['name' => 'Test Monitor', 'type' => 'http', 'target' => 'https://google.com', 'created_by' => $user->id]);

# 3. Delete the user
>>> $user->delete();

# 4. Check monitor still exists
>>> Monitor::find($monitor->id);
>>> // Should return monitor with created_by = null

# 5. Verify monitor still works
>>> // Queue workers will still process this monitor
```

### Test Scenario 2: Admin Can Manage Orphaned Monitors
```bash
# Admin can view monitors with created_by = null
>>> Monitor::whereNull('created_by')->get();

# Admin can reassign to another user
>>> $monitor = Monitor::whereNull('created_by')->first();
>>> $monitor->update(['created_by' => 1]); // Assign to admin
```

## Database Query Examples

```sql
-- Find all monitors with deleted creators
SELECT * FROM monitors WHERE created_by IS NULL;

-- Count orphaned monitors
SELECT COUNT(*) FROM monitors WHERE created_by IS NULL;

-- Reassign orphaned monitors to admin (ID = 1)
UPDATE monitors SET created_by = 1 WHERE created_by IS NULL;
```

## Security Considerations

1. **Authorization Check Update:** Ensure controllers handle null `created_by`:
```php
// MonitorController.php
if ($currentUser->role !== 'admin' && 
    $monitor->created_by !== null && 
    $monitor->created_by !== $currentUser->id) {
    return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
}
```

2. **Orphaned Monitor Cleanup (Optional):**
If you want to clean up orphaned monitors periodically:
```php
// app/Console/Commands/CleanupOrphanedMonitors.php
Monitor::whereNull('created_by')
       ->where('updated_at', '<', now()->subMonths(6))
       ->delete();
```

## Benefits

✅ **Data Persistence:** Monitoring data tidak hilang saat user dihapus
✅ **Service Continuity:** Monitoring tetap berjalan tanpa interupsi
✅ **Historical Integrity:** Semua historical data tetap tersimpan
✅ **Admin Control:** Admin tetap bisa manage semua monitors
✅ **Flexible Management:** Monitors bisa di-reassign ke user lain

## Related Files

- Migration: `database/migrations/2026_01_12_000001_modify_monitors_created_by_on_delete.php`
- Model: `app/Models/Monitor.php`
- Controller: `app/Http/Controllers/Api/MonitorController.php`

## Date
January 12, 2026
