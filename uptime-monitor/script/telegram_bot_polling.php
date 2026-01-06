<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

if ($argc < 2) {
    echo "Usage: php telegram_bot_polling.php <BOT_TOKEN>\n";
    echo "Example: php telegram_bot_polling.php 8565885504:AAEVmg7bEPnF-sBrZoM9ratvv-b8fpaK-9o\n";
    exit(1);
}

$botToken = $argv[1];
$offset = 0;

echo "╔════════════════════════════════════════╗\n";
echo "║   TELEGRAM BOT POLLING - STARTED      ║\n";
echo "╚════════════════════════════════════════╝\n\n";
echo "🤖 Bot is now listening for commands...\n";
echo "💬 Send /start to your bot in Telegram\n";
echo "🛑 Press Ctrl+C to stop\n\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

while (true) {
    try {
        $response = Http::withOptions(['verify' => false])
            ->timeout(30)
            ->get("https://api.telegram.org/bot{$botToken}/getUpdates", [
                'offset' => $offset,
                'timeout' => 20
            ]);

        if (!$response->successful()) {
            echo "⚠️ API Error: " . $response->body() . "\n";
            sleep(5);
            continue;
        }

        $data = $response->json();

        if (!isset($data['ok']) || !$data['ok']) {
            echo "❌ Error: " . ($data['description'] ?? 'Unknown') . "\n";
            sleep(5);
            continue;
        }

        foreach ($data['result'] as $update) {
            $offset = $update['update_id'] + 1;

            if (isset($update['message'])) {
                $message = $update['message'];
                $chatId = $message['chat']['id'];
                $text = $message['text'] ?? '';
                $from = $message['from']['first_name'] ?? 'Unknown';

                $timestamp = date('H:i:s');
                echo "[{$timestamp}] 📨 Message from {$from} (Chat ID: {$chatId}): {$text}\n";

                // Forward to webhook controller
                try {
                    $webhookResponse = Http::withOptions(['verify' => false])
                        ->timeout(30)
                        ->post('http://localhost:8000/api/telegram/webhook', $update);

                    if ($webhookResponse->successful()) {
                        echo "[{$timestamp}] ✅ Command processed successfully\n";
                    } else {
                        echo "[{$timestamp}] ⚠️ Failed to process: " . $webhookResponse->body() . "\n";
                    }
                } catch (\Exception $e) {
                    echo "[{$timestamp}] ❌ Error: " . $e->getMessage() . "\n";
                }

                echo "\n";
            }
        }

        usleep(500000); // 0.5 second delay

    } catch (\Exception $e) {
        echo "❌ Exception: " . $e->getMessage() . "\n";
        sleep(5);
    }
}
