<?php

namespace App\Observers;

use App\Models\NotificationChannel;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class NotificationChannelObserver
{
    /**
     * Handle the NotificationChannel "created" event.
     */
    public function created(NotificationChannel $channel): void
    {
        $this->setupTelegramWebhook($channel);
    }

    /**
     * Handle the NotificationChannel "updated" event.
     */
    public function updated(NotificationChannel $channel): void
    {
        $this->setupTelegramWebhook($channel);
    }

    /**
     * Setup Telegram webhook automatically
     */
    private function setupTelegramWebhook(NotificationChannel $channel): void
    {
        // Only process for Telegram channels
        if ($channel->type !== 'telegram') {
            return;
        }

        // Only setup if channel is enabled
        if (!$channel->is_enabled) {
            Log::info('Telegram channel disabled, skipping webhook setup', [
                'channel_id' => $channel->id,
                'channel_name' => $channel->name
            ]);
            return;
        }

        try {
            // Get bot token from config
            $config = is_string($channel->config) ? json_decode($channel->config, true) : $channel->config;
            $botToken = $config['bot_token'] ?? null;

            if (empty($botToken)) {
                Log::warning('Telegram bot token not found, skipping webhook setup', [
                    'channel_id' => $channel->id,
                    'channel_name' => $channel->name
                ]);
                return;
            }

            // Get webhook URL from APP_URL
            $webhookUrl = config('app.url') . '/api/telegram/webhook';

            // Skip if APP_URL is localhost (development without ngrok)
            if (strpos($webhookUrl, 'localhost') !== false && strpos($webhookUrl, 'https://') !== 0) {
                Log::info('Development environment detected (localhost), skipping auto webhook setup', [
                    'channel_id' => $channel->id,
                    'app_url' => config('app.url'),
                    'message' => 'Use ngrok or set APP_URL to https:// for auto webhook setup'
                ]);
                return;
            }

            Log::info('Setting up Telegram webhook automatically', [
                'channel_id' => $channel->id,
                'channel_name' => $channel->name,
                'webhook_url' => $webhookUrl
            ]);

            // Set webhook via Telegram API
            $response = Http::withOptions(['verify' => false])
                ->timeout(30)
                ->post("https://api.telegram.org/bot{$botToken}/setWebhook", [
                    'url' => $webhookUrl,
                    'allowed_updates' => ['message', 'callback_query'],
                    'drop_pending_updates' => false // Keep existing updates
                ]);

            if ($response->successful()) {
                $result = $response->json();
                
                if ($result['ok'] ?? false) {
                    Log::info('Telegram webhook set successfully', [
                        'channel_id' => $channel->id,
                        'channel_name' => $channel->name,
                        'webhook_url' => $webhookUrl,
                        'description' => $result['description'] ?? 'Success'
                    ]);

                    // Send test message to confirm setup
                    $this->sendSetupConfirmation($botToken, $config['chat_id'] ?? null, $channel->name);
                } else {
                    Log::error('Failed to set Telegram webhook', [
                        'channel_id' => $channel->id,
                        'error' => $result['description'] ?? 'Unknown error',
                        'response' => $result
                    ]);
                }
            } else {
                Log::error('Telegram API request failed', [
                    'channel_id' => $channel->id,
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Exception during Telegram webhook setup', [
                'channel_id' => $channel->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Send confirmation message to Telegram
     */
    private function sendSetupConfirmation(string $botToken, ?string $chatId, string $channelName): void
    {
        if (empty($chatId)) {
            return;
        }

        try {
            $message = "✅ *Bot Configuration Updated!*\n\n";
            $message .= "Channel: *{$channelName}*\n";
            $message .= "Status: Active ✅\n";
            $message .= "Webhook: Configured automatically\n\n";
            $message .= "📱 *Available Commands:*\n";
            $message .= "/start - Welcome message\n";
            $message .= "/status - Monitor status\n";
            $message .= "/incidents - Recent incidents\n";
            $message .= "/monitors - List all monitors\n";
            $message .= "/uptime - Uptime statistics\n";
            $message .= "/ping - Health check\n";
            $message .= "/help - All commands\n\n";
            $message .= "🎉 Bot is ready to use!";

            Http::withOptions(['verify' => false])
                ->timeout(10)
                ->post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => $message,
                    'parse_mode' => 'Markdown'
                ]);

            Log::info('Setup confirmation sent to Telegram', ['chat_id' => $chatId]);

        } catch (\Exception $e) {
            // Silent fail for confirmation message
            Log::debug('Failed to send setup confirmation', [
                'error' => $e->getMessage()
            ]);
        }
    }
}
