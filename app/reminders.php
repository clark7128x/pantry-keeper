<?php
declare(strict_types=1);

function dueReminders(DateTimeImmutable $now): array
{
    $rows = query("SELECT p.id AS product_id,p.name,p.category,p.expires_on,s.id AS subscription_id,s.endpoint,s.public_key,s.auth_token,u.language
        FROM products p JOIN users u ON u.id=p.user_id JOIN subscriptions s ON s.user_id=u.id
        WHERE p.status='active' AND u.notifications=1 AND p.expires_on BETWEEN ? AND ?
        AND NOT EXISTS(SELECT 1 FROM deliveries d WHERE d.product_id=p.id AND d.subscription_id=s.id AND d.expires_on=p.expires_on)
        ORDER BY p.expires_on,p.id,s.id", [$now->format('Y-m-d'),$now->modify('+1 day')->format('Y-m-d')])->fetchAll();
    return array_values(array_filter($rows, fn(array $row): bool => reminderDue($row['expires_on'], $now)));
}

function reminderPayload(array $row, DateTimeImmutable $now): array
{
    $key = $row['expires_on'] === $now->format('Y-m-d') ? 'push_today' : 'push_tomorrow';
    $body = t($key, ['{name}'=>$row['name']], $row['language']);
    if (in_array(mb_strtolower($row['name']), ['молоко', 'milk', 'milch'], true)) {
        $body .= t('milk_hint', [], $row['language']);
    }
    return ['title'=>t('push_title', [], $row['language']), 'body'=>$body, 'tag'=>'expiry-' . $row['product_id'] . '-' . $row['expires_on']];
}

function recordDelivery(array $row): void
{
    query('INSERT INTO deliveries(product_id,subscription_id,expires_on) VALUES (?,?,?) ON CONFLICT DO NOTHING', [$row['product_id'],$row['subscription_id'],$row['expires_on']]);
}
