<?php

namespace App\Jobs;

use App\Models\Monitor;
use App\Models\NotificationChannel;
use App\Models\Incident;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class SendNotification implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $backoff = 5;

    protected Monitor $monitor;
    protected string $type; // 'down', 'up', 'test', 'critical_down'
    protected ?Incident $incident;
    protected ?NotificationChannel $channel;
    protected ?string $customMessage;
    protected array $metadata;

    /**
     * Create a new job instance.
     */
    public function __construct(Monitor $monitor, string $type, $messageOrIncident = null, ?Incident $incident = null, array $metadata = [])
    {
        $this->monitor = $monitor;
        $this->type = $type;
        $this->metadata = $metadata;
        
        // Set queue to notifications
        $this->onQueue('notifications');
        
        // Handle different parameter combinations for backward compatibility
        if (is_string($messageOrIncident)) {
            $this->customMessage = $messageOrIncident;
            $this->incident = $incident;
        } elseif ($messageOrIncident instanceof Incident) {
            $this->incident = $messageOrIncident;
            $this->customMessage = null;
        } elseif ($messageOrIncident instanceof NotificationChannel) {
            $this->channel = $messageOrIncident;
            $this->incident = $incident;
            $this->customMessage = null;
        } else {
            $this->incident = $incident;
            $this->customMessage = null;
        }
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Get notification channels for this monitor
        $channels = $this->channel ? [$this->channel] : $this->getMonitorChannels();

        foreach ($channels as $channel) {
            try {
                $this->sendToChannel($channel);
                
                Log::info("Notification sent successfully", [
                    'monitor_id' => $this->monitor->id ?? 'test',
                    'channel_id' => $channel->id,
                    'channel_type' => $channel->type,
                    'notification_type' => $this->type,
                ]);
            } catch (Exception $e) {
                Log::error("Failed to send notification", [
                    'monitor_id' => $this->monitor->id ?? 'test',
                    'channel_id' => $channel->id,
                    'channel_type' => $channel->type,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                
                // Re-throw exception for test notifications
                if ($this->type === 'test') {
                    throw $e;
                }
            }
        }

        // Update last notification sent timestamp (skip for test or if monitor not saved)
        if ($this->type !== 'test' && $this->monitor->exists) {
            $this->monitor->update(['last_notification_sent' => now()]);
        }
    }

    protected function getMonitorChannels(): array
    {
        $channelIds = $this->monitor->notification_channels ?? [];
        
        if (empty($channelIds)) {
            return [];
        }

        return NotificationChannel::whereIn('id', $channelIds)->get()->toArray();
    }

    protected function sendToChannel(NotificationChannel $channel): void
    {
        $message = $this->buildMessage();

        switch ($channel->type) {
            case 'telegram':
                $this->sendTelegram($channel, $message);
                break;
            case 'discord':
                $this->sendDiscord($channel, $message);
                break;
            case 'slack':
                $this->sendSlack($channel, $message);
                break;
            case 'webhook':
                $this->sendWebhook($channel, $message);
                break;
            default:
                throw new Exception("Unsupported notification channel type: {$channel->type}");
        }
    }

    protected function buildMessage(): array
    {
        $baseInfo = [
            'monitor_name' => $this->monitor->name,
            'monitor_type' => $this->monitor->type,
            'target' => $this->monitor->target,
            'timestamp' => now()->toISOString(),
        ];

        switch ($this->type) {
            case 'down':
                return array_merge($baseInfo, [
                    'status' => '🔴 DOWN',
                    'title' => "🚨 Monitor Down Alert",
                    'message' => "**{$this->monitor->name}** is DOWN!\n\n" .
                               "🎯 **Target:** {$this->monitor->target}\n" .
                               "⏰ **Time:** " . now()->format('Y-m-d H:i:s') . "\n" .
                               ($this->incident ? "📊 **Incident ID:** {$this->incident->id}\n" : "") .
                               "🔧 **Monitor Type:** {$this->monitor->type}",
                    'color' => '#ff4757', // Red
                ]);

            case 'critical_down':
                // Use custom message if provided, otherwise build default critical message
                $message = $this->customMessage ?: $this->buildCriticalDownMessage();
                
                return array_merge($baseInfo, [
                    'status' => '🚨 CRITICAL DOWN',
                    'title' => "🚨 CRITICAL SERVICE OUTAGE - IMMEDIATE ACTION REQUIRED",
                    'message' => $message,
                    'color' => '#ff3742', // Bright Red
                    'priority' => 'critical',
                    'consecutive_failures' => $this->monitor->consecutive_failures,
                ]);

            case 'up':
                $duration = $this->incident ? 
                    now()->diffInSeconds($this->incident->started_at) : 0;
                
                return array_merge($baseInfo, [
                    'status' => '🟢 UP',
                    'title' => "✅ Monitor Recovered",
                    'message' => "**{$this->monitor->name}** is back UP!\n\n" .
                               "🎯 **Target:** {$this->monitor->target}\n" .
                               "⏰ **Recovered at:** " . now()->format('Y-m-d H:i:s') . "\n" .
                               "⏱️ **Downtime:** " . gmdate('H:i:s', $duration) . "\n" .
                               "🔧 **Monitor Type:** {$this->monitor->type}",
                    'color' => '#2ed573', // Green
                ]);

            case 'test':
                return array_merge($baseInfo, [
                    'status' => '🧪 TEST',
                    'title' => "🧪 Test Notification",
                    'message' => "This is a test notification from **{$this->monitor->name}**\n\n" .
                               "🎯 **Target:** {$this->monitor->target}\n" .
                               "⏰ **Test Time:** " . now()->format('Y-m-d H:i:s') . "\n" .
                               "✅ If you receive this, notifications are working correctly!",
                    'color' => '#3742fa', // Blue
                ]);

            default:
                throw new Exception("Unknown notification type: {$this->type}");
        }
    }

    protected function sendTelegram(NotificationChannel $channel, array $message): void
    {
        $config = $channel->config;
        $botToken = $config['bot_token'] ?? '';
        $chatId = $config['chat_id'] ?? '';

        if (empty($botToken) || empty($chatId)) {
            throw new Exception("Telegram bot token or chat ID not configured");
        }

        $text = $message['message'];
        
        $response = Http::withOptions([
            'verify' => false, // Disable SSL verification for local development
        ])->timeout(30)
            ->post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'Markdown',
                'disable_web_page_preview' => true,
            ]);

        if (!$response->successful()) {
            throw new Exception("Telegram API error: " . $response->body());
        }
    }

    protected function sendDiscord(NotificationChannel $channel, array $message): void
    {
        $config = $channel->config;
        $webhookUrl = $config['webhook_url'] ?? '';

        if (empty($webhookUrl)) {
            throw new Exception("Discord webhook URL not configured");
        }

        $payload = [
            'embeds' => [
                [
                    'title' => $message['title'],
                    'description' => $message['message'],
                    'color' => hexdec(str_replace('#', '', $message['color'])),
                    'timestamp' => $message['timestamp'],
                    'footer' => [
                        'text' => 'Uptime Monitor',
                    ],
                ]
            ]
        ];

        $response = Http::withOptions([
            'verify' => false, // Disable SSL verification for local development
        ])->timeout(30)->post($webhookUrl, $payload);

        if (!$response->successful()) {
            $errorBody = $response->body();
            $statusCode = $response->status();
            throw new Exception("Discord webhook error (HTTP {$statusCode}): {$errorBody}");
        }
    }

    protected function sendSlack(NotificationChannel $channel, array $message): void
    {
        $config = $channel->config;
        $webhookUrl = $config['webhook_url'] ?? '';

        if (empty($webhookUrl)) {
            throw new Exception("Slack webhook URL not configured");
        }

        $payload = [
            'text' => $message['title'],
            'attachments' => [
                [
                    'color' => $message['color'],
                    'text' => $message['message'],
                    'ts' => now()->timestamp,
                ]
            ]
        ];

        $response = Http::withOptions([
            'verify' => false, // Disable SSL verification for local development
        ])->timeout(30)->post($webhookUrl, $payload);

        if (!$response->successful()) {
            throw new Exception("Slack webhook error: " . $response->body());
        }
    }

    protected function sendWebhook(NotificationChannel $channel, array $message): void
    {
        $config = $channel->config;
        $webhookUrl = $config['webhook_url'] ?? '';
        $headers = $config['headers'] ?? [];

        if (empty($webhookUrl)) {
            throw new Exception("Webhook URL not configured");
        }

        $payload = array_merge($message, [
            'monitor_id' => $this->monitor->id,
            'incident_id' => $this->incident?->id,
        ]);

        $response = Http::withOptions([
            'verify' => false, // Disable SSL verification for local development
        ])->withHeaders($headers)
            ->timeout(30)
            ->post($webhookUrl, $payload);

        if (!$response->successful()) {
            throw new Exception("Webhook error: " . $response->body());
        }
    }

    /**
     * Build critical down message when service has failed 20 consecutive times
     */
    protected function buildCriticalDownMessage(): string
    {
        $downtime = $this->calculateDowntimeDuration();
        
        return "🚨 **CRITICAL SERVICE OUTAGE ALERT** 🚨\n\n" .
               "**Service:** {$this->monitor->name}\n" .
               "**Target:** {$this->monitor->target}\n" .
               "**Status:** DOWN for {$this->monitor->consecutive_failures} consecutive checks\n" .
               "**Estimated Downtime:** ~{$downtime} minutes\n" .
               "**Last Error:** {$this->monitor->last_error}\n\n" .
               "⚠️ **IMMEDIATE ACTION REQUIRED** ⚠️\n" .
               "This service has been unresponsive for an extended period.\n" .
               "Please investigate and resolve this issue immediately.\n\n" .
               "**Incident Time:** " . now()->format('Y-m-d H:i:s T') . "\n" .
               "**Alert Generated:** " . now()->toISOString() . "\n" .
               ($this->incident ? "**Incident ID:** {$this->incident->id}" : "");
    }

    /**
     * Calculate approximate downtime duration in minutes
     */
    protected function calculateDowntimeDuration(): int
    {
        // Estimate downtime based on consecutive failures and check interval
        $estimatedDowntimeSeconds = $this->monitor->consecutive_failures * $this->monitor->interval_seconds;
        return (int) ceil($estimatedDowntimeSeconds / 60);
    }
}
