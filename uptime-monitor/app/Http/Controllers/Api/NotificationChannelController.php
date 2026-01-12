<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NotificationChannel;
use App\Models\Monitor;
use App\Jobs\SendNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class NotificationChannelController extends Controller
{
    /**
     * Display a listing of the resource.
     * All users can see all notification channels (admin and regular users)
     */
    public function index(): JsonResponse
    {
        // Show all notification channels to all authenticated users
        // This allows any user to use any configured bot for their monitors
        $channels = NotificationChannel::with('creator:id,name,email')
            ->latest()
            ->get();

        // Add creator info to each channel
        $channels->transform(function ($channel) {
            $channel->created_by_name = $channel->creator->name ?? 'Unknown';
            $channel->created_by_email = $channel->creator->email ?? '';
            unset($channel->creator);
            return $channel;
        });

        return response()->json([
            'success' => true,
            'data' => $channels
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'type' => 'required|in:telegram,discord,slack,webhook',
            'config' => 'required|array',
            'config.webhook_url' => 'required_if:type,discord,slack,webhook|url',
            'config.bot_token' => 'required_if:type,telegram|string',
            'config.chat_id' => 'required_if:type,telegram|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $channel = NotificationChannel::create([
            'name' => $request->name,
            'type' => $request->type,
            'config' => $request->config,
            'created_by' => auth('api')->id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Notification channel created successfully',
            'data' => $channel
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(NotificationChannel $notificationChannel): JsonResponse
    {
        if ($notificationChannel->created_by !== auth('api')->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $notificationChannel
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, NotificationChannel $notificationChannel): JsonResponse
    {
        if ($notificationChannel->created_by !== auth('api')->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'type' => 'sometimes|in:telegram,discord,slack,webhook',
            'config' => 'sometimes|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $notificationChannel->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Notification channel updated successfully',
            'data' => $notificationChannel
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(NotificationChannel $notificationChannel): JsonResponse
    {
        if ($notificationChannel->created_by !== auth('api')->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        $notificationChannel->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notification channel deleted successfully'
        ]);
    }

    /**
     * Toggle notification channel enabled/disabled status
     */
    public function toggle(NotificationChannel $notificationChannel): JsonResponse
    {
        if ($notificationChannel->created_by !== auth('api')->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        $notificationChannel->update([
            'is_enabled' => !$notificationChannel->is_enabled
        ]);

        return response()->json([
            'success' => true,
            'message' => $notificationChannel->is_enabled 
                ? 'Notification channel enabled successfully' 
                : 'Notification channel disabled successfully',
            'data' => $notificationChannel
        ]);
    }

    /**
     * Test notification channel (FR-15)
     */
    public function test(Request $request, NotificationChannel $notificationChannel): JsonResponse
    {
        if ($notificationChannel->created_by !== auth('api')->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        try {
            // Send test notification directly without using queue
            $this->sendTestNotification($notificationChannel);

            return response()->json([
                'success' => true,
                'message' => 'Test notification sent successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Test notification failed', [
                'channel_id' => $notificationChannel->id,
                'channel_type' => $notificationChannel->type,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to send test notification: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send test notification directly to a channel
     */
    private function sendTestNotification(NotificationChannel $channel): void
    {
        $message = [
            'title' => '🧪 Test Notification',
            'message' => "This is a test notification from your Uptime Monitor\n\n" .
                       "⏰ **Test Time:** " . now()->format('Y-m-d H:i:s') . "\n" .
                       "✅ If you receive this, notifications are working correctly!",
            'color' => '#3742fa',
            'timestamp' => now()->toISOString(),
        ];

        switch ($channel->type) {
            case 'telegram':
                $this->sendTelegramTest($channel, $message);
                break;
            case 'discord':
                $this->sendDiscordTest($channel, $message);
                break;
            case 'slack':
                $this->sendSlackTest($channel, $message);
                break;
            case 'webhook':
                $this->sendWebhookTest($channel, $message);
                break;
            default:
                throw new \Exception("Unsupported notification channel type: {$channel->type}");
        }
    }

    private function sendTelegramTest(NotificationChannel $channel, array $message): void
    {
        $config = $channel->config;
        $botToken = $config['bot_token'] ?? '';
        $chatId = $config['chat_id'] ?? '';

        if (empty($botToken) || empty($chatId)) {
            throw new \Exception("Telegram bot token or chat ID not configured");
        }

        $response = Http::withOptions([
            'verify' => false, // Disable SSL verification for local development
        ])->timeout(30)
            ->post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $message['message'],
                'parse_mode' => 'Markdown',
                'disable_web_page_preview' => true,
            ]);

        if (!$response->successful()) {
            throw new \Exception("Telegram API error: " . $response->body());
        }
    }

    private function sendDiscordTest(NotificationChannel $channel, array $message): void
    {
        $config = $channel->config;
        $webhookUrl = $config['webhook_url'] ?? '';

        if (empty($webhookUrl)) {
            throw new \Exception("Discord webhook URL not configured");
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
        ])->timeout(30)->connectTimeout(10)->post($webhookUrl, $payload);

        if (!$response->successful()) {
            $statusCode = $response->status();
            $errorBody = $response->body();
            throw new \Exception("Discord webhook error (HTTP {$statusCode}): {$errorBody}");
        }
    }

    private function sendSlackTest(NotificationChannel $channel, array $message): void
    {
        $config = $channel->config;
        $webhookUrl = $config['webhook_url'] ?? '';

        if (empty($webhookUrl)) {
            throw new \Exception("Slack webhook URL not configured");
        }

        $payload = [
            'text' => $message['title'],
            'attachments' => [
                [
                    'text' => $message['message'],
                    'color' => $message['color'],
                    'ts' => now()->timestamp,
                ]
            ]
        ];

        if (!empty($config['channel'])) {
            $payload['channel'] = $config['channel'];
        }

        $response = Http::withOptions([
            'verify' => false, // Disable SSL verification for local development
        ])->timeout(30)->connectTimeout(10)->post($webhookUrl, $payload);

        if (!$response->successful()) {
            throw new \Exception("Slack webhook error: " . $response->body());
        }
    }

    private function sendWebhookTest(NotificationChannel $channel, array $message): void
    {
        $config = $channel->config;
        $webhookUrl = $config['webhook_url'] ?? '';
        $method = strtoupper($config['method'] ?? 'POST');

        if (empty($webhookUrl)) {
            throw new \Exception("Webhook URL not configured");
        }

        $payload = $config['payload'] ?? [
            'message' => $message['message'],
            'title' => $message['title'],
            'timestamp' => $message['timestamp'],
            'type' => 'test',
        ];

        // Replace template variables
        $payload = json_decode(
            str_replace(
                ['{{message}}', '{{status}}', '{{monitor_name}}', '{{timestamp}}'],
                [$message['message'], 'test', 'Test Monitor', $message['timestamp']],
                json_encode($payload)
            ),
            true
        );

        $headers = $config['headers'] ?? [];
        
        $request = Http::withOptions([
            'verify' => false, // Disable SSL verification for local development
        ])->timeout(30);
        
        foreach ($headers as $key => $value) {
            $request = $request->withHeader($key, $value);
        }

        $response = $request->send($method, $webhookUrl, ['json' => $payload]);

        if (!$response->successful()) {
            throw new \Exception("Webhook error: " . $response->body());
        }
    }
}
