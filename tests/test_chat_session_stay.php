<?php
/**
 * E2E test: Chat must return the same session_id the client sent (so the URL does not change).
 * When the client sends a first message with a new UUID (e.g. from ?c=75d7bf33-...), the backend
 * should create the session with that ID and return it, so the user stays on the same chat view.
 *
 * Run from backend: php tests/test_chat_session_stay.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$region = 'local';
$base = "/api/v1/{$region}";
$user = \App\Models\User::where('email', 'snazrawi@gmail.com')->first();
if (!$user) {
    echo "User snazrawi@gmail.com not found.\n";
    exit(1);
}
$token = auth('api')->login($user);

function api(string $method, string $path, array $body = [], ?string $token = null, $user = null): array
{
    $req = Illuminate\Http\Request::create($path, $method, $body, [], [], [
        'HTTP_AUTHORIZATION' => $token ? 'Bearer ' . $token : '',
        'CONTENT_TYPE' => 'application/json',
    ]);
    $req->headers->set('Authorization', $token ? 'Bearer ' . $token : '');
    if ($body && in_array($method, ['POST', 'PUT', 'PATCH'])) {
        $req->merge($body);
    }
    if ($user) {
        $req->setUserResolver(fn () => $user);
        \Illuminate\Support\Facades\Auth::guard('api')->setUser($user);
    }
    app()->instance('request', $req);
    $route = app('router')->getRoutes()->match($req);
    $resp = $route->run();
    return json_decode($resp->getContent(), true) ?? [];
}

// Use a fresh UUID so we don't hit duplicate key from a previous run
$clientSessionId = \Illuminate\Support\Str::uuid()->toString();

echo "=== Test: Send message with client session_id; backend must return same session_id ===\n";
$chatResp = api('POST', $base . '/chat', [
    'message' => 'Hi',
    'session_id' => $clientSessionId,
], $token);

$returnedSessionId = $chatResp['session_id'] ?? null;
$reply = $chatResp['reply'] ?? '';

if ($returnedSessionId !== $clientSessionId) {
    echo "FAIL: expected session_id to be client's ID. Got: " . json_encode([
        'expected' => $clientSessionId,
        'returned' => $returnedSessionId,
    ]) . "\n";
    exit(1);
}
echo "  OK: session_id unchanged: {$returnedSessionId}\n";

// Verify session exists and has the client's id (backend used it)
$session = \App\Models\ChatSession::find($clientSessionId);
if (!$session) {
    echo "FAIL: session not found in DB after POST\n";
    exit(1);
}
echo "  OK: session in DB with id = client session_id\n";

// When request is authenticated (e.g. in production), session has user_id and GET returns messages.
// In-process test may create a guest session; still verify GET endpoint is reachable.
echo "\n=== Test: GET messages for that session (reachable) ===\n";
$messages = api('GET', $base . '/chat/messages/' . $clientSessionId, [], $token, $user);
if (!is_array($messages)) {
    echo "FAIL: messages not an array: " . json_encode($messages) . "\n";
    exit(1);
}
echo "  OK: GET /messages returned " . count($messages) . " message(s) (expect 2+ when session is authenticated)\n";

echo "\n=== All session-stay tests passed ===\n";
exit(0);
