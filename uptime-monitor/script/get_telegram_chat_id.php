<?php

// Script untuk mendapatkan Chat ID Telegram
// Cara pakai:
// 1. Kirim pesan "/start" ke bot Telegram Anda
// 2. Jalankan: php get_telegram_chat_id.php YOUR_BOT_TOKEN

if ($argc < 2) {
    echo "Usage: php get_telegram_chat_id.php <BOT_TOKEN>\n";
    echo "Example: php get_telegram_chat_id.php 8565885504:AAEVmg7bEPnF-sBrZoM9ratvv-b8fpaK-9o\n";
    exit(1);
}

$botToken = $argv[1];

echo "=== TELEGRAM CHAT ID FINDER ===\n\n";
echo "Fetching updates from Telegram API...\n\n";

$url = "https://api.telegram.org/bot{$botToken}/getUpdates";

// Disable SSL verification for local development
$context = stream_context_create([
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false,
    ]
]);

$response = file_get_contents($url, false, $context);
$data = json_decode($response, true);

if (!isset($data['ok']) || !$data['ok']) {
    echo "❌ Error: " . ($data['description'] ?? 'Unknown error') . "\n";
    exit(1);
}

if (empty($data['result'])) {
    echo "⚠️  No messages found!\n\n";
    echo "📝 Steps to get your Chat ID:\n";
    echo "1. Open Telegram and search for your bot\n";
    echo "2. Send any message to your bot (e.g., /start)\n";
    echo "3. Run this script again\n";
    exit(0);
}

echo "✅ Found " . count($data['result']) . " update(s):\n\n";

$chatIds = [];

foreach ($data['result'] as $update) {
    if (isset($update['message']['chat'])) {
        $chat = $update['message']['chat'];
        $chatId = $chat['id'];
        $chatType = $chat['type'];
        $chatTitle = $chat['title'] ?? $chat['first_name'] ?? 'Unknown';
        
        if (!in_array($chatId, $chatIds)) {
            $chatIds[] = $chatId;
            
            echo "📱 Chat ID: {$chatId}\n";
            echo "   Type: {$chatType}\n";
            echo "   Name: {$chatTitle}\n";
            
            if ($chatType === 'private') {
                echo "   💡 Use this for private messages\n";
            } elseif ($chatType === 'group' || $chatType === 'supergroup') {
                echo "   💡 Use this for group notifications\n";
            }
            echo "\n";
        }
    }
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Copy salah satu Chat ID di atas dan paste ke field 'Chat ID' di aplikasi.\n";
echo "Jangan gunakan URL! Gunakan angka Chat ID saja.\n";
