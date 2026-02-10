<?php

// Script untuk setup Telegram Webhook
// Usage: php setup_telegram_webhook.php YOUR_BOT_TOKEN

if ($argc < 2) {
    echo "Usage: php setup_telegram_webhook.php <BOT_TOKEN>\n";
    echo "Example: php setup_telegram_webhook.php 8565885504:AAEVmg7bEPnF-sBrZoM9ratvv-b8fpaK-9o\n";
    exit(1);
}

$botToken = $argv[1];

echo "=== TELEGRAM WEBHOOK SETUP ===\n\n";

// Get your server URL (change this to your actual domain)
$webhookUrl = "http://localhost:8000/api/telegram/webhook";

// For production, use your actual domain:
// $webhookUrl = "https://yourdomain.com/api/telegram/webhook";

echo "Setting webhook to: {$webhookUrl}\n\n";

$url = "https://api.telegram.org/bot{$botToken}/setWebhook";

$data = [
    'url' => $webhookUrl,
    'allowed_updates' => ['message', 'callback_query']
];

$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => 'Content-Type: application/json',
        'content' => json_encode($data)
    ],
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false,
    ]
]);

$response = file_get_contents($url, false, $context);
$result = json_decode($response, true);

if (!isset($result['ok']) || !$result['ok']) {
    echo "❌ Error: " . ($result['description'] ?? 'Unknown error') . "\n";
    exit(1);
}

echo "✅ Webhook berhasil di-set!\n\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "📝 Langkah selanjutnya:\n";
echo "1. Pastikan Laravel server berjalan (php artisan serve)\n";
echo "2. Buka Telegram dan chat dengan bot Anda\n";
echo "3. Kirim /start untuk mendapatkan Chat ID\n";
echo "4. Copy Chat ID dan paste ke dashboard\n\n";
echo "💡 Untuk development lokal, gunakan ngrok:\n";
echo "   ngrok http 8000\n";
echo "   Lalu jalankan script ini dengan URL ngrok\n";
