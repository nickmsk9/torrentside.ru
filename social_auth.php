<?php
declare(strict_types=1);

require_once 'include/bittorrent.php';
dbconn();

$provider = strtolower(trim((string)($_GET['provider'] ?? $_POST['provider'] ?? '')));
$action = strtolower(trim((string)($_GET['action'] ?? $_POST['action'] ?? 'start')));
$returnto = social_auth_safe_returnto((string)($_GET['returnto'] ?? $_POST['returnto'] ?? ''));

if (!in_array($provider, ['apple', 'telegram'], true)) {
    social_auth_fail('Социальный вход', 'Неизвестный провайдер авторизации.');
}

if (!social_auth_provider_enabled($provider)) {
    social_auth_fail('Социальный вход', 'Этот способ входа сейчас отключён.');
}

social_auth_require_table();

if ($provider === 'apple') {
    if ($action === 'start') {
        $session = social_auth_begin('apple', $returnto);
        $params = [
            'response_type' => 'code id_token',
            'response_mode' => 'form_post',
            'scope' => (string)(social_auth_config()['apple']['scope'] ?? 'name email'),
            'client_id' => (string)(social_auth_config()['apple']['client_id'] ?? ''),
            'redirect_uri' => social_auth_callback_url('apple'),
            'state' => (string)$session['state'],
            'nonce' => (string)$session['nonce'],
        ];

        header('Location: https://appleid.apple.com/auth/authorize?' . http_build_query($params));
        exit;
    }

    if ($action !== 'callback') {
        social_auth_fail('Apple ID', 'Неподдерживаемое действие для Apple ID.');
    }

    if (!empty($_POST['error'])) {
        $description = trim((string)($_POST['error_description'] ?? $_POST['error']));
        social_auth_fail('Apple ID', 'Apple вернул ошибку: ' . social_auth_h($description));
    }

    $state = trim((string)($_POST['state'] ?? ''));
    $code = trim((string)($_POST['code'] ?? ''));
    if ($state === '' || $code === '') {
        social_auth_fail('Apple ID', 'Apple не передал обязательные параметры авторизации.');
    }

    $session = social_auth_pull_session('apple', $state);
    $claims = social_auth_apple_exchange_code($code, $session);

    $userPayload = json_decode((string)($_POST['user'] ?? ''), true);
    $userPayload = is_array($userPayload) ? $userPayload : [];
    $namePayload = is_array($userPayload['name'] ?? null) ? $userPayload['name'] : [];
    $displayName = trim(
        implode(
            ' ',
            array_filter([
                trim((string)($namePayload['firstName'] ?? '')),
                trim((string)($namePayload['lastName'] ?? '')),
            ], static fn(string $value): bool => $value !== '')
        )
    );

    $identity = [
        'provider' => 'apple',
        'provider_user_id' => trim((string)($claims['sub'] ?? '')),
        'provider_username' => '',
        'display_name' => $displayName !== '' ? $displayName : trim((string)($claims['email'] ?? '')),
        'email' => trim((string)($claims['email'] ?? '')),
        'avatar_url' => '',
        'raw' => [
            'claims' => $claims,
            'user' => $userPayload,
        ],
    ];

    social_auth_finish($identity, (string)($session['returnto'] ?? ''));
}

if ($provider === 'telegram') {
    if ($action !== 'callback') {
        social_auth_fail('Telegram', 'Для Telegram вход запускается только с формы входа.');
    }

    $state = trim((string)($_GET['state'] ?? ''));
    if ($state === '') {
        social_auth_fail('Telegram', 'Не найден state для Telegram-авторизации.');
    }

    $session = social_auth_pull_session('telegram', $state);
    $input = [
        'id' => (string)($_GET['id'] ?? ''),
        'first_name' => (string)($_GET['first_name'] ?? ''),
        'last_name' => (string)($_GET['last_name'] ?? ''),
        'username' => (string)($_GET['username'] ?? ''),
        'photo_url' => (string)($_GET['photo_url'] ?? ''),
        'auth_date' => (string)($_GET['auth_date'] ?? ''),
        'hash' => (string)($_GET['hash'] ?? ''),
    ];

    $config = social_auth_config();
    if (!social_auth_verify_telegram_payload($input, (string)$config['telegram']['bot_token'], (int)$config['telegram']['auth_ttl'])) {
        social_auth_fail('Telegram', 'Не удалось проверить подпись Telegram Login Widget.');
    }

    $displayName = trim(
        implode(
            ' ',
            array_filter([
                trim((string)$input['first_name']),
                trim((string)$input['last_name']),
            ], static fn(string $value): bool => $value !== '')
        )
    );

    $identity = [
        'provider' => 'telegram',
        'provider_user_id' => trim((string)$input['id']),
        'provider_username' => trim((string)$input['username']),
        'display_name' => $displayName !== '' ? $displayName : trim((string)$input['username']),
        'email' => '',
        'avatar_url' => trim((string)$input['photo_url']),
        'raw' => $input,
    ];

    social_auth_finish($identity, (string)($session['returnto'] ?? ''));
}

social_auth_fail('Социальный вход', 'Неподдерживаемая комбинация provider/action.');
