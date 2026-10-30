<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { exit(1); }
require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/reminders.php';

$dryRun = in_array('--dry-run', $argv, true);
try {
    db();
    $lock = fopen(dirname($config['database']) . '/reminders.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) { fwrite(STDERR, "Another reminder worker is running.\n"); exit(1); }
    $now = new DateTimeImmutable();
    $rows = dueReminders($now);
    if ($dryRun) { echo count($rows) . " pending reminder(s); dry run, nothing sent.\n"; exit; }
    if (!$config['vapid']['publicKey'] || !$config['vapid']['privateKey'] || !is_file(dirname(__DIR__) . '/vendor/autoload.php')) {
        throw new RuntimeException('Install Composer dependencies and run bin/setup-push.php first.');
    }
    require dirname(__DIR__) . '/vendor/autoload.php';
    $push = new Minishlink\WebPush\WebPush(['VAPID'=>$config['vapid']], ['TTL'=>3600], 15, ['allow_redirects'=>false]);
    $sent = 0;
    $failed = 0;
    foreach ($rows as $row) {
        // Recheck opt-out and product status immediately before each external send.
        $eligible = query("SELECT 1 FROM products p JOIN users u ON u.id=p.user_id JOIN subscriptions s ON s.user_id=u.id WHERE p.id=? AND s.id=? AND p.expires_on=? AND p.status='active' AND u.notifications=1", [$row['product_id'],$row['subscription_id'],$row['expires_on']])->fetchColumn();
        if (!$eligible || !validPushEndpoint($row['endpoint'])) { continue; }
        $subscription = Minishlink\WebPush\Subscription::create(['endpoint'=>$row['endpoint'],'publicKey'=>$row['public_key'],'authToken'=>$row['auth_token'],'contentEncoding'=>'aes128gcm']);
        try {
            $report = $push->sendOneNotification($subscription, json_encode(reminderPayload($row, $now), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
            if ($report->isSuccess()) {
                // A concurrent opt-out may have removed the subscription while transport was in flight.
                if (query('SELECT 1 FROM subscriptions WHERE id=?', [$row['subscription_id']])->fetchColumn()) { recordDelivery($row); }
                $sent++;
            } elseif ($report->isSubscriptionExpired()) {
                query('DELETE FROM subscriptions WHERE id=?', [$row['subscription_id']]);
            } else { $failed++; }
        } catch (Throwable) { $failed++; }
    }
    echo "Accepted by push service: $sent; failed/retryable: $failed.\n";
    exit($failed ? 1 : 0);
} catch (Throwable $exception) {
    fwrite(STDERR, 'Reminder worker: ' . $exception->getMessage() . "\n");
    exit(1);
}
