<?php
declare(strict_types=1);

function handleAction(string $action, ?array $user): array
{
    global $language;
    if (!hash_equals($_SESSION['csrf'], input($_POST, 'csrf'))) {
        http_response_code(403);
        return ['global' => 'csrf_error'];
    }
    if ($action === 'language') {
        $language = input($_POST, 'language');
        if (!in_array($language, LANGUAGES, true)) { $language = $_SESSION['language'] ?? 'uk'; return ['global' => 'invalid_choice']; }
        $_SESSION['language'] = $language;
        if ($user) { query('UPDATE users SET language = ? WHERE id = ?', [$language, $user['id']]); }
        redirect();
    }
    if (in_array($action, ['login', 'register'], true) && !$user) {
        $email = strtolower(input($_POST, 'email'));
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        $bucket = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . ':' . $email);
        query('DELETE FROM auth_attempts WHERE started_at < ?', [time() - 900]);
        query('INSERT INTO auth_attempts(bucket, attempts, started_at) VALUES (?, 1, ?) ON CONFLICT(bucket) DO UPDATE SET attempts = attempts + 1', [$bucket, time()]);
        if (query('SELECT attempts FROM auth_attempts WHERE bucket = ?', [$bucket])->fetchColumn() > 10) {
            http_response_code(429);
            return ['global' => 'rate_limit'];
        }
        $errors = [];
        if (strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors['email'] = 'invalid_email'; }
        if ($action === 'register') {
            $name = input($_POST, 'name');
            if ($name === '' || mb_strlen($name) > 80) { $errors['name'] = 'invalid_name'; }
            if (mb_strlen($password) < 12 || strlen($password) > 72 || str_contains($password, "\0")) { $errors['password'] = 'invalid_password'; }
            if ($errors) { return $errors; }
            try {
                query('INSERT INTO users(email,password_hash,name,language) VALUES (?,?,?,?)', [$email, password_hash($password, PASSWORD_DEFAULT), $name, $language]);
            } catch (PDOException $exception) {
                if ($exception->getCode() === '23000') { return ['email' => 'email_used']; }
                throw $exception;
            }
            $id = (int) db()->lastInsertId();
        } else {
            if ($errors) { return $errors; }
            $account = query('SELECT * FROM users WHERE email = ?', [$email])->fetch();
            $hash = $account['password_hash'] ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
            $valid = strlen($password) <= 72 && !str_contains($password, "\0") && password_verify($password, $hash);
            if (!$valid || !$account) { return ['global' => 'credentials']; }
            $id = $account['id'];
            $language = $account['language'];
        }
        query('DELETE FROM auth_attempts WHERE bucket = ?', [$bucket]);
        session_regenerate_id(true);
        $_SESSION = ['user_id' => $id, 'language' => $language, 'csrf' => bin2hex(random_bytes(32))];
        redirect();
    }
    if (!$user) { http_response_code(401); return ['global' => 'credentials']; }
    if ($action === 'logout') {
        query('DELETE FROM subscriptions WHERE user_id = ? AND session_key = ?', [$user['id'], hash('sha256', session_id())]);
        $_SESSION = ['language' => $language, 'csrf' => bin2hex(random_bytes(32))];
        session_regenerate_id(true);
        redirect();
    }
    if ($action === 'disable_push') {
        db()->beginTransaction();
        query('UPDATE users SET notifications = 0 WHERE id = ?', [$user['id']]);
        query('DELETE FROM subscriptions WHERE user_id = ?', [$user['id']]);
        db()->commit();
        $_SESSION['flash'] = 'push_disabled';
        redirect();
    }
    if (in_array($action, ['add', 'edit'], true)) {
        $errors = validateProduct($_POST);
        if ($errors) { return $errors; }
        $values = [input($_POST, 'name'), input($_POST, 'category'), input($_POST, 'storage'), input($_POST, 'expires_on')];
        if ($action === 'add') {
            $token = input($_POST, 'request_token');
            if (!preg_match('/^[a-f0-9]{32}$/D', $token)) { return ['global' => 'csrf_error']; }
            query('INSERT INTO products(name,category,storage,expires_on,user_id,request_token) VALUES (?,?,?,?,?,?) ON CONFLICT(user_id,request_token) DO NOTHING', [...$values, $user['id'], $token]);
        } else {
            $changed = query('UPDATE products SET name=?,category=?,storage=?,expires_on=? WHERE id=? AND user_id=? AND status=\'active\'', [...$values, input($_POST, 'id'), $user['id']]);
            if (!$changed->rowCount()) { http_response_code(404); return ['global' => 'not_found']; }
        }
        $_SESSION['flash'] = 'saved';
        redirect();
    }
    if ($action === 'restore') {
        $changed = query("UPDATE products SET status='active' WHERE id=? AND user_id=? AND status!='active'", [input($_POST, 'id'), $user['id']]);
        if (!$changed->rowCount()) { http_response_code(404); return ['global' => 'not_found']; }
        $_SESSION['flash'] = 'updated';
        redirect('/?page=history');
    }
    if ($action === 'status') {
        $status = input($_POST, 'status');
        if (!in_array($status, ['consumed', 'discarded'], true)) { return ['global' => 'invalid_choice']; }
        $changed = query('UPDATE products SET status=? WHERE id=? AND user_id=? AND status=\'active\'', [$status, input($_POST, 'id'), $user['id']]);
        if (!$changed->rowCount()) { http_response_code(404); return ['global' => 'not_found']; }
        $_SESSION['flash'] = 'updated';
        redirect();
    }
    http_response_code(400);
    return ['global' => 'invalid_choice'];
}
