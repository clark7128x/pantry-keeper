<?php
declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\VAPID;
use Minishlink\WebPush\WebPush;

$vapid = VAPID::createVapidKeys();
$clientKeys = VAPID::createVapidKeys();
$subscription = Subscription::create([
    'endpoint'=>'https://fcm.googleapis.com/fcm/send/test-only',
    'publicKey'=>$clientKeys['publicKey'],
    'authToken'=>rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '='),
    'contentEncoding'=>'aes128gcm',
]);
$history = [];
$handler = HandlerStack::create(new MockHandler([new Response(201), new Response(410), new Response(503)]));
$handler->push(Middleware::history($history));
$push = new WebPush(['VAPID'=>['subject'=>'mailto:test@example.invalid'] + $vapid], ['TTL'=>3600], 5, ['handler'=>$handler, 'allow_redirects'=>false]);
$payload = json_encode(['title'=>'Test','body'=>'Private food reminder']);
if (!$push->sendOneNotification($subscription, $payload)->isSuccess()) { throw new RuntimeException('Success response not recognized'); }
if (!$push->sendOneNotification($subscription, $payload)->isSubscriptionExpired()) { throw new RuntimeException('Expired subscription not recognized'); }
if ($push->sendOneNotification($subscription, $payload)->isSuccess()) { throw new RuntimeException('Transport failure treated as success'); }
$request = $history[0]['request'];
if ($request->getHeaderLine('Content-Encoding') !== 'aes128gcm' || !$request->getHeaderLine('Authorization')) { throw new RuntimeException('Missing encryption/VAPID headers'); }
if (str_contains((string)$request->getBody(), 'Private food reminder')) { throw new RuntimeException('Unencrypted payload'); }
echo "5 Web Push checks passed with mocked HTTP transport; no external messages sent.\n";
