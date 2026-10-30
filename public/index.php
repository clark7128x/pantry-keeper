<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/actions.php';
startSession();
$language = $_SESSION['language'] ?? 'uk';
if (!in_array(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), ['/', '/index.php'], true)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo t('not_found');
    exit;
}
$errors = [];
$user = null;
$page = input($_GET, 'page');
$form = [];
$products = [];
$filter = input($_GET, 'storage');
$fatal = false;
try {
    $user = currentUser();
    $language = $user['language'] ?? $language;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = input($_POST, 'action');
        $errors = handleAction($action, $user);
        if ($errors && http_response_code() === 200) { http_response_code(422); }
        if (in_array($action, ['add', 'edit', 'register', 'login'], true)) { $page = $action; }
        $form = $_POST;
    }
    if ($user && $page === 'edit' && !$form) {
        $form = query('SELECT * FROM products WHERE id=? AND user_id=? AND status=\'active\'', [input($_GET, 'id'), $user['id']])->fetch() ?: [];
        if (!$form) { http_response_code(404); $errors['global'] = 'not_found'; $page = ''; }
    }
    if ($user) {
        $sql = 'SELECT * FROM products WHERE user_id=? AND status ' . ($page === 'history' ? '!= ' : '= ') . "'active'";
        $params = [$user['id']];
        if (in_array($filter, STORAGE, true)) { $sql .= ' AND storage=?'; $params[] = $filter; }
        $products = query($sql . ' ORDER BY expires_on ASC, id ASC', $params)->fetchAll();
    }
} catch (Throwable $exception) {
    try { if (db()->inTransaction()) { db()->rollBack(); } } catch (Throwable) { /* Database may be unavailable. */ }
    error_log('Pantry request failed: ' . $exception->getMessage());
    http_response_code(500);
    $errors['global'] = 'server_error';
    $form = $_POST;
    $fatal = true;
}
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$editing = $user && in_array($page, ['add', 'edit'], true);
$registering = !$user && $page === 'register';
$pushReady = $config['vapid']['publicKey'] !== '' && is_file(dirname(__DIR__) . '/vendor/autoload.php');
$now = new DateTimeImmutable();
function fieldError(string $field): string {
    global $errors;
    return isset($errors[$field]) ? '<span class="field-error" id="error-' . e($field) . '">' . e(t($errors[$field])) . '</span>' : '';
}
function invalidAttributes(string $field): string {
    global $errors;
    return isset($errors[$field]) ? ' aria-invalid="true" aria-describedby="error-' . e($field) . '"' : '';
}
?>
<!doctype html>
<html lang="<?= e($language) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#215c43">
    <meta name="csrf-token" content="<?= e($_SESSION['csrf']) ?>">
    <title><?= e(t('app')) ?></title>
    <link rel="stylesheet" href="/assets/app.css">
    <script src="/assets/app.js" defer></script>
</head>
<body>
<a class="skip" href="#main"><?= e(t('skip')) ?></a>
<header class="site-header">
    <a href="/" class="brand"><span class="brand-mark" aria-hidden="true">▤</span><?= e(t('app')) ?></a>
    <div class="header-actions">
        <form method="post" class="language-form">
            <?= csrfField() ?><input type="hidden" name="action" value="language">
            <label class="sr-only" for="language"><?= e(t('language')) ?></label>
            <select name="language" id="language">
                <?php foreach (['uk'=>'Українська','en'=>'English','de'=>'Deutsch'] as $code=>$label): ?>
                <option value="<?= $code ?>" <?= $language === $code ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach ?>
            </select>
            <button class="quiet" type="submit"><?= e(t('apply')) ?></button>
        </form>
        <?php if ($user): ?><form method="post"><?= csrfField() ?><button class="quiet" name="action" value="logout"><?= e(t('logout')) ?></button></form><?php endif ?>
    </div>
