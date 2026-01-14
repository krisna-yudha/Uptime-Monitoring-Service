<?php
/**
 * Test API Performance for Monitor Detail Endpoint
 * 
 * Usage: php test_api_performance.php <monitor_id> <api_url>
 * Example: php test_api_performance.php 11 http://localhost:8000/api/monitors/11
 */

if ($argc < 2) {
    echo "Usage: php test_api_performance.php <monitor_id> [api_url]\n";
    echo "Example: php test_api_performance.php 11\n";
    exit(1);
}

$monitorId = $argv[1];
$apiUrl = $argv[2] ?? "http://localhost:8000/api/monitors/{$monitorId}";

echo "🔍 Testing API Performance for Monitor #{$monitorId}\n";
echo "📍 URL: {$apiUrl}\n\n";

// Get auth token (assumes you have a test user)
// You may need to adjust this based on your auth setup
$token = getenv('API_TOKEN') ?: 'your-test-token-here';

$startTime = microtime(true);
$ch = curl_init();

curl_setopt_array($ch, [
    CURLOPT_URL => $apiUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        'Authorization: Bearer ' . $token
    ],
    CURLOPT_TIMEOUT => 30
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$totalTime = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
$error = curl_error($ch);

curl_close($ch);

$endTime = microtime(true);
$elapsedTime = ($endTime - $startTime) * 1000; // Convert to milliseconds

echo "📊 Performance Results:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "HTTP Status: {$httpCode}\n";
echo "Total Time: " . round($totalTime * 1000, 2) . " ms\n";
echo "Elapsed Time: " . round($elapsedTime, 2) . " ms\n";

if ($error) {
    echo "❌ Error: {$error}\n";
    exit(1);
}

if ($httpCode === 200) {
    $data = json_decode($response, true);
    
    if (isset($data['data'])) {
        $monitor = $data['data'];
        echo "\n📈 Monitor Data:\n";
        echo "Name: " . ($monitor['name'] ?? 'N/A') . "\n";
        echo "Type: " . ($monitor['type'] ?? 'N/A') . "\n";
        echo "Status: " . ($monitor['last_status'] ?? 'N/A') . "\n";
        
        // Check if avg calculations are present
        echo "\n⚡ Average Response Times:\n";
        echo "1h: " . ($monitor['avg_response_1h'] ?? 'N/A') . " ms\n";
        echo "24h: " . ($monitor['avg_response_24h'] ?? 'N/A') . " ms\n";
        echo "7d: " . ($monitor['avg_response_7d'] ?? 'N/A') . " ms\n";
        echo "30d: " . ($monitor['avg_response_30d'] ?? 'N/A') . " ms\n";
        echo "All-time: " . ($monitor['avg_response_all_time'] ?? 'N/A') . " ms\n";
        
        echo "\n✅ SUCCESS - API responded in " . round($elapsedTime, 2) . " ms\n";
        
        // Performance assessment
        if ($elapsedTime < 500) {
            echo "🚀 EXCELLENT - Very fast response!\n";
        } elseif ($elapsedTime < 2000) {
            echo "✓ GOOD - Acceptable response time\n";
        } elseif ($elapsedTime < 5000) {
            echo "⚠️ SLOW - Consider further optimization\n";
        } else {
            echo "❌ VERY SLOW - Needs immediate attention\n";
        }
    } else {
        echo "❌ Invalid response format\n";
        echo $response . "\n";
    }
} else {
    echo "❌ HTTP Error: {$httpCode}\n";
    echo $response . "\n";
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
