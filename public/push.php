<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
startSession();
header('Content-Type: application/json; charset=utf-8');
$language = $_SESSION['language'] ?? 'uk';
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); header('Allow: POST'); exit; }
    if (!hash_equals($_SESSION['csrf'], $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) { http_response_code(403); echo json_encode(['error'=>t('csrf_error')]); exit; }
    $user = currentUser();
    if (!$user) { http_response_code(401); echo json_encode(['error'=>t('credentials')]); exit; }
    if (!$config['vapid']['publicKey']) { http_response_code(503); echo json_encode(['error'=>t('push_unconfigured')]); exit; }
    $body = file_get_contents('php://input', false, null, 0, 8193);
    $data = strlen($body) <= 8192 ? json_decode($body, true) : null;
    $endpoint = is_array($data) ? input($data, 'endpoint') : '';
    $keys = is_array($data['keys'] ?? null) ? $data['keys'] : [];
    if (strlen($endpoint) > 2048 || !validPushEndpoint($endpoint) || !validPushKey(input($keys, 'p256dh'), 65) || !validPushKey(input($keys, 'auth'), 16)) {
        http_response_code(422); echo json_encode(['error'=>t('push_error')]); exit;
    }
    db()->beginTransaction();
    $existing = query('SELECT * FROM subscriptions WHERE endpoint=?', [$endpoint])->fetch();
    if ($existing && (int)$existing['user_id'] !== (int)$user['id']) {
        query('DELETE FROM subscriptions WHERE id=?', [$existing['id']]);
    }
    query('INSERT INTO subscriptions(user_id,endpoint,public_key,auth_token,session_key) VALUES (?,?,?,?,?) ON CONFLICT(endpoint) DO UPDATE SET public_key=excluded.public_key,auth_token=excluded.auth_token,session_key=excluded.session_key', [$user['id'],$endpoint,input($keys,'p256dh'),input($keys,'auth'),hash('sha256',session_id())]);
    query('UPDATE users SET notifications=1 WHERE id=?', [$user['id']]);
    db()->commit();
    $_SESSION['flash'] = 'push_enabled';
    echo json_encode(['ok'=>true]);
} catch (Throwable $exception) {
    try { if (db()->inTransaction()) { db()->rollBack(); } } catch (Throwable) {}
    error_log('Push subscription failed: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['error'=>t('server_error')]);
}