</header>
<main id="main" class="<?= !$user ? 'auth-layout' : '' ?>">
<?php if ($flash): ?><p class="notice" role="status"><?= e(t($flash)) ?></p><?php endif ?>
<?php if ($errors): ?><div class="error-summary" role="alert" tabindex="-1"><p><?= e(t($errors['global'] ?? 'errors')) ?></p><?php foreach ($errors as $field=>$error): if ($field === 'global') { continue; } ?><a href="#<?= e($field) ?>"><?= e(t($error)) ?></a><?php endforeach ?></div><?php endif ?>
<?php if (!$user): ?>
    <section class="auth-intro"><span class="shelf-art" aria-hidden="true">▤</span><h1><?= e(t('tagline')) ?></h1><p><?= e(t('intro')) ?></p></section>
    <section class="auth-form"><h2><?= e(t($registering ? 'register' : 'login')) ?></h2>
        <form method="post" class="stack">
            <?= csrfField() ?><input type="hidden" name="action" value="<?= $registering ? 'register' : 'login' ?>">
            <?php if ($registering): ?><div><label for="name"><?= e(t('your_name')) ?></label><input id="name" name="name" autocomplete="name" maxlength="80" required value="<?= e(input($form,'name')) ?>"<?= invalidAttributes('name') ?>><?= fieldError('name') ?></div><?php endif ?>
            <div><label for="email"><?= e(t('email')) ?></label><input id="email" name="email" type="email" inputmode="email" spellcheck="false" autocomplete="email" maxlength="254" required value="<?= e(input($form,'email')) ?>"<?= invalidAttributes('email') ?>><?= fieldError('email') ?></div>
            <div><label for="password"><?= e(t('password')) ?></label><input id="password" name="password" type="password" autocomplete="<?= $registering ? 'new-password' : 'current-password' ?>" required <?= $registering ? 'minlength="12"' : '' ?><?= invalidAttributes('password') ?>><?php if ($registering): ?><small><?= e(t('password_help')) ?></small><?php endif ?><?= fieldError('password') ?></div>
            <button class="primary"><?= e(t($registering ? 'register' : 'login')) ?></button>
        </form>
        <a class="auth-switch" href="/?page=<?= $registering ? 'login' : 'register' ?>"><?= e(t($registering ? 'login' : 'register')) ?></a>
    </section>
<?php elseif ($editing): ?>
    <section class="editor"><a href="/" class="back"><?= e(t('back')) ?></a><h1><?= e(t($page === 'edit' ? 'edit' : 'add')) ?></h1>
        <form method="post" class="stack" autocomplete="off">
            <?= csrfField() ?><input type="hidden" name="action" value="<?= e($page) ?>">
            <input type="hidden" name="id" value="<?= e(is_scalar($form['id'] ?? null) ? $form['id'] : '') ?>">
            <input type="hidden" name="request_token" value="<?= e(input($form, 'request_token') ?: bin2hex(random_bytes(16))) ?>">
            <div><label for="name"><?= e(t('name')) ?></label><input id="name" name="name" maxlength="100" required value="<?= e(input($form,'name')) ?>"<?= invalidAttributes('name') ?>><?= fieldError('name') ?></div>
            <div class="form-pair">
                <?php foreach (['category'=>CATEGORIES,'storage'=>STORAGE] as $field=>$choices): ?><div><label for="<?= $field ?>"><?= e(t($field)) ?></label><select id="<?= $field ?>" name="<?= $field ?>" required<?= invalidAttributes($field) ?>><?php foreach ($choices as $choice): ?><option value="<?= $choice ?>" <?= input($form,$field) === $choice ? 'selected' : '' ?>><?= e(t($choice)) ?></option><?php endforeach ?></select><?= fieldError($field) ?></div><?php endforeach ?>
            </div>
            <div><label for="expires_on"><?= e(t('expires_on')) ?></label><input type="date" id="expires_on" name="expires_on" min="2000-01-01" max="2100-12-31" required value="<?= e(input($form,'expires_on')) ?>"<?= invalidAttributes('expires_on') ?>><small><?= e(t('date_help')) ?></small><?= fieldError('expires_on') ?></div>
            <div class="form-actions"><button class="primary"><?= e(t($page === 'edit' ? 'save' : 'add')) ?></button><a href="/"><?= e(t('cancel')) ?></a></div>
        </form>
    </section>
