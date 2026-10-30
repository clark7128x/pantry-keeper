<?php
declare(strict_types=1);

const CATEGORIES = ['dairy', 'vegetables', 'fruit', 'meat', 'fish', 'grains', 'other'];
const STORAGE = ['fridge', 'freezer', 'cupboard'];
const LANGUAGES = ['uk', 'en', 'de'];

function input(array $data, string $key): string
{
    return isset($data[$key]) && is_string($data[$key]) ? trim($data[$key]) : '';
}

function validateProduct(array $data): array
{
    $errors = [];
    $name = input($data, 'name');
    if ($name === '' || mb_strlen($name) > 100) { $errors['name'] = 'invalid_product_name'; }
    if (!in_array(input($data, 'category'), CATEGORIES, true)) { $errors['category'] = 'invalid_choice'; }
    if (!in_array(input($data, 'storage'), STORAGE, true)) { $errors['storage'] = 'invalid_choice'; }
    $date = input($data, 'expires_on');
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', str_replace("\0", '', $date));
    if (!$parsed || str_contains(is_string($data['expires_on'] ?? null) ? $data['expires_on'] : '', "\0") || $parsed->format('Y-m-d') !== $date || $date < '2000-01-01' || $date > '2100-12-31') { $errors['expires_on'] = 'invalid_date'; }
    return $errors;
}

function expiryStatus(string $date, DateTimeImmutable $now): string
{
    $today = $now->setTime(0, 0);
    $expiry = new DateTimeImmutable($date, $now->getTimezone());
    return $expiry < $today ? 'expired' : ($expiry <= $today->modify('+2 days') ? 'soon' : 'fresh');
}

function reminderDue(string $date, DateTimeImmutable $now): bool
{
    $expiry = new DateTimeImmutable($date . ' 23:59:59', $now->getTimezone());
    return $now >= $expiry->modify('-1 day')->setTime(9, 0) && $now <= $expiry;
}

function validPushEndpoint(string $endpoint): bool
{
    $parts = parse_url($endpoint);
    if (!$parts || ($parts['scheme'] ?? '') !== 'https' || isset($parts['user']) || isset($parts['pass']) || isset($parts['port']) || isset($parts['fragment'])) { return false; }
    // Restrict destinations to browser push services, not arbitrary user-supplied hosts.
    $host = strtolower($parts['host'] ?? '');
    return $host === 'fcm.googleapis.com' || $host === 'updates.push.services.mozilla.com'
        || $host === 'web.push.apple.com' || str_ends_with($host, '.notify.windows.com');
}

function validPushKey(string $value, int $bytes): bool
{
    if (!preg_match('/^[A-Za-z0-9_-]+$/D', $value)) { return false; }
    $decoded = base64_decode(strtr($value, '-_', '+/'), true);
    return $decoded !== false && strlen($decoded) === $bytes;
}
