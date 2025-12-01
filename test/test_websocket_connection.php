<?php

/**
 * Test WebSocket Connection
 * Tests if WebSocket server is accessible and can accept connections
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "🧪 WEBSOCKET CONNECTION TEST\n";
echo "============================\n\n";

// Test 1: Check Soketi directly
echo "1️⃣ Testing Soketi on localhost:6001...\n";
$ch = curl_init('http://127.0.0.1:6001');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 3);
curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode > 0 && $httpCode != 502) {
    echo "   ✅ Soketi is responding (HTTP {$httpCode})\n";
} else {
    echo "   ❌ Soketi is not responding (HTTP {$httpCode})\n";
    exit(1);
}
echo "\n";

// Test 2: Check via Nginx
echo "2️⃣ Testing WebSocket via Nginx proxy...\n";
$ch = curl_init('https://chat.akmicroservice.com/app/');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode > 0) {
    echo "   ✅ Nginx proxy is responding (HTTP {$httpCode})\n";
    if ($httpCode == 404) {
        echo "   ℹ️  404 is expected for plain HTTP GET (WebSocket needs upgrade headers)\n";
    }
} else {
    echo "   ❌ Nginx proxy is not responding\n";
    exit(1);
}
echo "\n";

// Test 3: Test WebSocket upgrade headers
echo "3️⃣ Testing WebSocket upgrade headers...\n";
$ch = curl_init('https://chat.akmicroservice.com/app/');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Connection: Upgrade',
    'Upgrade: websocket',
    'Sec-WebSocket-Version: 13',
    'Sec-WebSocket-Key: dGhlIHNhbXBsZSBub25jZQ==',
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$headers = curl_getinfo($ch, CURLINFO_HEADER_OUT);
curl_close($ch);

echo "   Response code: {$httpCode}\n";
if ($httpCode == 101) {
    echo "   ✅ WebSocket upgrade successful!\n";
} elseif ($httpCode == 404) {
    echo "   ⚠️  404 - This might be because Soketi needs the app key in the path\n";
    echo "   ℹ️  Full path should be: /app/akili-chat-key?protocol=7&client=js\n";
} else {
    echo "   ⚠️  Unexpected response code: {$httpCode}\n";
}
echo "\n";

// Test 4: Test with app key in path
echo "4️⃣ Testing with app key in path...\n";
$ch = curl_init('https://chat.akmicroservice.com/app/akili-chat-key?protocol=7&client=js&version=8.4.0');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Connection: Upgrade',
    'Upgrade: websocket',
    'Sec-WebSocket-Version: 13',
    'Sec-WebSocket-Key: dGhlIHNhbXBsZSBub25jZQ==',
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "   Response code: {$httpCode}\n";
if ($httpCode == 101) {
    echo "   ✅ WebSocket upgrade successful with app key!\n";
} elseif ($httpCode == 404) {
    echo "   ⚠️  404 - Check if Soketi is configured correctly\n";
} else {
    echo "   ⚠️  Response code: {$httpCode}\n";
}
echo "\n";

echo "============================\n";
echo "📊 SUMMARY\n";
echo "============================\n\n";
echo "✅ Backend WebSocket server is running\n";
echo "✅ Nginx proxy is configured\n";
echo "⚠️  WebSocket connection requires proper upgrade headers\n";
echo "⚠️  Frontend should connect to: wss://chat.akmicroservice.com/app/akili-chat-key\n";
echo "\n";
echo "The frontend is using Laravel Echo which should handle this automatically.\n";
echo "If connection still fails, check:\n";
echo "  1. Nginx has been reloaded: sudo systemctl reload nginx\n";
echo "  2. Soketi logs: tail -f /var/log/akili-websocket.log\n";
echo "  3. Nginx error logs: tail -f /var/log/nginx/chat.akmicroservice.error.log\n";

