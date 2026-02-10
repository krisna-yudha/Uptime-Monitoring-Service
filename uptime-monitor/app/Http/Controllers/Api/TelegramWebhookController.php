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
                $page = is_numeric($param) ? (int)$param : 0;
                $this->sendStatus($chatId, $page);
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
        $message .= "Selamat datang! Bot ini mengirimkan notifikasi otomatis saat service down atau recovery.\n\n";
        
        $message .= "┌─────────────────────────┐\n";
        $message .= "│   📱 *MENU UTAMA*        │\n";
        $message .= "└─────────────────────────┘\n\n";
        
        $message .= "📊 *Status* - Health monitoring\n";
        $message .= "📋 *Monitors* - Ringkasan semua\n";
        $message .= "📁 *Groups* - Per kategori\n";
        $message .= "🚨 *Incidents* - Riwayat masalah\n";
        $message .= "📈 *Uptime* - Statistik\n";
        $message .= "❓ *Help* - Panduan lengkap\n\n";
        
        $message .= "💡 *Quick Tips:*\n";
        $message .= "• `/search api` untuk cari\n";
        $message .= "• `/group nama` tanpa {kurung}\n";
        $message .= "• Huruf besar/kecil sama saja\n\n";
        
        $message .= "━━━━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "🆔 *Chat ID:* `{$chatId}`\n";
        $message .= "Setup notifikasi di dashboard\n";
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
        $message = "╔══════════════════════════╗\n";
        $message .= "║  ❓ *PANDUAN BOT*         ║\n";
        $message .= "╚══════════════════════════╝\n\n";
        
        $message .= "🔍 *PERINTAH UTAMA:*\n\n";
        
        $message .= "📊 `/status` - Status monitoring\n";
        $message .= "📋 `/monitors` - Ringkasan monitor\n";
        $message .= "📁 `/groups` - Daftar group\n";
        $message .= "🚨 `/incidents` - Riwayat incident\n";
        $message .= "📈 `/uptime` - Statistik uptime\n\n";
        
        $message .= "🔎 *PENCARIAN:*\n\n";
        $message .= "`/search api` - Cari monitor\n";
        $message .= "`/monitor API Server` - Detail monitor\n";
        $message .= "`/group Production` - Monitor per group\n\n";
        
        $message .= "💡 *Tips:*\n";
        $message .= "• Tidak perlu gunakan {kurung}\n";
        $message .= "• Huruf besar/kecil sama saja\n";
        $message .= "• Bisa pakai sebagian nama\n\n";
        
        $message .= "━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "💬 *Notifikasi Otomatis:*\n";
        $message .= "✓ Alert saat service down\n";
        $message .= "✓ Alert saat service recovery\n";
        $message .= "✓ Real-time monitoring 24/7\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━━━━━";

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '📊 Status', 'callback_data' => 'status'],
                    ['text' => '🚨 Incidents', 'callback_data' => 'incidents'],
                ],
                [
                    ['text' => '📁 Groups', 'callback_data' => 'groups'],
                    ['text' => '📈 Uptime', 'callback_data' => 'uptime'],
                ],
            ]
        ];

        $this->sendMessage($chatId, $message, $keyboard);
    }

    private function sendStatus(string $chatId, int $page = 0): void
    {
        $allMonitors = Monitor::where('enabled', true)->get();
        
        if ($allMonitors->isEmpty()) {
            $this->sendMessage($chatId, "⚠️ Tidak ada monitor yang aktif.");
            return;
        }

        $message = "╔══════════════════════════╗\n";
        $message .= "║   📊 *STATUS MONITOR*     ║\n";
        $message .= "╚══════════════════════════╝\n\n";
        
        // Group monitors by group_name
        $grouped = $allMonitors->groupBy('group_name');
        
        // Sort groups: those with down monitors first
        $sortedGroups = $grouped->sortByDesc(function ($groupMonitors) {
            return $groupMonitors->where('last_status', 'down')->count();
        });
        
        // Calculate totals from all monitors
        $totalMonitors = $allMonitors->count();
        $totalUp = $allMonitors->where('last_status', 'up')->count();
        $totalDown = $allMonitors->where('last_status', 'down')->count();
        $totalUnknown = $allMonitors->where('last_status', '!=', 'up')
                                    ->where('last_status', '!=', 'down')
                                    ->count();
        $totalGroups = $grouped->count();
        
        // Pagination settings
        $perPage = 10;
        $groupsArray = $sortedGroups->values()->all();
        $totalPages = (int)ceil($totalGroups / $perPage);
        $currentPage = max(0, min($page, $totalPages - 1));
        $offset = $currentPage * $perPage;
        
        // Get groups for current page
        $pageGroups = array_slice($groupsArray, $offset, $perPage);
        
        // Show pagination info if there are multiple pages
        if ($totalPages > 1) {
            $message .= "📄 Halaman " . ($currentPage + 1) . " dari {$totalPages}\n";
            $message .= "━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        }
        
        foreach ($pageGroups as $groupName => $groupMonitors) {
            $group = $groupName ?? 'Uncategorized';
            $total = $groupMonitors->count();
            $up = $groupMonitors->where('last_status', 'up')->count();
            $down = $groupMonitors->where('last_status', 'down')->count();
            $unknown = $groupMonitors->where('last_status', '!=', 'up')
                                     ->where('last_status', '!=', 'down')
                                     ->count();
            
            // Calculate health percentage
            $healthPercent = $total > 0 ? ($up / $total) * 100 : 0;
            
            // Choose emoji based on health
            if ($down > 0) {
                $healthEmoji = '🔴';
            } elseif ($healthPercent >= 95) {
                $healthEmoji = '🟢';
            } elseif ($healthPercent >= 80) {
                $healthEmoji = '🟡';
            } else {
                $healthEmoji = '🔴';
            }
            
            $message .= "{$healthEmoji} *{$group}*\n";
            $message .= "┌─────────────────────\n";
            $message .= "│ 📊 Total: {$total} monitors\n";
            $message .= "│ 🟢 Up: {$up}";
            
            if ($down > 0) {
                $message .= " | 🔴 Down: {$down}";
            }
            if ($unknown > 0) {
                $message .= " | ⚪ Unknown: {$unknown}";
            }
            
            $message .= "\n│ 💚 Health: " . number_format($healthPercent, 1) . "%\n";
            $message .= "└─────────────────────\n\n";
        }
        
        // Overall summary (from ALL monitors)
        $overallHealth = $totalMonitors > 0 
            ? ($totalUp / $totalMonitors) * 100 
            : 0;
        $summaryEmoji = $totalDown > 0 ? '🔴' : ($overallHealth >= 95 ? '🟢' : '🟡');
        
        $message .= "━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "{$summaryEmoji} *OVERALL SUMMARY*\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "📊 Total: *{$totalMonitors}* monitors\n";
        $message .= "📁 Groups: *{$totalGroups}*\n";
        $message .= "🟢 Up: *{$totalUp}* | 🔴 Down: *{$totalDown}*";
        
        if ($totalUnknown > 0) {
            $message .= " | ⚪ Unknown: *{$totalUnknown}*";
        }
        
        $message .= "\n💚 Overall Health: " . number_format($overallHealth, 1) . "%";

        // Build keyboard with pagination
        $keyboard = ['inline_keyboard' => []];
        
        // Pagination buttons
        if ($totalPages > 1) {
            $navButtons = [];
            
            if ($currentPage > 0) {
                $navButtons[] = ['text' => '⬅️ Previous', 'callback_data' => 'status:' . ($currentPage - 1)];
            }
            
            if ($currentPage < $totalPages - 1) {
                $navButtons[] = ['text' => 'Next ➡️', 'callback_data' => 'status:' . ($currentPage + 1)];
            }
            
            if (!empty($navButtons)) {
                $keyboard['inline_keyboard'][] = $navButtons;
            }
        }
        
        // Regular buttons
        $keyboard['inline_keyboard'][] = [
            ['text' => '🔄 Refresh', 'callback_data' => 'status:' . $currentPage],
            ['text' => '📁 Groups', 'callback_data' => 'groups'],
        ];
        
        $keyboard['inline_keyboard'][] = [
            ['text' => '🚨 Incidents', 'callback_data' => 'incidents'],
            ['text' => '📈 Uptime', 'callback_data' => 'uptime'],
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
            $startedAt = \Carbon\Carbon::parse($incident->started_at);
            $group = $incident->monitor->group_name ?? 'Uncategorized';
            
            $message .= "*{$num}.* {$status} *{$incident->monitor->name}*\n";
            $message .= "   📁 {$group}\n";
            $message .= "   📅 {$startedAt->format('d/m H:i')}";
            
            if ($incident->resolved_at) {
                $resolvedAt = \Carbon\Carbon::parse($incident->resolved_at);
                $duration = $startedAt->diff($resolvedAt);
                
                if ($duration->h > 0) {
                    $durationText = "{$duration->h}j {$duration->i}m";
                } elseif ($duration->i > 0) {
                    $durationText = "{$duration->i}m";
                } else {
                    $durationText = "{$duration->s}s";
                }
                
                $message .= " - " . $resolvedAt->format('H:i');
                $message .= " (⏱️ {$durationText})\n";
            } else {
                $downtime = $startedAt->diffForHumans(null, true);
                $message .= " (⚠️ {$downtime})\n";
            }
            
            $message .= "\n";
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
        $message .= "║   📋 *RINGKASAN MONITOR*  ║\n";
        $message .= "╚══════════════════════════╝\n\n";
        
        $totalMonitors = $monitors->count();
        $totalEnabled = $monitors->where('enabled', true)->count();
        $totalUp = $monitors->where('last_status', 'up')->count();
        $totalDown = $monitors->where('last_status', 'down')->count();

        foreach ($grouped as $groupName => $groupMonitors) {
            $group = $groupName ?? 'Uncategorized';
            $total = $groupMonitors->count();
            $enabled = $groupMonitors->where('enabled', true)->count();
            $up = $groupMonitors->where('last_status', 'up')->count();
            $down = $groupMonitors->where('last_status', 'down')->count();
            
            $healthPercent = $total > 0 ? ($up / $total) * 100 : 0;
            $healthEmoji = $down > 0 ? '🔴' : ($healthPercent >= 95 ? '🟢' : '🟡');
            
            $message .= "{$healthEmoji} *{$group}*\n";
            $message .= "   📊 {$total} monitors | ✅ {$enabled} aktif\n";
            $message .= "   🟢 {$up} up | 🔴 {$down} down\n\n";
        }

        $message .= "━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "📊 *TOTAL SUMMARY*\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "📋 Total: *{$totalMonitors}* monitors\n";
        $message .= "✅ Aktif: *{$totalEnabled}*\n";
        $message .= "🟢 Up: *{$totalUp}* | 🔴 Down: *{$totalDown}*\n";
        $message .= "📁 Groups: *{$grouped->count()}*\n\n";
        $message .= "💡 Lihat detail: `/group {nama}`";

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '📁 Groups', 'callback_data' => 'groups'],
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
        $poor = [];     // < 90%
        $warning = [];  // 90-95%
        $good = [];     // 95-99%
        $excellent = []; // >= 99%

        foreach ($monitors as $monitor) {
            // Calculate uptime from last 24 hours or use stored value
            $uptime = $this->calculateMonitorUptime($monitor);
            
            if ($uptime < 90) {
                $poor[] = ['name' => $monitor->name, 'uptime' => $uptime];
            } elseif ($uptime < 95) {
                $warning[] = ['name' => $monitor->name, 'uptime' => $uptime];
            } elseif ($uptime < 99) {
                $good[] = ['name' => $monitor->name, 'uptime' => $uptime];
            } else {
                $excellent[] = ['name' => $monitor->name, 'uptime' => $uptime];
            }
            
            $totalUptime += $uptime;
            $count++;
        }
        
        // Show critical issues first
        if (!empty($poor)) {
            $message .= "🔴 *PERLU PERHATIAN (< 90%)*\n";
            foreach ($poor as $m) {
                $message .= "   • {$m['name']}: " . number_format($m['uptime'], 1) . "%\n";
            }
            $message .= "\n";
        }
        
        if (!empty($warning)) {
            $message .= "🟡 *WARNING (90-95%)*\n";
            foreach ($warning as $m) {
                $message .= "   • {$m['name']}: " . number_format($m['uptime'], 1) . "%\n";
            }
            $message .= "\n";
        }
        
        // Summary counts only for good and excellent
        if (!empty($good)) {
            $message .= "🟢 *GOOD (95-99%):* " . count($good) . " monitors\n\n";
        }
        
        if (!empty($excellent)) {
            $message .= "💚 *EXCELLENT (≥ 99%):* " . count($excellent) . " monitors\n\n";
        }

        $avgUptime = $count > 0 ? $totalUptime / $count : 0;
        $avgEmoji = $avgUptime >= 99 ? '💚' : ($avgUptime >= 95 ? '🟢' : ($avgUptime >= 90 ? '🟡' : '🔴'));
        
        $message .= "━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "{$avgEmoji} *Rata-rata Uptime*\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "📊 " . number_format($avgUptime, 2) . "%\n";
        $message .= "📋 {$count} monitors aktif\n";
        $message .= "⏱️ Based on last 24 hours";

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

    /**
     * Calculate uptime percentage for a monitor
     */
    private function calculateMonitorUptime($monitor): float
    {
        // Try to use uptime_percentage column if it exists and has value
        if (isset($monitor->uptime_percentage) && $monitor->uptime_percentage > 0) {
            return $monitor->uptime_percentage;
        }
        
        // Calculate from incidents in last 24 hours
        $last24Hours = now()->subDay();
        
        $incidents = Incident::where('monitor_id', $monitor->id)
            ->where('started_at', '>=', $last24Hours)
            ->get();
        
        if ($incidents->isEmpty()) {
            // No incidents in last 24 hours = 100% uptime
            return 100.0;
        }
        
        $totalDowntimeMinutes = 0;
        
        foreach ($incidents as $incident) {
            $startedAt = \Carbon\Carbon::parse($incident->started_at);
            
            // If still ongoing, calculate till now
            $endedAt = $incident->resolved_at 
                ? \Carbon\Carbon::parse($incident->resolved_at) 
                : now();
            
            // Only count time within last 24 hours
            $startedAt = $startedAt->max($last24Hours);
            
            $downtimeMinutes = $startedAt->diffInMinutes($endedAt);
            $totalDowntimeMinutes += $downtimeMinutes;
        }
        
        // Total minutes in 24 hours = 1440
        $totalMinutes = 1440;
        $uptimeMinutes = $totalMinutes - $totalDowntimeMinutes;
        
        // Ensure it doesn't go below 0
        $uptimeMinutes = max(0, $uptimeMinutes);
        
        $uptimePercentage = ($uptimeMinutes / $totalMinutes) * 100;
        
        return round($uptimePercentage, 2);
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
        // Clean input: remove brackets, trim, etc
        $cleanName = trim(str_replace(['{', '}', '[', ']', '(', ')'], '', $groupName));
        
        if (empty($cleanName)) {
            $this->sendMessage($chatId, "❌ Masukkan nama group!\n\nContoh: `/group Production`");
            return;
        }
        
        // Case-insensitive search
        $monitors = Monitor::whereRaw('LOWER(group_name) LIKE LOWER(?)', ["%{$cleanName}%"])->get();
        
        if ($monitors->isEmpty()) {
            // Suggest available groups
            $availableGroups = Monitor::select('group_name')
                ->distinct()
                ->whereNotNull('group_name')
                ->limit(5)
                ->pluck('group_name')
                ->toArray();
            
            $message = "❌ Group tidak ditemukan: `{$cleanName}`\n\n";
            
            if (!empty($availableGroups)) {
                $message .= "📁 *Group yang tersedia:*\n";
                foreach ($availableGroups as $group) {
                    $message .= "• {$group}\n";
                }
                $message .= "\nℹ️ Ketik: `/group {nama_group}`";
            } else {
                $message .= "Gunakan `/groups` untuk melihat semua group.";
            }
            
            $this->sendMessage($chatId, $message);
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
        // Clean input
        $cleanSearch = trim(str_replace(['{', '}', '[', ']', '(', ')'], '', $search));
        
        if (empty($cleanSearch)) {
            $this->sendMessage($chatId, "❌ Masukkan nama monitor!\n\nContoh: `/monitor API Server`");
            return;
        }
        
        // Case-insensitive search
        $monitor = Monitor::whereRaw('LOWER(name) LIKE LOWER(?)', ["%{$cleanSearch}%"])->first();
        
        if (!$monitor) {
            // Suggest similar monitors
            $similar = Monitor::whereRaw('LOWER(name) LIKE LOWER(?)', ["%{$cleanSearch}%"])
                ->orWhereRaw('LOWER(target) LIKE LOWER(?)', ["%{$cleanSearch}%"])
                ->limit(5)
                ->get(['name', 'group_name']);
            
            $message = "❌ Monitor tidak ditemukan: `{$cleanSearch}`\n\n";
            
            if ($similar->isNotEmpty()) {
                $message .= "🔍 *Monitor yang mirip:*\n";
                foreach ($similar as $m) {
                    $group = $m->group_name ?? 'Uncategorized';
                    $message .= "• {$m->name} ({$group})\n";
                }
                $message .= "\nℹ️ Ketik: `/monitor {nama}`";
            } else {
                $message .= "Gunakan `/search {keyword}` untuk mencari monitor.";
            }
            
            $this->sendMessage($chatId, $message);
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
        // Clean input
        $cleanKeyword = trim(str_replace(['{', '}', '[', ']', '(', ')'], '', $keyword));
        
        if (empty($cleanKeyword)) {
            $this->sendMessage($chatId, "❌ Masukkan keyword pencarian!\n\nContoh: `/search api`");
            return;
        }
        
        // Case-insensitive search across multiple fields
        $monitors = Monitor::where(function($query) use ($cleanKeyword) {
            $query->whereRaw('LOWER(name) LIKE LOWER(?)', ["%{$cleanKeyword}%"])
                  ->orWhereRaw('LOWER(target) LIKE LOWER(?)', ["%{$cleanKeyword}%"])
                  ->orWhereRaw('LOWER(group_name) LIKE LOWER(?)', ["%{$cleanKeyword}%"]);
        })->get();
        
        if ($monitors->isEmpty()) {
            // Count total monitors to give context
            $totalMonitors = Monitor::count();
            $message = "❌ Tidak ditemukan: `{$cleanKeyword}`\n\n";
            $message .= "📊 Total {$totalMonitors} monitors tersedia\n\n";
            $message .= "💡 *Tips pencarian:*\n";
            $message .= "• Coba keyword lebih pendek\n";
            $message .= "• Gunakan `/monitors` untuk ringkasan\n";
            $message .= "• Gunakan `/groups` untuk cari per group";
            
            $this->sendMessage($chatId, $message);
            return;
        }
        
        $message = "╔══════════════════════════╗\n";
        $message .= "║   🔍 *HASIL PENCARIAN*    ║\n";
        $message .= "╚══════════════════════════╝\n\n";
        $message .= "🔎 Keyword: `{$cleanKeyword}`\n";
        $message .= "📊 Ditemukan: *{$monitors->count()}* monitors\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        
        foreach ($monitors as $index => $monitor) {
            $num = $index + 1;
            $status = $monitor->last_status ?? 'unknown';
            $statusEmoji = $status === 'up' ? '🟢' : ($status === 'down' ? '🔴' : '⚪');
            $enabled = $monitor->enabled ? '✅' : '⏸️';
            $group = $monitor->group_name ?? 'Uncategorized';
            
            $message .= "*{$num}.* {$enabled} {$statusEmoji} *{$monitor->name}*\n";
            $message .= "   📁 {$group}\n";
            
            if ($monitor->uptime_percentage !== null) {
                $uptime = number_format($monitor->uptime_percentage, 1);
                $message .= "   📈 Uptime: {$uptime}%\n";
            }
            
            $message .= "\n";
        }
        
        $message .= "━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "💡 Detail: `/monitor {nama}`";
        
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
