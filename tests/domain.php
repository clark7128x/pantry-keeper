<?php
declare(strict_types=1);
putenv('PANTRY_DB=:memory:');
require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/reminders.php';
$checks = 0;
function check(bool $condition, string $description): void {
    global $checks;
    if (!$condition) { throw new RuntimeException('FAIL: ' . $description); }
    $checks++;
}
$valid = ['name'=>'Молоко','category'=>'dairy','storage'=>'fridge','expires_on'=>'2028-02-29'];
check(validateProduct($valid) === [], 'valid leap date and Unicode name');
foreach (['2027-02-29','2028-02-30','2028-2-01','',"2028-02-29\0",'1999-12-31','2101-01-01'] as $date) {
    check(isset(validateProduct(array_replace($valid, ['expires_on'=>$date]))['expires_on']), 'reject invalid date');
}
foreach (['2000-01-01','2100-12-31'] as $date) { check(validateProduct(array_replace($valid, ['expires_on'=>$date])) === [], 'date limits inclusive'); }
check(validateProduct(array_replace($valid, ['name'=>str_repeat('Ї',100)])) === [], '100 Unicode characters');
foreach (['', '   ', str_repeat('Ї',101), ['array']] as $name) { check(isset(validateProduct(array_replace($valid, ['name'=>$name]))['name']), 'name boundary'); }
check(isset(validateProduct(array_replace($valid, ['storage'=>'unknown']))['storage']), 'storage allowlist');
check(isset(validateProduct(array_replace($valid, ['category'=>'unknown']))['category']), 'category allowlist');
$now = new DateTimeImmutable('2026-10-30 12:00:00');
foreach (['2026-10-29'=>'expired','2026-10-30'=>'soon','2026-11-01'=>'soon','2026-11-02'=>'fresh'] as $date=>$status) { check(expiryStatus($date,$now) === $status, 'expiry boundary'); }
check(!reminderDue('2026-10-31', new DateTimeImmutable('2026-10-30 08:59:59')), 'not before reminder time');
check(reminderDue('2026-10-31', new DateTimeImmutable('2026-10-30 09:00:00')), 'exact reminder time');
check(reminderDue('2026-10-31', new DateTimeImmutable('2026-10-31 23:59:59')), 'retry through expiry date');
check(!reminderDue('2026-10-31', new DateTimeImmutable('2026-11-01 00:00:00')), 'no expired reminders');
check(reminderDue('2026-03-30', new DateTimeImmutable('2026-03-29 09:00:00')), 'DST calendar day');
foreach (['http://fcm.googleapis.com/a','https://127.0.0.1/a','https://fcm.googleapis.com.evil.test/a','https://fcm.googleapis.com:8443/a','https://user@fcm.googleapis.com/a'] as $url) { check(!validPushEndpoint($url), 'reject unsafe push endpoint'); }
check(validPushEndpoint('https://fcm.googleapis.com/fcm/send/example'), 'FCM allowed');
check(validPushEndpoint('https://updates.push.services.mozilla.com/wpush/v2/example'), 'Firefox allowed');
check(!validPushKey('not-a-key',65), 'reject invalid key');
foreach ($messages as $locale=>$strings) { check(array_keys($strings) === array_keys($messages['uk']), 'translation key parity: ' . $locale); }
query('INSERT INTO users(email,password_hash,name,notifications) VALUES (?,?,?,1)', ['a@example.invalid','test-only','A']);
query('INSERT INTO products(user_id,name,category,storage,expires_on,request_token) VALUES (1,?,?,?,?,?)', ['Молоко','dairy','fridge','2026-10-31','one']);
query('INSERT INTO subscriptions(user_id,endpoint,public_key,auth_token,session_key) VALUES (1,?,?,?,?)', ['https://fcm.googleapis.com/test','fake','fake','test']);
$due = dueReminders($now);
check(count($due) === 1, 'one reminder due');
check(str_contains(reminderPayload($due[0],$now)['body'],'кашу'), 'milk reminder hint');
recordDelivery($due[0]);
recordDelivery($due[0]);
check(dueReminders($now) === [], 'successful delivery deduplicated');
query('DELETE FROM deliveries');
query("UPDATE products SET status='consumed'");
check(dueReminders($now) === [], 'consumed products excluded');
query("UPDATE products SET status='active'");
query('UPDATE users SET notifications=0');
check(dueReminders($now) === [], 'notification preference respected');
query('UPDATE users SET notifications=1');
check(count(dueReminders($now)) === 1, 'unsent reminder remains retryable');
query('DELETE FROM subscriptions');
check(dueReminders($now) === [], 'deleted subscriptions excluded');
echo "$checks domain checks passed using an in-memory database.\n";
