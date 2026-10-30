<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { exit(1); }
require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/vendor/autoload.php';
$subject = $argv[1] ?? '';
if (!str_starts_with($subject, 'mailto:') || !filter_var(substr($subject, 7), FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Usage: php bin/setup-push.php mailto:your-real-contact@example.com\n"); exit(1);
}
if ($config['vapid']['privateKey']) { fwrite(STDERR, "Keys already configured; leaving existing subscriptions intact.\n"); exit(1); }
$config['vapid'] = ['subject'=>$subject] + Minishlink\WebPush\VAPID::createVapidKeys();
$path = dirname(__DIR__) . '/config.local.php';
if (file_put_contents($path, "<?php\nreturn " . var_export($config, true) . ";\n", LOCK_EX) === false) { exit(1); }
chmod($path, 0600);
echo "VAPID configuration saved privately in config.local.php.\n";
