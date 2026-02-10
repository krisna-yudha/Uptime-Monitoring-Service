<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Monitor;
use App\Models\Incident;
use App\Models\NotificationChannel;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class TelegramWebhookController extends Controller
{
    /**
     * Handle incoming webhook from Telegram
     */
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
                
                // Handle commands
                if (strpos($text, '/') === 0) {
                    $this->handleCommand($chatId, $text);
                }
            } elseif (isset($update['callback_query'])) {
                // Handle inline keyboard button callbacks
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

    /**
     * Handle inline keyboard callbacks
     */
    private function handleCallback(string $chatId, string $data, string $callbackId): void
    {
        Log::info('Handling callback', ['chat_id' => $chatId, 'data' => $data]);
        
        // Answer callback query to remove loading state
        $this->answerCallback($callbackId);
        
        // Parse callback data
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
            case 'groups':
                $this->sendMonitorGroups($chatId);
                break;
            case 'incidents':
                $this->sendIncidents($chatId, $param);
                break;
            case 'uptime':
                $this->sendUptime($chatId);
                break;
            case 'help':
                $this->sendHelp($chatId);
                break;
            case 'group':
                $this->sendGroupMonitors($chatId, $param);
                break;
            default:
                $this->sendMessage($chatId, "⚠️ Unknown action: {$action}");
        }
    }

    /**
     * Handle Telegram commands
     */
    private function handleCommand(string $chatId, string $command): void
    {
        // Parse command and arguments
        $parts = explode(' ', trim($command), 2);
        $cmd = strtolower($parts[0]);
        $args = $parts[1] ?? '';
        
        Log::info('Handling Telegram command', ['chat_id' => $chatId, 'command' => $cmd, 'args' => $args]);

        switch ($cmd) {
            case '/start':
                $this->sendStart($chatId);
                break;
            case '/help':
                $this->sendHelp($chatId);
                break;
            case '/status':
                $this->sendStatus($chatId);
                break;
            case '/incidents':
                $this->sendIncidents($chatId, $args);
                break;
            case '/monitors':
                $this->sendMonitors($chatId);
                break;
            case '/monitor':
                $this->sendMonitorDetail($chatId, $args);
                break;
            case '/groups':
                $this->sendMonitorGroups($chatId);
                break;
            case '/group':
                $this->sendGroupMonitors($chatId, $args);
                break;
            case '/search':
                $this->searchMonitors($chatId, $args);
                break;
            case '/subscribe':
                $this->subscribe($chatId);
                break;
            case '/unsubscribe':
                $this->unsubscribe($chatId);
                break;
            case '/uptime':
                $this->sendUptime($chatId);
                break;
            case '/ping':
                $this->sendPing($chatId);
                break;
            default:
                $this->sendUnknownCommand($chatId);
        }
    }

    private function sendStart(string $chatId): void
    {
        $message = "╔══════════════════════════╗\n";
        $message .= "║  🤖 *UPTIME MONITOR BOT*  ║\n";
        $message .= "╚══════════════════════════╝\n\n";
        $message .= "Selamat datang! Bot ini akan mengirimkan notifikasi otomatis ketika ada service yang down atau up kembali.\n\n";
        
        $message .= "┌─────────────────────────┐\n";
        $message .= "│   📱 *MENU UTAMA*        │\n";
        $message .= "└─────────────────────────┘\n\n";
        
        $message .= "Pilih menu di bawah untuk:\n";
        $message .= "• 📊 Lihat status semua monitor\n";
        $message .= "• 📋 Daftar monitor aktif\n";
        $message .= "• 📁 Group monitoring\n";
        $message .= "• 🚨 Laporan incident\n";
        $message .= "• 📈 Statistik uptime\n";
        $message .= "• ❓ Panduan lengkap\n\n";
        
        $message .= "━━━━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "💡 *Chat ID:* `{$chatId}`\n";
        $message .= "Gunakan Chat ID ini untuk setup notifikasi di dashboard.\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━━━━";

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '📊 Status', 'callback_data' => 'status'],
                    ['text' => '📋 Monitors', 'callback_data' => 'monitors'],
                ],
                [
                    ['text' => '📁 Groups', 'callback_data' => 'groups'],
                    ['text' => '🚨 Incidents', 'callback_data' => 'incidents'],
                ],
                [
                    ['text' => '📈 Uptime', 'callback_data' => 'uptime'],
                    ['text' => '❓ Help', 'callback_data' => 'help'],
                ],
            ]
        ];

        $this->sendMessage($chatId, $message, $keyboard);
    }

    private function sendHelp(string $chatId): void
    {
        $message = "╔════════════════════════════╗\n";
        $message .= "║ 📚 *PANDUAN PENGGUNAAN BOT* ║\n";
        $message .= "╚════════════════════════════╝\n\n";
        
        $message .= "┏━━━━━━━━━━━━━━━━━━━━━━━━┓\n";
        $message .= "┃ 📊 *MONITORING*         ┃\n";
        $message .= "┗━━━━━━━━━━━━━━━━━━━━━━━━┛\n";
        $message .= "• `/status` - Status semua monitor\n";
        $message .= "• `/monitors` - Daftar semua monitor\n";
        $message .= "• `/groups` - Daftar group monitor\n";
        $message .= "• `/group Production` - Monitor di group Production\n";
        $message .= "• `/monitor API Server` - Detail monitor tertentu\n";
        $message .= "• `/search api` - Cari monitor dengan keyword\n\n";
        
        $message .= "┏━━━━━━━━━━━━━━━━━━━━━━━━┓\n";
        $message .= "┃ 🚨 *INCIDENT*           ┃\n";
        $message .= "┗━━━━━━━━━━━━━━━━━━━━━━━━┛\n";
        $message .= "• `/incidents` - 10 incident terbaru\n";
        $message .= "• `/incidents open` - Incident aktif\n";
        $message .= "• `/incidents resolved` - Sudah teratasi\n";
        $message .= "• `/incidents today` - Incident hari ini\n\n";
        
        $message .= "┏━━━━━━━━━━━━━━━━━━━━━━━━┓\n";
        $message .= "┃ 📈 *STATISTICS*         ┃\n";
        $message .= "┗━━━━━━━━━━━━━━━━━━━━━━━━┛\n";
        $message .= "• `/uptime` - Statistik uptime semua monitor\n";
        $message .= "• `/ping` - Test koneksi bot\n\n";
        
        $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "💬 *Auto Notification:*\n";
        $message .= "✓ Service down → Notifikasi instant\n";
        $message .= "✓ Service up → Notifikasi recovery\n";
        $message .= "✓ Real-time monitoring 24/7\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        $message .= "💡 Tip: Gunakan `/search` untuk cari monitor cepat!";

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '📊 Lihat Status', 'callback_data' => 'status'],
                    ['text' => '🚨 Lihat Incidents', 'callback_data' => 'incidents'],
                ],
                [
                    ['text' => '📁 Lihat Groups', 'callback_data' => 'groups'],
                    ['text' => '📈 Lihat Uptime', 'callback_data' => 'uptime'],
                ],
            ]
        ];

        $this->sendMessage($chatId, $message, $keyboard);
    }

    private function sendStatus(string $chatId): void
    {
        $monitors = Monitor::where('enabled', true)->get();
        
        if ($monitors->isEmpty()) {
            $this->sendMessage($chatId, "⚠️ Tidak ada monitor yang aktif.");
            return;
        }

        $upCount = 0;
        $downCount = 0;
        $unknownCount = 0;
        
        $message = "╔══════════════════════════╗\n";
        $message .= "║   📊 *STATUS MONITOR*     ║\n";
        $message .= "╚══════════════════════════╝\n\n";
        
        // Group by status for better display
        $upMonitors = [];
        $downMonitors = [];
        $unknownMonitors = [];
        
        foreach ($monitors as $monitor) {
            $status = $monitor->last_status ?? 'unknown';
            
            if ($status === 'up') {
                $upMonitors[] = $monitor;
                $upCount++;
            } elseif ($status === 'down') {
                $downMonitors[] = $monitor;
                $downCount++;
            } else {
                $unknownMonitors[] = $monitor;
                $unknownCount++;
            }
        }
        
        // Show down monitors first (critical)
        if (!empty($downMonitors)) {
            $message .= "🔴 *DOWN ({$downCount})*\n";
            $message .= "━━━━━━━━━━━━━━━━━\n";
            foreach ($downMonitors as $monitor) {
                $group = $monitor->group_name ?? 'Uncategorized';
                $lastCheck = $monitor->last_checked_at 
                    ? \Carbon\Carbon::parse($monitor->last_checked_at)->diffForHumans() 
                    : 'Never';
                $message .= "❌ *{$monitor->name}*\n";
                $message .= "   📁 {$group} | 🔗 {$monitor->type}\n";
                $message .= "   ⏱️ {$lastCheck}\n\n";
            }
        }
        
        // Show unknown monitors
        if (!empty($unknownMonitors)) {
            $message .= "⚪ *UNKNOWN ({$unknownCount})*\n";
            $message .= "━━━━━━━━━━━━━━━━━\n";
            foreach ($unknownMonitors as $monitor) {
                $group = $monitor->group_name ?? 'Uncategorized';
                $message .= "⚪ *{$monitor->name}*\n";
                $message .= "   📁 {$group}\n\n";
            }
        }
        
        // Show up monitors (show first 5 only if many)
        if (!empty($upMonitors)) {
            $showCount = min(5, count($upMonitors));
            $message .= "✅ *UP ({$upCount})*\n";
            $message .= "━━━━━━━━━━━━━━━━━\n";
            
            for ($i = 0; $i < $showCount; $i++) {
                $monitor = $upMonitors[$i];
                $group = $monitor->group_name ?? 'Uncategorized';
                $message .= "✅ *{$monitor->name}* | 📁 {$group}\n";
            }
            
            if (count($upMonitors) > 5) {
                $remaining = count($upMonitors) - 5;
                $message .= "   ... dan {$remaining} monitor lainnya\n";
            }
        }
        
        $message .= "\n╔══════════════════════════╗\n";
        $message .= "║      *SUMMARY*            ║\n";
        $message .= "╚══════════════════════════╝\n";
        $message .= "📊 Total: *{$monitors->count()}* monitors\n";
        $message .= "✅ Up: *{$upCount}* | ❌ Down: *{$downCount}*";
        
        if ($unknownCount > 0) {
            $message .= " | ⚪ Unknown: *{$unknownCount}*";
        }

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '🔄 Refresh', 'callback_data' => 'status'],
                    ['text' => '📁 Groups', 'callback_data' => 'groups'],
                ],
                [
                    ['text' => '🚨 Incidents', 'callback_data' => 'incidents'],
                    ['text' => '📈 Uptime', 'callback_data' => 'uptime'],
                ],
            ]
        ];

        $this->sendMessage($chatId, $message, $keyboard);
    }

    private function sendIncidents(string $chatId, string $filter = ''): void
    {
        $query = Incident::with('monitor')->orderBy('started_at', 'desc');
        
        // Apply filters
        $filterText = '';
        switch (strtolower(trim($filter))) {
            case 'open':
                $query->where('status', 'open');
                $filterText = 'Open';
                break;
            case 'resolved':
                $query->where('status', 'resolved');
                $filterText = 'Resolved';
                break;
            case 'today':
                $query->whereDate('started_at', today());
                $filterText = 'Today';
                break;
            case 'week':
                $query->where('started_at', '>=', now()->subWeek());
                $filterText = 'This Week';
                break;
            default:
                $filterText = 'All';
        }
        
        $incidents = $query->limit(10)->get();
        $total = $query->count();

        if ($incidents->isEmpty()) {
            $msg = $filterText !== 'All' 
                ? "✅ Tidak ada incident {$filterText}!" 
                : "✅ Tidak ada incident!";
            $this->sendMessage($chatId, $msg);
            return;
        }

        $message = "╔══════════════════════════╗\n";
        $message .= "║   🚨 *INCIDENT REPORT*    ║\n";
        $message .= "╚══════════════════════════╝\n\n";
        
        if ($filterText !== 'All') {
            $message .= "🔍 Filter: *{$filterText}*\n";
            $message .= "━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        }

        foreach ($incidents as $index => $incident) {
            $num = $index + 1;
            $status = $incident->status === 'open' ? '🔴' : '✅';
            $statusText = strtoupper($incident->status);
            $startedAt = \Carbon\Carbon::parse($incident->started_at);
            $group = $incident->monitor->group_name ?? 'Uncategorized';
            
            $message .= "*{$num}.* {$status} *{$incident->monitor->name}*\n";
            $message .= "┌─────────────────────\n";
            $message .= "│ 📁 Group: {$group}\n";
            $message .= "│ 🔖 Status: {$statusText}\n";
            $message .= "│ 📅 Started: {$startedAt->format('d/m H:i')}\n";
            $message .= "│ ⏱️ {$startedAt->diffForHumans()}\n";
            
            if ($incident->resolved_at) {
                $resolvedAt = \Carbon\Carbon::parse($incident->resolved_at);
                $duration = $startedAt->diff($resolvedAt);
                $durationText = '';
                
                if ($duration->h > 0) {
                    $durationText = "{$duration->h}h {$duration->i}m";
                } elseif ($duration->i > 0) {
                    $durationText = "{$duration->i}m {$duration->s}s";
                } else {
                    $durationText = "{$duration->s}s";
                }
                
                $message .= "│ ✅ Resolved: {$resolvedAt->format('d/m H:i')}\n";
                $message .= "│ ⏳ Duration: {$durationText}\n";
            } else {
                $downtime = $startedAt->diffForHumans(null, true);
                $message .= "│ ⚠️ Downtime: {$downtime}\n";
            }
            
            if ($incident->error_message) {
                $error = strlen($incident->error_message) > 40 
                    ? substr($incident->error_message, 0, 40) . '...' 
                    : $incident->error_message;
                $message .= "│ ❗ Error: {$error}\n";
            }
            
            $message .= "└─────────────────────\n\n";
        }

        $message .= "━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "📊 Showing *{$incidents->count()}* of *{$total}* incidents\n\n";
        
        $message .= "🔍 *Available Filters:*\n";
        $message .= "`/incidents open` - Open only\n";
        $message .= "`/incidents resolved` - Resolved only\n";
        $message .= "`/incidents today` - Today only\n";
        $message .= "`/incidents week` - This week";

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '🔴 Open', 'callback_data' => 'incidents:open'],
                    ['text' => '✅ Resolved', 'callback_data' => 'incidents:resolved'],
                ],
                [
                    ['text' => '📅 Today', 'callback_data' => 'incidents:today'],
                    ['text' => '📆 This Week', 'callback_data' => 'incidents:week'],
                ],
                [
                    ['text' => '📋 All', 'callback_data' => 'incidents:'],
                    ['text' => '🔄 Refresh', 'callback_data' => 'incidents:' . strtolower(trim($filter))],
                ],
            ]
        ];

        $this->sendMessage($chatId, $message, $keyboard);
    }

    private function sendMonitors(string $chatId): void
    {
        $monitors = Monitor::all();

        if ($monitors->isEmpty()) {
            $this->sendMessage($chatId, "⚠️ Belum ada monitor.");
            return;
        }

        // Group monitors
        $grouped = $monitors->groupBy('group_name');
        
        $message = "╔══════════════════════════╗\n";
        $message .= "║   📋 *DAFTAR MONITOR*     ║\n";
        $message .= "╚══════════════════════════╝\n\n";
        
        $totalEnabled = $monitors->where('enabled', true)->count();
        $totalDisabled = $monitors->where('enabled', false)->count();

        foreach ($grouped as $groupName => $groupMonitors) {
            $group = $groupName ?? '📂 Uncategorized';
            $count = $groupMonitors->count();
            
            $message .= "📁 *{$group}* ({$count})\n";
            $message .= "━━━━━━━━━━━━━━━━━━━━━━━━\n";
            
            foreach ($groupMonitors as $monitor) {
                $enabled = $monitor->enabled ? '✅' : '⏸️';
                $status = $monitor->last_status ?? '⚪';
                $statusEmoji = $status === 'up' ? '✅' : ($status === 'down' ? '❌' : '⚪');
                $type = strtoupper($monitor->type);
                
                $message .= "{$enabled} {$statusEmoji} *{$monitor->name}*\n";
                $message .= "   🔗 {$type} | ⏱️ {$monitor->interval_seconds}s\n";
            }
            
            $message .= "\n";
        }

        $message .= "━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "📊 *Summary:*\n";
        $message .= "Total: *{$monitors->count()}* monitors\n";
        $message .= "✅ Active: *{$totalEnabled}* | ⏸️ Paused: *{$totalDisabled}*\n";
        $message .= "📁 Groups: *{$grouped->count()}*\n\n";
        $message .= "💡 Gunakan:\n";
        $message .= "`/groups` - Lihat semua group\n";
        $message .= "`/group {nama}` - Monitor per group\n";
        $message .= "`/monitor {nama}` - Detail monitor";

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '📁 Lihat Groups', 'callback_data' => 'groups'],
                    ['text' => '📊 Status', 'callback_data' => 'status'],
                ],
                [
                    ['text' => '🔄 Refresh', 'callback_data' => 'monitors'],
                ],
            ]
        ];

        $this->sendMessage($chatId, $message, $keyboard);
    }

    private function sendUptime(string $chatId): void
    {
        $monitors = Monitor::where('enabled', true)->get();

        if ($monitors->isEmpty()) {
            $this->sendMessage($chatId, "⚠️ Tidak ada monitor aktif.");
            return;
        }

        $message = "╔══════════════════════════╗\n";
        $message .= "║  📈 *STATISTIK UPTIME*    ║\n";
        $message .= "╚══════════════════════════╝\n\n";

        $totalUptime = 0;
        $count = 0;
        
        // Group by uptime range
        $excellent = []; // >= 99%
        $good = []; // 95-99%
        $warning = []; // 90-95%
        $poor = []; // < 90%

        foreach ($monitors as $monitor) {
            $uptime = $monitor->uptime_percentage ?? 0;
            
            if ($uptime >= 99) {
                $excellent[] = ['name' => $monitor->name, 'uptime' => $uptime];
            } elseif ($uptime >= 95) {
                $good[] = ['name' => $monitor->name, 'uptime' => $uptime];
            } elseif ($uptime >= 90) {
                $warning[] = ['name' => $monitor->name, 'uptime' => $uptime];
            } else {
                $poor[] = ['name' => $monitor->name, 'uptime' => $uptime];
            }
            
            $totalUptime += $uptime;
            $count++;
        }
        
        // Show poor first (critical)
        if (!empty($poor)) {
            $message .= "🔴 *POOR (< 90%)*\n";
            foreach ($poor as $m) {
                $message .= "   • {$m['name']}: " . number_format($m['uptime'], 2) . "%\n";
            }
            $message .= "\n";
        }
        
        if (!empty($warning)) {
            $message .= "🟡 *WARNING (90-95%)*\n";
            foreach ($warning as $m) {
                $message .= "   • {$m['name']}: " . number_format($m['uptime'], 2) . "%\n";
            }
            $message .= "\n";
        }
        
        if (!empty($good)) {
            $message .= "🟢 *GOOD (95-99%)*\n";
            foreach ($good as $m) {
                $message .= "   • {$m['name']}: " . number_format($m['uptime'], 2) . "%\n";
            }
            $message .= "\n";
        }
        
        if (!empty($excellent)) {
            $message .= "💚 *EXCELLENT (≥ 99%)*\n";
            $showCount = min(5, count($excellent));
            for ($i = 0; $i < $showCount; $i++) {
                $m = $excellent[$i];
                $message .= "   • {$m['name']}: " . number_format($m['uptime'], 2) . "%\n";
            }
            if (count($excellent) > 5) {
                $remaining = count($excellent) - 5;
                $message .= "   ... dan {$remaining} monitor lainnya\n";
            }
            $message .= "\n";
        }

        $avgUptime = $count > 0 ? $totalUptime / $count : 0;
        $avgEmoji = $avgUptime >= 99 ? '💚' : ($avgUptime >= 95 ? '🟢' : ($avgUptime >= 90 ? '🟡' : '🔴'));
        
        $message .= "━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "{$avgEmoji} *Average Uptime:* " . number_format($avgUptime, 2) . "%\n";
        $message .= "📊 Total Monitors: {$count}";

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '🔄 Refresh', 'callback_data' => 'uptime'],
                    ['text' => '📊 Status', 'callback_data' => 'status'],
                ],
                [
                    ['text' => '📁 Groups', 'callback_data' => 'groups'],
                    ['text' => '🚨 Incidents', 'callback_data' => 'incidents'],
                ],
            ]
        ];

        $this->sendMessage($chatId, $message, $keyboard);
    }

    private function sendMonitorGroups(string $chatId): void
    {
        $monitors = Monitor::all();
        
        if ($monitors->isEmpty()) {
            $this->sendMessage($chatId, "⚠️ Belum ada monitor.");
            return;
        }
        
        $grouped = $monitors->groupBy('group_name');
        
        $message = "╔══════════════════════════╗\n";
        $message .= "║   📁 *MONITOR GROUPS*     ║\n";
        $message .= "╚══════════════════════════╝\n\n";
        
        foreach ($grouped as $groupName => $groupMonitors) {
            $group = $groupName ?? 'Uncategorized';
            $total = $groupMonitors->count();
            $active = $groupMonitors->where('enabled', true)->count();
            $up = $groupMonitors->where('last_status', 'up')->count();
            $down = $groupMonitors->where('last_status', 'down')->count();
            
            $healthPercent = $total > 0 ? ($up / $total) * 100 : 0;
            $healthEmoji = $healthPercent >= 95 ? '🟢' : ($healthPercent >= 80 ? '🟡' : '🔴');
            
            $message .= "{$healthEmoji} *{$group}*\n";
            $message .= "┌─────────────────────\n";
            $message .= "│ 📊 Total: {$total} monitors\n";
            $message .= "│ ✅ Active: {$active}\n";
            $message .= "│ 🟢 Up: {$up} | 🔴 Down: {$down}\n";
            $message .= "│ 💚 Health: " . number_format($healthPercent, 1) . "%\n";
            $message .= "└─────────────────────\n\n";
        }
        
        $message .= "━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "📁 Total Groups: *{$grouped->count()}*\n\n";
        $message .= "💡 Untuk melihat detail group:\n";
        $message .= "`/group {nama_group}`";
        
        // Create keyboard with group buttons (max 2 per row, max 10 groups)
        $buttons = [];
        $count = 0;
        foreach ($grouped as $groupName => $groupMonitors) {
            if ($count >= 10) break;
            $group = $groupName ?? 'Uncategorized';
            $up = $groupMonitors->where('last_status', 'up')->count();
            $total = $groupMonitors->count();
            $emoji = ($up / $total) >= 0.95 ? '🟢' : (($up / $total) >= 0.8 ? '🟡' : '🔴');
            
            $buttons[] = ['text' => "{$emoji} {$group}", 'callback_data' => "group:{$group}"];
            $count++;
        }
        
        // Arrange buttons in rows of 2
        $keyboard = ['inline_keyboard' => []];
        for ($i = 0; $i < count($buttons); $i += 2) {
            $row = [$buttons[$i]];
            if (isset($buttons[$i + 1])) {
                $row[] = $buttons[$i + 1];
            }
            $keyboard['inline_keyboard'][] = $row;
        }
        
        // Add refresh button
        $keyboard['inline_keyboard'][] = [
            ['text' => '🔄 Refresh', 'callback_data' => 'groups'],
            ['text' => '📊 Status', 'callback_data' => 'status'],
        ];
        
        $this->sendMessage($chatId, $message, $keyboard);
    }

    private function sendGroupMonitors(string $chatId, string $groupName): void
    {
        if (empty(trim($groupName))) {
            $this->sendMessage($chatId, "❌ Masukkan nama group!\n\nContoh: `/group Production`");
            return;
        }
        
        $monitors = Monitor::where('group_name', 'LIKE', "%{$groupName}%")->get();
        
        if ($monitors->isEmpty()) {
            $this->sendMessage($chatId, "❌ Group '*{$groupName}*' tidak ditemukan.\n\nGunakan `/groups` untuk melihat daftar group.");
            return;
        }
        
        $actualGroup = $monitors->first()->group_name ?? 'Uncategorized';
        
        $message = "╔══════════════════════════╗\n";
        $message .= "║   📁 *GROUP: {$actualGroup}*   ║\n";
        $message .= "╚══════════════════════════╝\n\n";
        
        $up = 0;
        $down = 0;
        $unknown = 0;
        
        foreach ($monitors as $monitor) {
            $status = $monitor->last_status ?? 'unknown';
            $enabled = $monitor->enabled ? '✅' : '⏸️';
            $statusEmoji = $status === 'up' ? '🟢' : ($status === 'down' ? '🔴' : '⚪');
            $type = strtoupper($monitor->type);
            
            $message .= "{$enabled} {$statusEmoji} *{$monitor->name}*\n";
            $message .= "   🔗 {$type} → {$monitor->target}\n";
            $message .= "   ⏱️ Interval: {$monitor->interval_seconds}s\n";
            
            if ($monitor->last_checked_at) {
                $lastCheck = \Carbon\Carbon::parse($monitor->last_checked_at)->diffForHumans();
                $message .= "   🕐 Last check: {$lastCheck}\n";
            }
            
            if ($monitor->uptime_percentage !== null) {
                $uptime = number_format($monitor->uptime_percentage, 2);
                $message .= "   📈 Uptime: {$uptime}%\n";
            }
            
            $message .= "\n";
            
            if ($status === 'up') $up++;
            elseif ($status === 'down') $down++;
            else $unknown++;
        }
        
        $total = $monitors->count();
        $healthPercent = $total > 0 ? ($up / $total) * 100 : 0;
        
        $message .= "━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "📊 *Group Statistics:*\n";
        $message .= "Total: {$total} monitors\n";
        $message .= "🟢 Up: {$up} | 🔴 Down: {$down}";
        if ($unknown > 0) {
            $message .= " | ⚪ Unknown: {$unknown}";
        }
        $message .= "\n💚 Health: " . number_format($healthPercent, 1) . "%";
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '🔄 Refresh', 'callback_data' => "group:{$actualGroup}"],
                    ['text' => '📁 All Groups', 'callback_data' => 'groups'],
                ],
                [
                    ['text' => '📊 Status', 'callback_data' => 'status'],
                    ['text' => '🚨 Incidents', 'callback_data' => 'incidents'],
                ],
            ]
        ];
        
        $this->sendMessage($chatId, $message, $keyboard);
    }

    private function sendMonitorDetail(string $chatId, string $search): void
    {
        if (empty(trim($search))) {
            $this->sendMessage($chatId, "❌ Masukkan nama monitor!\n\nContoh: `/monitor API Server`");
            return;
        }
        
        $monitor = Monitor::where('name', 'LIKE', "%{$search}%")->first();
        
        if (!$monitor) {
            $this->sendMessage($chatId, "❌ Monitor '*{$search}*' tidak ditemukan.\n\nGunakan `/search {keyword}` untuk mencari monitor.");
            return;
        }
        
        $status = $monitor->last_status ?? 'unknown';
        $statusEmoji = $status === 'up' ? '🟢' : ($status === 'down' ? '🔴' : '⚪');
        $enabled = $monitor->enabled ? '✅ Active' : '⏸️ Paused';
        
        $message = "╔══════════════════════════╗\n";
        $message .= "║   {$statusEmoji} *MONITOR DETAIL*   ║\n";
        $message .= "╚══════════════════════════╝\n\n";
        
        $message .= "📌 *{$monitor->name}*\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        
        $message .= "┌─ *Basic Info* ─────────\n";
        $message .= "│ 🔖 Status: {$enabled}\n";
        $message .= "│ 📁 Group: " . ($monitor->group_name ?? 'Uncategorized') . "\n";
        $message .= "│ 🔗 Type: " . strtoupper($monitor->type) . "\n";
        $message .= "│ 🎯 Target: `{$monitor->target}`\n";
        $message .= "│ ⏱️ Interval: {$monitor->interval_seconds}s\n";
        $message .= "└────────────────────────\n\n";
        
        $message .= "┌─ *Current Status* ─────\n";
        $message .= "│ {$statusEmoji} Status: " . strtoupper($status) . "\n";
        
        if ($monitor->last_checked_at) {
            $lastCheck = \Carbon\Carbon::parse($monitor->last_checked_at);
            $message .= "│ 🕐 Last check: {$lastCheck->format('d/m/Y H:i:s')}\n";
            $message .= "│ ⏱️ {$lastCheck->diffForHumans()}\n";
        }
        
        if ($monitor->response_time !== null) {
            $message .= "│ ⚡ Response: {$monitor->response_time}ms\n";
        }
        
        $message .= "└────────────────────────\n\n";
        
        $message .= "┌─ *Statistics* ─────────\n";
        if ($monitor->uptime_percentage !== null) {
            $uptime = number_format($monitor->uptime_percentage, 2);
            $uptimeEmoji = $monitor->uptime_percentage >= 99 ? '💚' : 
                          ($monitor->uptime_percentage >= 95 ? '🟢' : 
                          ($monitor->uptime_percentage >= 90 ? '🟡' : '🔴'));
            $message .= "│ {$uptimeEmoji} Uptime: {$uptime}%\n";
        }
        
        // Get recent incidents
        $recentIncidents = Incident::where('monitor_id', $monitor->id)
            ->orderBy('started_at', 'desc')
            ->limit(3)
            ->count();
        
        if ($recentIncidents > 0) {
            $message .= "│ 🚨 Recent incidents: {$recentIncidents}\n";
        }
        
        $message .= "└────────────────────────\n\n";
        
        if ($monitor->last_error) {
            $error = strlen($monitor->last_error) > 60 
                ? substr($monitor->last_error, 0, 60) . '...' 
                : $monitor->last_error;
            $message .= "⚠️ *Last Error:*\n";
            $message .= "`{$error}`\n\n";
        }
        
        $message .= "━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "💡 Tips:\n";
        $message .= "• `/incidents` - Lihat semua incident\n";
        $message .= "• `/group " . ($monitor->group_name ?? 'Uncategorized') . "` - Monitor di group ini";
        
        $this->sendMessage($chatId, $message);
    }

    private function searchMonitors(string $chatId, string $keyword): void
    {
        if (empty(trim($keyword))) {
            $this->sendMessage($chatId, "❌ Masukkan keyword pencarian!\n\nContoh: `/search api`");
            return;
        }
        
        $monitors = Monitor::where('name', 'LIKE', "%{$keyword}%")
            ->orWhere('target', 'LIKE', "%{$keyword}%")
            ->orWhere('group_name', 'LIKE', "%{$keyword}%")
            ->get();
        
        if ($monitors->isEmpty()) {
            $this->sendMessage($chatId, "❌ Tidak ada monitor dengan keyword '*{$keyword}*'\n\nCoba keyword lain atau gunakan `/monitors` untuk melihat semua.");
            return;
        }
        
        $message = "╔══════════════════════════╗\n";
        $message .= "║   🔍 *SEARCH RESULTS*     ║\n";
        $message .= "╚══════════════════════════╝\n\n";
        $message .= "Keyword: `{$keyword}`\n";
        $message .= "Found: *{$monitors->count()}* monitors\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        
        foreach ($monitors as $index => $monitor) {
            $num = $index + 1;
            $status = $monitor->last_status ?? 'unknown';
            $statusEmoji = $status === 'up' ? '🟢' : ($status === 'down' ? '🔴' : '⚪');
            $enabled = $monitor->enabled ? '✅' : '⏸️';
            $group = $monitor->group_name ?? 'Uncategorized';
            $type = strtoupper($monitor->type);
            
            $message .= "*{$num}.* {$enabled} {$statusEmoji} *{$monitor->name}*\n";
            $message .= "   📁 {$group} | 🔗 {$type}\n";
            $message .= "   🎯 {$monitor->target}\n";
            
            if ($monitor->uptime_percentage !== null) {
                $uptime = number_format($monitor->uptime_percentage, 2);
                $message .= "   📈 {$uptime}%\n";
            }
            
            $message .= "\n";
        }
        
        $message .= "━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "💡 Untuk detail monitor:\n";
        $message .= "`/monitor {nama}`";
        
        $this->sendMessage($chatId, $message);
    }

    private function subscribe(string $chatId): void
    {
        $message = "ℹ️ *Fitur Subscribe*\n\n";
        $message .= "Untuk mengaktifkan notifikasi:\n";
        $message .= "1. Buka dashboard web\n";
        $message .= "2. Masuk ke menu Notification Channels\n";
        $message .= "3. Tambah channel Telegram baru\n";
        $message .= "4. Masukkan Chat ID: `{$chatId}`\n\n";
        $message .= "Setelah itu, Anda akan otomatis menerima notifikasi!";

        $this->sendMessage($chatId, $message);
    }

    private function unsubscribe(string $chatId): void
    {
        $message = "ℹ️ *Fitur Unsubscribe*\n\n";
        $message .= "Untuk menonaktifkan notifikasi:\n";
        $message .= "1. Buka dashboard web\n";
        $message .= "2. Masuk ke menu Notification Channels\n";
        $message .= "3. Disable atau hapus channel dengan Chat ID: `{$chatId}`";

        $this->sendMessage($chatId, $message);
    }

    private function sendPing(string $chatId): void
    {
        $message = "🏓 Pong! Bot aktif dan berjalan.\n\n";
        $message .= "⏰ " . now()->format('d/m/Y H:i:s');

        $this->sendMessage($chatId, $message);
    }

    private function sendUnknownCommand(string $chatId): void
    {
        $message = "❓ Perintah tidak dikenali.\n\n";
        $message .= "Ketik /help untuk melihat daftar perintah yang tersedia.";

        $this->sendMessage($chatId, $message);
    }

    /**
     * Send message to Telegram
     */
    private function sendMessage(string $chatId, string $text, ?array $keyboard = null): void
    {
        Log::info('Attempting to send Telegram message', ['chat_id' => $chatId, 'text_length' => strlen($text)]);
        
        // Get bot token from first active Telegram channel
        $channel = NotificationChannel::where('type', 'telegram')
            ->where('is_enabled', true)
            ->first();

        if (!$channel) {
            Log::warning('No active Telegram channel found for command response');
            return;
        }

        // Decode config if it's JSON string
        $config = is_string($channel->config) ? json_decode($channel->config, true) : $channel->config;
        $botToken = $config['bot_token'] ?? '';

        if (empty($botToken)) {
            Log::error('Bot token not configured', ['config' => $config]);
            return;
        }

        Log::info('Sending message to Telegram API', ['bot_token_length' => strlen($botToken)]);

        try {
            $payload = [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'Markdown',
                'disable_web_page_preview' => true,
            ];
            
            if ($keyboard !== null) {
                $payload['reply_markup'] = json_encode($keyboard);
            }
            
            $response = Http::withOptions(['verify' => false])
                ->timeout(30)
                ->post("https://api.telegram.org/bot{$botToken}/sendMessage", $payload);

            if (!$response->successful()) {
                Log::error('Failed to send Telegram message', [
                    'chat_id' => $chatId,
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
            } else {
                Log::info('Telegram message sent successfully', ['chat_id' => $chatId]);
            }
        } catch (\Exception $e) {
            Log::error('Telegram send message error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Answer callback query to remove loading state
     */
    private function answerCallback(string $callbackId, ?string $text = null): void
    {
        $channel = NotificationChannel::where('type', 'telegram')
            ->where('is_enabled', true)
            ->first();

        if (!$channel) {
            return;
        }

        $config = is_string($channel->config) ? json_decode($channel->config, true) : $channel->config;
        $botToken = $config['bot_token'] ?? '';

        if (empty($botToken)) {
            return;
        }

        try {
            $payload = ['callback_query_id' => $callbackId];
            
            if ($text !== null) {
                $payload['text'] = $text;
            }
            
            Http::withOptions(['verify' => false])
                ->timeout(10)
                ->post("https://api.telegram.org/bot{$botToken}/answerCallbackQuery", $payload);
        } catch (\Exception $e) {
            Log::error('Answer callback error', ['error' => $e->getMessage()]);
        }
    }
}
