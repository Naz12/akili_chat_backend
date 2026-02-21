<?php
/**
 * E2E test: Chat session creation and continuation for signed-in user and guest.
 * - Login with snazrawi@gmail.com / test123$, create chat, continue chat, verify session in list.
 * - Guest: create chat with X-Guest-UUID, continue chat, verify session in list.
 *
 * Run: php tests/test_chat_sessions_signed_in_and_guest.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$region = 'local';
$base = "/api/v1/{$region}";

function dispatchRequest(string $method, string $path, array $body = [], ?string $bearer = null, ?string $guestUuid = null): array
{
    $uri = $path;
    $server = ['CONTENT_TYPE' => 'application/json'];
    $content = ($body && in_array($method, ['POST', 'PUT', 'PATCH'])) ? json_encode($body) : '';
    $req = Illuminate\Http\Request::create($uri, $method, [], [], [], $server, $content);
    $req->headers->set('Accept', 'application/json');
    if ($bearer) {
        $req->headers->set('Authorization', 'Bearer ' . $bearer);
    }
    if ($guestUuid) {
        $req->headers->set('X-Guest-UUID', $guestUuid);
    }
    app()->instance('request', $req);
    try {
        $route = app('router')->getRoutes()->match($req);
        $resp = $route->run();
    } catch (\Throwable $e) {
        $code = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;
        $body = method_exists($e, 'getMessage') ? ['error' => $e->getMessage()] : [];
        $resp = new \Illuminate\Http\JsonResponse($body, $code);
    }
    $content = $resp->getContent();
    return [
        'status' => $resp->getStatusCode(),
        'data' => is_string($content) ? (json_decode($content, true) ?? []) : [],
    ];
}

$failed = 0;

// ---- GUEST TESTS FIRST (no auth yet, so backend treats as guest) ----
echo "=== 1. Guest: create new chat ===\n";
$guestUuid = 'test-guest-' . \Illuminate\Support\Str::uuid()->toString();
$guestSessionId = \Illuminate\Support\Str::uuid()->toString();
$guestChat1 = dispatchRequest('POST', $base . '/chat', [
    'message' => 'Guest says hi',
    'session_id' => $guestSessionId,
], null, $guestUuid);
if ($guestChat1['status'] !== 200) {
    echo "FAIL: Guest POST /chat returned {$guestChat1['status']}: " . json_encode($guestChat1['data']) . "\n";
    $failed++;
} else {
    $gReturned = $guestChat1['data']['session_id'] ?? null;
    if ($gReturned !== $guestSessionId) {
        echo "FAIL: Guest expected session_id {$guestSessionId}, got {$gReturned}\n";
        $failed++;
    } else {
        echo "  OK: Guest session_id unchanged: {$guestSessionId}\n";
    }
    $guestSession = \App\Models\ChatSession::find($guestSessionId);
    if (!$guestSession) {
        echo "FAIL: Guest session not in DB\n";
        $failed++;
    } elseif ($guestSession->guest_session_id === null) {
        echo "FAIL: Guest session has guest_session_id=null (created as user?)\n";
        $failed++;
    } else {
        echo "  OK: Guest session in DB with guest_session_id={$guestSession->guest_session_id}\n";
    }
}

// ---- 6. Guest: GET /sessions must include guest session ----
echo "\n=== 2. Guest: GET /sessions must include new session ===\n";
$guestSessionsResp = dispatchRequest('GET', $base . '/chat/sessions', [], null, $guestUuid);
if ($guestSessionsResp['status'] !== 200) {
    echo "FAIL: Guest GET /sessions returned {$guestSessionsResp['status']}\n";
    $failed++;
} else {
    $gList = $guestSessionsResp['data'];
    if (!is_array($gList)) {
        echo "FAIL: guest sessions not array\n";
        $failed++;
    } else {
        $gFound = false;
        foreach ($gList as $s) {
            if (($s['id'] ?? '') === $guestSessionId) {
                $gFound = true;
                break;
            }
        }
        if (!$gFound) {
            echo "FAIL: Guest session {$guestSessionId} not in GET /sessions (count=" . count($gList) . ")\n";
            $failed++;
        } else {
            echo "  OK: Guest session found in GET /sessions\n";
        }
    }
}

// ---- 3. Guest: continue chat (second message) ----
echo "\n=== 3. Guest: continue chat (second message) ===\n";
$guestChat2 = dispatchRequest('POST', $base . '/chat', [
    'message' => 'Guest second message',
    'session_id' => $guestSessionId,
], null, $guestUuid);
if ($guestChat2['status'] !== 200) {
    echo "FAIL: Guest continue POST /chat returned {$guestChat2['status']}: " . json_encode($guestChat2['data']) . "\n";
    $failed++;
} else {
    $gReturned2 = $guestChat2['data']['session_id'] ?? null;
    if ($gReturned2 !== $guestSessionId) {
        echo "FAIL: Guest continue expected session_id {$guestSessionId}, got {$gReturned2}\n";
        $failed++;
    } else {
        echo "  OK: Guest session_id unchanged on continue\n";
    }
}
$guestMessagesResp = dispatchRequest('GET', $base . '/chat/messages/' . $guestSessionId, [], null, $guestUuid);
if ($guestMessagesResp['status'] !== 200 || !is_array($guestMessagesResp['data'])) {
    echo "FAIL: Guest GET /messages returned " . ($guestMessagesResp['status'] ?? 'non-200') . "\n";
    $failed++;
} else {
    $gCount = count($guestMessagesResp['data']);
    if ($gCount < 4) {
        echo "FAIL: Guest expected at least 4 messages, got {$gCount}\n";
        $failed++;
    } else {
        echo "  OK: Guest GET /messages returned {$gCount} messages\n";
    }
}

// ---- SIGNED-IN USER TESTS ----
echo "\n=== 4. Auth (snazrawi@gmail.com / test123$) ===\n";
$user = \App\Models\User::where('email', 'snazrawi@gmail.com')->first();
if (!$user) {
    echo "FAIL: User snazrawi@gmail.com not found in DB\n";
    exit(1);
}
if (!\Illuminate\Support\Facades\Hash::check('test123$', $user->password)) {
    echo "FAIL: Password test123\$ does not match for snazrawi@gmail.com\n";
    exit(1);
}
$token = auth('api')->login($user);
if (!$token) {
    echo "FAIL: auth('api')->login failed\n";
    exit(1);
}
echo "  OK: JWT obtained for user id={$user->id}\n";

echo "\n=== 5. Signed-in: create new chat ===\n";
$sessionId1 = \Illuminate\Support\Str::uuid()->toString();
$chat1 = dispatchRequest('POST', $base . '/chat', [
    'message' => 'Hello',
    'session_id' => $sessionId1,
], $token);
if ($chat1['status'] !== 200) {
    echo "FAIL: POST /chat returned {$chat1['status']}: " . json_encode($chat1['data']) . "\n";
    $failed++;
} else {
    $returnedId = $chat1['data']['session_id'] ?? null;
    if ($returnedId !== $sessionId1) {
        echo "FAIL: expected session_id {$sessionId1}, got {$returnedId}\n";
        $failed++;
    } else {
        echo "  OK: session_id unchanged: {$sessionId1}\n";
    }
    $session = \App\Models\ChatSession::find($sessionId1);
    if (!$session) {
        echo "FAIL: Session not in DB\n";
        $failed++;
    } elseif ($session->user_id === null) {
        echo "FAIL: Session has user_id=null (created as guest)\n";
        $failed++;
    } else {
        echo "  OK: Session in DB with user_id={$session->user_id}\n";
    }
}

echo "\n=== 6. Signed-in: GET /sessions must include new session ===\n";
$sessionsResp = dispatchRequest('GET', $base . '/chat/sessions', [], $token);
if ($sessionsResp['status'] !== 200) {
    echo "FAIL: GET /sessions returned {$sessionsResp['status']}\n";
    $failed++;
} else {
    $list = $sessionsResp['data'];
    if (!is_array($list)) {
        echo "FAIL: sessions not array\n";
        $failed++;
    } else {
        $found = false;
        foreach ($list as $s) {
            if (($s['id'] ?? '') === $sessionId1) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            echo "FAIL: New session {$sessionId1} not in GET /sessions list (count=" . count($list) . ")\n";
            $failed++;
        } else {
            echo "  OK: New session found in GET /sessions\n";
        }
    }
}

echo "\n=== 7. Signed-in: continue chat (second message) ===\n";
$chat2 = dispatchRequest('POST', $base . '/chat', [
    'message' => 'How are you?',
    'session_id' => $sessionId1,
], $token);
if ($chat2['status'] !== 200) {
    echo "FAIL: POST /chat (continue) returned {$chat2['status']}: " . json_encode($chat2['data']) . "\n";
    $failed++;
} else {
    $returnedId2 = $chat2['data']['session_id'] ?? null;
    if ($returnedId2 !== $sessionId1) {
        echo "FAIL: continue expected session_id {$sessionId1}, got {$returnedId2}\n";
        $failed++;
    } else {
        echo "  OK: session_id unchanged on continue: {$sessionId1}\n";
    }
}
$messagesResp = dispatchRequest('GET', $base . '/chat/messages/' . $sessionId1, [], $token);
if ($messagesResp['status'] !== 200 || !is_array($messagesResp['data'])) {
    echo "FAIL: GET /messages returned " . ($messagesResp['status'] ?? 'non-200') . "\n";
    $failed++;
} else {
    $count = count($messagesResp['data']);
    if ($count < 4) {
        echo "FAIL: Expected at least 4 messages (user, assistant, user, assistant), got {$count}\n";
        $failed++;
    } else {
        echo "  OK: GET /messages returned {$count} messages\n";
    }
}

// ---- Summary ----
echo "\n" . ($failed === 0 ? "=== All chat session tests passed ===" : "=== {$failed} test(s) failed ===") . "\n";
exit($failed > 0 ? 1 : 0);