<?php else: ?>
    <div class="page-heading"><div><h1><?= e(t('inventory')) ?></h1><p><?= e(t('inventory_help')) ?></p></div><a class="button primary" href="/?page=add"><span aria-hidden="true">+</span> <?= e(t('add')) ?></a></div>
    <div class="inventory-layout"><section aria-label="<?= e(t('inventory')) ?>">
        <div class="list-toolbar"><nav class="tabs" aria-label="<?= e(t('inventory')) ?>"><a href="/" <?= $page !== 'history' ? 'aria-current="page"' : '' ?>><?= e(t('active')) ?></a><a href="/?page=history" <?= $page === 'history' ? 'aria-current="page"' : '' ?>><?= e(t('history')) ?></a></nav>
        <form method="get" class="filter"><input type="hidden" name="page" value="<?= $page === 'history' ? 'history' : '' ?>"><label class="sr-only" for="storage-filter"><?= e(t('storage')) ?></label><select id="storage-filter" name="storage"><option value=""><?= e(t('all_storage')) ?></option><?php foreach(STORAGE as $location): ?><option value="<?= $location ?>" <?= $filter === $location ? 'selected' : '' ?>><?= e(t($location)) ?></option><?php endforeach ?></select><button class="quiet"><?= e(t('filter')) ?></button></form></div>
        <?php if (!$products && !$fatal): ?><div class="empty"><h2><?= e(t($page === 'history' ? 'empty_history' : 'empty')) ?></h2><?php if ($page !== 'history'): ?><p><?= e(t('empty_help')) ?></p><a href="/?page=add"><?= e(t('add')) ?></a><?php endif ?></div><?php endif ?>
        <ul class="food-list">
        <?php foreach ($products as $product): $status = expiryStatus($product['expires_on'], $now); ?>
            <li class="food-row"><div class="food-info"><span class="status <?= $status ?>"><?= e(t($status)) ?></span><h2><?= e($product['name']) ?></h2><p><?= e(t($product['category'])) ?> <span aria-hidden="true">/</span> <?= e(t($product['storage'])) ?></p></div>
            <div class="food-date"><span><?= e(t('expires_on')) ?></span><time datetime="<?= e($product['expires_on']) ?>"><?= e((new DateTimeImmutable($product['expires_on']))->format($language === 'en' ? 'Y-m-d' : 'd.m.Y')) ?></time><?php if ($product['expires_on'] === $now->format('Y-m-d')): ?><strong><?= e(t('today')) ?></strong><?php elseif($product['expires_on'] === $now->modify('+1 day')->format('Y-m-d')): ?><strong><?= e(t('tomorrow')) ?></strong><?php endif ?></div>
            <?php if ($product['status'] === 'active'): ?><div class="food-actions"><a href="/?page=edit&amp;id=<?= $product['id'] ?>"><?= e(t('edit')) ?></a><form method="post"><?= csrfField() ?><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= $product['id'] ?>"><button name="status" value="consumed" class="quiet" aria-label="<?= e(t('mark_consumed') . ': ' . $product['name']) ?>"><?= e(t('consumed')) ?></button><button name="status" value="discarded" class="quiet danger" aria-label="<?= e(t('mark_discarded') . ': ' . $product['name']) ?>"><?= e(t('discarded')) ?></button></form></div><?php else: ?><div class="food-actions"><span class="history-status"><?= e(t($product['status'])) ?></span><form method="post"><?= csrfField() ?><input type="hidden" name="id" value="<?= $product['id'] ?>"><button class="quiet" name="action" value="restore"><?= e(t('restore')) ?></button></form></div><?php endif ?>
            </li>
        <?php endforeach ?>
        </ul><p class="safety-note"><?= e(t('safety')) ?></p>
    </section>
    <aside class="reminders"><h2><?= e(t('notifications')) ?></h2><p><?= e(t('notification_help')) ?></p><small><?= e($config['timezone']) ?></small>
        <?php if ($pushReady): ?><button type="button" class="secondary" id="enable-push" data-key="<?= e($config['vapid']['publicKey']) ?>" data-success="<?= e(t('push_enabled')) ?>" data-denied="<?= e(t('push_denied')) ?>" data-unsupported="<?= e(t('push_unsupported')) ?>" data-error="<?= e(t('push_error')) ?>"><?= e(t('enable_push')) ?></button><?php else: ?><p class="muted"><?= e(t('push_unconfigured')) ?></p><?php endif ?>
        <?php if ($user['notifications']): ?><form method="post"><?= csrfField() ?><button class="quiet" name="action" value="disable_push"><?= e(t('disable_push')) ?></button></form><?php endif ?>
        <p id="push-message" role="status" aria-live="polite"></p><noscript><p><?= e(t('no_js')) ?></p></noscript>
    </aside></div>
<?php endif ?>
</main>
</body>
</html>
