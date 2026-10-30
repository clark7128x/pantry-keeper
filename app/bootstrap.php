<?php
declare(strict_types=1);

$config = require dirname(__DIR__) . '/config.example.php';
if (is_file(dirname(__DIR__) . '/config.local.php')) {
    $config = array_replace_recursive($config, require dirname(__DIR__) . '/config.local.php');
}
if (getenv('PANTRY_DB')) {
    $config['database'] = getenv('PANTRY_DB');
}
date_default_timezone_set($config['timezone']);
require __DIR__ . '/domain.php';
require __DIR__ . '/translations.php';

function db(): PDO
{
    global $config;
    static $db;
    if ($db instanceof PDO) { return $db; }
    $directory = dirname($config['database']);
    if (!is_dir($directory)) { mkdir($directory, 0700, true); }
    $db = new PDO('sqlite:' . $config['database'], null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $db->exec('PRAGMA foreign_keys = ON; PRAGMA busy_timeout = 5000; PRAGMA journal_mode = WAL;');
    $db->exec(file_get_contents(__DIR__ . '/schema.sql'));
    return $db;
}

function query(string $sql, array $parameters = []): PDOStatement
{
    $statement = db()->prepare($sql);
    $statement->execute($parameters);
    return $statement;
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function t(string $key, array $values = [], ?string $locale = null): string
{
    global $messages, $language;
    return strtr($messages[$locale ?? $language ?? 'uk'][$key] ?? $key, $values);
}

function redirect(string $url = '/'): never
{
    header('Location: ' . $url, true, 303);
    exit;
}

function currentUser(): ?array
{
    return isset($_SESSION['user_id']) ? (query('SELECT * FROM users WHERE id = ?', [$_SESSION['user_id']])->fetch() ?: null) : null;
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf" value="' . e($_SESSION['csrf']) . '">';
}

function startSession(): void
{
    global $config;
    ini_set('session.use_strict_mode', '1');
    session_name('pantry_session');
    session_set_cookie_params(['httponly' => true, 'secure' => $config['secure_cookies'], 'samesite' => 'Lax', 'path' => '/']);
    session_start();
    $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
    header('Cache-Control: no-store');
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self'; connect-src 'self'; worker-src 'self'; frame-ancestors 'none'; base-uri 'none'; form-action 'self'");
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
}
