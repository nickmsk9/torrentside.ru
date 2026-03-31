<?php
declare(strict_types=1);

if (!defined('IN_TRACKER')) {
    die('Hacking attempt!');
}

if (!function_exists('social_auth_h')) {
    function social_auth_h(?string $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('social_auth_env_bool')) {
    function social_auth_env_bool(string $name, bool $default = false): bool
    {
        $value = getenv($name);
        if ($value === false || $value === '') {
            return $default;
        }

        $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        return $parsed ?? $default;
    }
}

if (!function_exists('social_auth_env_int')) {
    function social_auth_env_int(string $name, int $default): int
    {
        $value = getenv($name);
        if ($value === false || $value === '') {
            return $default;
        }

        return (int)$value;
    }
}

if (!function_exists('social_auth_config')) {
    function social_auth_config(): array
    {
        static $config = null;

        if ($config !== null) {
            return $config;
        }

        $telegramBotUsername = trim((string)($GLOBALS['social_telegram_bot_username'] ?? getenv('SOCIAL_TELEGRAM_BOT_USERNAME') ?: ''));
        $telegramBotToken = trim((string)($GLOBALS['social_telegram_bot_token'] ?? getenv('SOCIAL_TELEGRAM_BOT_TOKEN') ?: ''));
        $telegramWidgetSize = strtolower(trim((string)($GLOBALS['social_telegram_widget_size'] ?? getenv('SOCIAL_TELEGRAM_WIDGET_SIZE') ?: 'large')));
        if (!in_array($telegramWidgetSize, ['large', 'medium', 'small'], true)) {
            $telegramWidgetSize = 'large';
        }

        $telegramEnabled = (bool)($GLOBALS['social_telegram_enabled'] ?? social_auth_env_bool('SOCIAL_TELEGRAM_ENABLED', false));
        $telegramEnabled = $telegramEnabled && $telegramBotUsername !== '' && $telegramBotToken !== '';

        $applePrivateKey = (string)($GLOBALS['social_apple_private_key'] ?? getenv('SOCIAL_APPLE_PRIVATE_KEY') ?: '');
        if ($applePrivateKey !== '') {
            $applePrivateKey = str_replace(["\r\n", '\n', '\r'], ["\n", "\n", "\r"], $applePrivateKey);
        }

        $applePrivateKeyPath = trim((string)($GLOBALS['social_apple_private_key_path'] ?? getenv('SOCIAL_APPLE_PRIVATE_KEY_PATH') ?: ''));
        $appleEnabled = (bool)($GLOBALS['social_apple_enabled'] ?? social_auth_env_bool('SOCIAL_APPLE_ENABLED', false));
        $appleEnabled = $appleEnabled
            && trim((string)($GLOBALS['social_apple_client_id'] ?? getenv('SOCIAL_APPLE_CLIENT_ID') ?: '')) !== ''
            && trim((string)($GLOBALS['social_apple_team_id'] ?? getenv('SOCIAL_APPLE_TEAM_ID') ?: '')) !== ''
            && trim((string)($GLOBALS['social_apple_key_id'] ?? getenv('SOCIAL_APPLE_KEY_ID') ?: '')) !== ''
            && ($applePrivateKey !== '' || $applePrivateKeyPath !== '');

        $config = [
            'auto_signup' => (bool)($GLOBALS['social_auth_auto_signup'] ?? social_auth_env_bool('SOCIAL_AUTH_AUTO_SIGNUP', true)),
            'session_ttl' => max(300, social_auth_env_int('SOCIAL_AUTH_SESSION_TTL', 900)),
            'telegram' => [
                'enabled' => $telegramEnabled,
                'bot_username' => $telegramBotUsername,
                'bot_token' => $telegramBotToken,
                'widget_size' => $telegramWidgetSize,
                'request_access' => (bool)($GLOBALS['social_telegram_request_write_access'] ?? social_auth_env_bool('SOCIAL_TELEGRAM_REQUEST_WRITE_ACCESS', false)),
                'auth_ttl' => max(60, min(86400, (int)($GLOBALS['social_telegram_auth_ttl'] ?? social_auth_env_int('SOCIAL_TELEGRAM_AUTH_TTL', 900)))),
            ],
            'apple' => [
                'enabled' => $appleEnabled,
                'client_id' => trim((string)($GLOBALS['social_apple_client_id'] ?? getenv('SOCIAL_APPLE_CLIENT_ID') ?: '')),
                'team_id' => trim((string)($GLOBALS['social_apple_team_id'] ?? getenv('SOCIAL_APPLE_TEAM_ID') ?: '')),
                'key_id' => trim((string)($GLOBALS['social_apple_key_id'] ?? getenv('SOCIAL_APPLE_KEY_ID') ?: '')),
                'private_key_path' => $applePrivateKeyPath,
                'private_key' => $applePrivateKey,
                'scope' => trim((string)($GLOBALS['social_apple_scope'] ?? getenv('SOCIAL_APPLE_SCOPE') ?: 'name email')),
            ],
        ];

        return $config;
    }
}

if (!function_exists('social_auth_provider_enabled')) {
    function social_auth_provider_enabled(string $provider): bool
    {
        $provider = strtolower(trim($provider));
        $config = social_auth_config();
        return !empty($config[$provider]['enabled']);
    }
}

if (!function_exists('social_auth_any_enabled')) {
    function social_auth_any_enabled(): bool
    {
        return social_auth_provider_enabled('telegram') || social_auth_provider_enabled('apple');
    }
}

if (!function_exists('social_auth_safe_returnto')) {
    function social_auth_safe_returnto(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }

        if (preg_match('~^https?://[^/]+/(.*)$~i', $raw, $m)) {
            $raw = $m[1];
        }

        $raw = ltrim($raw, '/');
        if ($raw === '') {
            return '';
        }

        if (preg_match('~^(?:https?:|//)~i', $raw) === 1) {
            return '';
        }

        if (preg_match('~^(?:login|logout|social_auth)\.php\b~i', $raw) === 1) {
            return '';
        }

        return preg_match('~^[A-Za-z0-9_./?=&%#\-]+$~', $raw) === 1 ? $raw : '';
    }
}

if (!function_exists('social_auth_redirect_url')) {
    function social_auth_redirect_url(string $returnto = ''): string
    {
        global $DEFAULTBASEURL;

        $path = social_auth_safe_returnto($returnto);
        if ($path === '') {
            return rtrim((string)$DEFAULTBASEURL, '/') . '/';
        }

        return rtrim((string)$DEFAULTBASEURL, '/') . '/' . $path;
    }
}

if (!function_exists('social_auth_callback_url')) {
    function social_auth_callback_url(string $provider): string
    {
        global $DEFAULTBASEURL;
        return rtrim((string)$DEFAULTBASEURL, '/') . '/social_auth.php?provider=' . rawurlencode($provider) . '&action=callback';
    }
}

if (!function_exists('social_auth_start_session')) {
    function social_auth_start_session(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }
}

if (!function_exists('social_auth_begin')) {
    function social_auth_begin(string $provider, string $returnto = ''): array
    {
        $config = social_auth_config();
        social_auth_start_session();

        $provider = strtolower(trim($provider));
        $state = bin2hex(random_bytes(16));
        $nonce = bin2hex(random_bytes(16));

        $_SESSION['social_auth'][$provider] = [
            'state' => $state,
            'nonce' => $nonce,
            'returnto' => social_auth_safe_returnto($returnto),
            'started_at' => time(),
            'expires_at' => time() + (int)$config['session_ttl'],
        ];

        return $_SESSION['social_auth'][$provider];
    }
}

if (!function_exists('social_auth_pull_session')) {
    function social_auth_pull_session(string $provider, string $state): array
    {
        $provider = strtolower(trim($provider));
        social_auth_start_session();

        $payload = $_SESSION['social_auth'][$provider] ?? null;
        unset($_SESSION['social_auth'][$provider]);

        if (!is_array($payload)) {
            social_auth_fail('Ошибка авторизации', 'Сессия входа истекла. Попробуйте ещё раз.');
        }

        if (empty($payload['state']) || !hash_equals((string)$payload['state'], $state)) {
            social_auth_fail('Ошибка авторизации', 'Неверный параметр состояния. Повторите вход.');
        }

        $expiresAt = (int)($payload['expires_at'] ?? 0);
        if ($expiresAt > 0 && $expiresAt < time()) {
            social_auth_fail('Ошибка авторизации', 'Сессия входа истекла. Попробуйте ещё раз.');
        }

        return $payload;
    }
}

if (!function_exists('social_auth_require_table')) {
    function social_auth_require_table(): void
    {
        if (!class_permissions_table_exists('social_accounts')) {
            social_auth_fail('Ошибка', 'Таблица social_accounts не найдена. Выполните миграцию базы данных.');
        }
    }
}

if (!function_exists('social_auth_fail')) {
    function social_auth_fail(string $title, string $message): void
    {
        stdhead($title);
        stdmsg($title, $message, 'error');
        stdfoot();
        exit;
    }
}

if (!function_exists('social_auth_base64url_encode')) {
    function social_auth_base64url_encode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}

if (!function_exists('social_auth_base64url_decode')) {
    function social_auth_base64url_decode(string $data): string
    {
        $data = strtr($data, '-_', '+/');
        $padding = strlen($data) % 4;
        if ($padding !== 0) {
            $data .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode($data, true);
        return $decoded === false ? '' : $decoded;
    }
}

if (!function_exists('social_auth_json_request')) {
    function social_auth_json_request(string $url, array $postFields): array
    {
        $body = http_build_query($postFields);

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/x-www-form-urlencoded',
                    'Accept: application/json',
                ],
            ]);
            $responseBody = curl_exec($ch);
            $statusCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($responseBody === false) {
                return ['ok' => false, 'status' => $statusCode, 'body' => '', 'json' => [], 'error' => $error !== '' ? $error : 'cURL request failed'];
            }

            $json = json_decode((string)$responseBody, true);
            return [
                'ok' => $statusCode >= 200 && $statusCode < 300 && is_array($json),
                'status' => $statusCode,
                'body' => (string)$responseBody,
                'json' => is_array($json) ? $json : [],
                'error' => '',
            ];
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\nAccept: application/json\r\n",
                'content' => $body,
                'timeout' => 15,
                'ignore_errors' => true,
            ],
        ]);

        $responseBody = @file_get_contents($url, false, $context);
        $statusCode = 0;
        $headers = $http_response_header ?? [];
        if (!empty($headers[0]) && preg_match('~\s(\d{3})\s~', (string)$headers[0], $m)) {
            $statusCode = (int)$m[1];
        }

        $json = json_decode((string)$responseBody, true);
        return [
            'ok' => $responseBody !== false && $statusCode >= 200 && $statusCode < 300 && is_array($json),
            'status' => $statusCode,
            'body' => $responseBody === false ? '' : (string)$responseBody,
            'json' => is_array($json) ? $json : [],
            'error' => $responseBody === false ? 'HTTP request failed' : '',
        ];
    }
}

if (!function_exists('social_auth_decode_jwt_payload')) {
    function social_auth_decode_jwt_payload(string $jwt): array
    {
        $parts = explode('.', $jwt);
        if (count($parts) < 2) {
            return [];
        }

        $payload = social_auth_base64url_decode((string)$parts[1]);
        if ($payload === '') {
            return [];
        }

        $decoded = json_decode($payload, true);
        return is_array($decoded) ? $decoded : [];
    }
}

if (!function_exists('social_auth_apple_private_key')) {
    function social_auth_apple_private_key(array $config): string
    {
        $key = trim((string)($config['apple']['private_key'] ?? ''));
        if ($key !== '') {
            return $key;
        }

        $path = trim((string)($config['apple']['private_key_path'] ?? ''));
        if ($path === '' || !is_file($path) || !is_readable($path)) {
            social_auth_fail('Apple ID', 'Не найден приватный ключ Apple. Проверьте SOCIAL_APPLE_PRIVATE_KEY_PATH.');
        }

        $contents = @file_get_contents($path);
        if ($contents === false || trim($contents) === '') {
            social_auth_fail('Apple ID', 'Не удалось прочитать приватный ключ Apple.');
        }

        return $contents;
    }
}

if (!function_exists('social_auth_apple_client_secret')) {
    function social_auth_apple_client_secret(array $config): string
    {
        $header = [
            'alg' => 'ES256',
            'kid' => (string)$config['apple']['key_id'],
            'typ' => 'JWT',
        ];

        $now = time();
        $claims = [
            'iss' => (string)$config['apple']['team_id'],
            'iat' => $now,
            'exp' => $now + 15777000,
            'aud' => 'https://appleid.apple.com',
            'sub' => (string)$config['apple']['client_id'],
        ];

        $unsigned = social_auth_base64url_encode(json_encode($header, JSON_UNESCAPED_SLASHES))
            . '.'
            . social_auth_base64url_encode(json_encode($claims, JSON_UNESCAPED_SLASHES));

        $privateKey = openssl_pkey_get_private(social_auth_apple_private_key($config));
        if ($privateKey === false) {
            social_auth_fail('Apple ID', 'Не удалось инициализировать приватный ключ Apple.');
        }

        $signature = '';
        $ok = openssl_sign($unsigned, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        openssl_free_key($privateKey);

        if (!$ok) {
            social_auth_fail('Apple ID', 'Не удалось подписать client_secret для Apple.');
        }

        return $unsigned . '.' . social_auth_base64url_encode($signature);
    }
}

if (!function_exists('social_auth_apple_exchange_code')) {
    function social_auth_apple_exchange_code(string $code, array $sessionPayload): array
    {
        $config = social_auth_config();
        $response = social_auth_json_request('https://appleid.apple.com/auth/token', [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'client_id' => (string)$config['apple']['client_id'],
            'client_secret' => social_auth_apple_client_secret($config),
            'redirect_uri' => social_auth_callback_url('apple'),
        ]);

        if (!$response['ok']) {
            $error = (string)($response['json']['error'] ?? $response['error'] ?? 'Token exchange failed');
            social_auth_fail('Apple ID', 'Apple не подтвердил вход: ' . social_auth_h($error));
        }

        $payload = $response['json'];
        $idToken = trim((string)($payload['id_token'] ?? ''));
        if ($idToken === '') {
            social_auth_fail('Apple ID', 'Apple не вернул id_token.');
        }

        $claims = social_auth_decode_jwt_payload($idToken);
        if ($claims === []) {
            social_auth_fail('Apple ID', 'Не удалось прочитать id_token Apple.');
        }

        if (($claims['iss'] ?? '') !== 'https://appleid.apple.com') {
            social_auth_fail('Apple ID', 'Неверный issuer в ответе Apple.');
        }

        $aud = $claims['aud'] ?? '';
        $clientId = (string)$config['apple']['client_id'];
        $audOk = is_array($aud) ? in_array($clientId, $aud, true) : ((string)$aud === $clientId);
        if (!$audOk) {
            social_auth_fail('Apple ID', 'Неверный audience в ответе Apple.');
        }

        $exp = (int)($claims['exp'] ?? 0);
        if ($exp !== 0 && $exp < (time() - 60)) {
            social_auth_fail('Apple ID', 'Срок действия токена Apple истёк.');
        }

        $expectedNonce = (string)($sessionPayload['nonce'] ?? '');
        $tokenNonce = (string)($claims['nonce'] ?? '');
        if ($expectedNonce !== '' && $tokenNonce !== '') {
            $hashedNonce = social_auth_base64url_encode(hash('sha256', $expectedNonce, true));
            if (!hash_equals($expectedNonce, $tokenNonce) && !hash_equals($hashedNonce, $tokenNonce)) {
                social_auth_fail('Apple ID', 'Проверка nonce не прошла.');
            }
        }

        return $claims;
    }
}

if (!function_exists('social_auth_verify_telegram_payload')) {
    function social_auth_verify_telegram_payload(array $input, string $botToken, int $ttl): bool
    {
        $hash = trim((string)($input['hash'] ?? ''));
        $authDate = (int)($input['auth_date'] ?? 0);
        $id = trim((string)($input['id'] ?? ''));

        if ($hash === '' || $authDate <= 0 || $id === '') {
            return false;
        }

        if (abs(time() - $authDate) > $ttl) {
            return false;
        }

        $check = [];
        foreach ($input as $key => $value) {
            if ($key === 'hash' || $value === '' || $value === null) {
                continue;
            }

            $check[$key] = (string)$value;
        }

        ksort($check, SORT_STRING);
        $lines = [];
        foreach ($check as $key => $value) {
            $lines[] = $key . '=' . $value;
        }

        $dataCheckString = implode("\n", $lines);
        $secretKey = hash('sha256', $botToken, true);
        $calculatedHash = hash_hmac('sha256', $dataCheckString, $secretKey);

        return hash_equals($calculatedHash, $hash);
    }
}

if (!function_exists('social_auth_find_link')) {
    function social_auth_find_link(string $provider, string $providerUserId): ?array
    {
        social_auth_require_table();

        $res = sql_query("
            SELECT id, user_id, provider, provider_user_id
            FROM social_accounts
            WHERE provider = " . sqlesc($provider) . "
              AND provider_user_id = " . sqlesc($providerUserId) . "
            LIMIT 1
        ") or sqlerr(__FILE__, __LINE__);

        if (!$res || mysqli_num_rows($res) === 0) {
            return null;
        }

        return mysqli_fetch_assoc($res) ?: null;
    }
}

if (!function_exists('social_auth_find_user_by_email')) {
    function social_auth_find_user_by_email(string $email): ?array
    {
        $email = trim($email);
        if ($email === '') {
            return null;
        }

        $res = sql_query("
            SELECT id, username, email, enabled, status
            FROM users
            WHERE LOWER(email) = LOWER(" . sqlesc($email) . ")
            LIMIT 1
        ") or sqlerr(__FILE__, __LINE__);

        if (!$res || mysqli_num_rows($res) === 0) {
            return null;
        }

        return mysqli_fetch_assoc($res) ?: null;
    }
}

if (!function_exists('social_auth_username_exists')) {
    function social_auth_username_exists(string $username): bool
    {
        $res = sql_query("SELECT 1 FROM users WHERE username = " . sqlesc($username) . " LIMIT 1") or sqlerr(__FILE__, __LINE__);
        return $res && mysqli_num_rows($res) > 0;
    }
}

if (!function_exists('social_auth_clean_username')) {
    function social_auth_clean_username(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $value = preg_replace('/\s+/u', '_', $value) ?? $value;
        $value = preg_replace('/[^\p{L}\p{N}_]+/u', '', $value) ?? $value;
        $value = trim($value, '_');

        if ($value === '') {
            return '';
        }

        if (mb_strlen($value, 'UTF-8') > 12) {
            $value = mb_substr($value, 0, 12, 'UTF-8');
        }

        return trim($value, '_');
    }
}

if (!function_exists('social_auth_generate_username')) {
    function social_auth_generate_username(array $identity): string
    {
        $provider = (string)($identity['provider'] ?? 'user');
        $providerUserId = (string)($identity['provider_user_id'] ?? '');
        $email = trim((string)($identity['email'] ?? ''));
        $emailBase = $email !== '' && str_contains($email, '@') ? (string)strtok($email, '@') : '';

        $candidates = [
            (string)($identity['provider_username'] ?? ''),
            (string)($identity['display_name'] ?? ''),
            $emailBase,
            ($provider === 'telegram' ? 'tg_' : 'apple_') . substr(preg_replace('/[^a-z0-9]+/i', '', strtolower($providerUserId)), 0, 8),
            ($provider === 'telegram' ? 'tg_' : 'apple_') . substr(md5($provider . ':' . $providerUserId), 0, 8),
        ];

        foreach ($candidates as $candidate) {
            $candidate = social_auth_clean_username($candidate);
            if ($candidate === '' || mb_strlen($candidate, 'UTF-8') < 3) {
                continue;
            }

            if (!social_auth_username_exists($candidate)) {
                return $candidate;
            }

            for ($i = 1; $i <= 99; $i++) {
                $suffix = (string)$i;
                $baseLength = max(3, 12 - strlen($suffix));
                $variant = social_auth_clean_username(mb_substr($candidate, 0, $baseLength, 'UTF-8') . $suffix);
                if ($variant !== '' && mb_strlen($variant, 'UTF-8') >= 3 && !social_auth_username_exists($variant)) {
                    return $variant;
                }
            }
        }

        $fallback = social_auth_clean_username(substr(md5($provider . ':' . $providerUserId . ':' . microtime(true)), 0, 12));
        if ($fallback === '' || mb_strlen($fallback, 'UTF-8') < 3) {
            $fallback = ($provider === 'telegram' ? 'tg' : 'apl') . substr(md5($providerUserId), 0, 9);
        }

        if (!social_auth_username_exists($fallback)) {
            return $fallback;
        }

        return ($provider === 'telegram' ? 'tg' : 'apl') . substr(md5($providerUserId . ':' . random_int(1000, 9999)), 0, 9);
    }
}

if (!function_exists('social_auth_placeholder_email')) {
    function social_auth_placeholder_email(string $provider, string $providerUserId): string
    {
        $base = strtolower(preg_replace('/[^a-z0-9]+/i', '', $provider . '_' . $providerUserId));
        if ($base === '') {
            $base = $provider . '_' . substr(md5($providerUserId), 0, 12);
        }

        $base = substr($base, 0, 48);
        $email = $base . '@users.invalid';

        if (!social_auth_find_user_by_email($email)) {
            return $email;
        }

        for ($i = 1; $i <= 50; $i++) {
            $candidate = substr($base, 0, max(1, 48 - strlen((string)$i))) . $i . '@users.invalid';
            if (!social_auth_find_user_by_email($candidate)) {
                return $candidate;
            }
        }

        return substr(md5($provider . ':' . $providerUserId . ':' . microtime(true)), 0, 24) . '@users.invalid';
    }
}

if (!function_exists('social_auth_prepare_email')) {
    function social_auth_prepare_email(array $identity): array
    {
        $provider = (string)($identity['provider'] ?? '');
        $providerUserId = (string)($identity['provider_user_id'] ?? '');
        $email = trim((string)($identity['email'] ?? ''));

        if ($email !== '' && validemail($email)) {
            $existing = social_auth_find_user_by_email($email);
            if ($existing) {
                social_auth_fail(
                    'Социальный вход',
                    'На этот email уже зарегистрирован аккаунт <b>' . social_auth_h((string)$existing['username']) . '</b>. '
                    . 'Войдите обычным способом и затем привяжите ' . social_auth_h($provider) . ' к существующему профилю.'
                );
            }

            return ['email' => $email, 'placeholder' => false];
        }

        return [
            'email' => social_auth_placeholder_email($provider, $providerUserId),
            'placeholder' => true,
        ];
    }
}

if (!function_exists('social_auth_create_user')) {
    function social_auth_create_user(array $identity): int
    {
        global $maxusers;

        $config = social_auth_config();
        if (empty($config['auto_signup'])) {
            social_auth_fail('Социальный вход', 'Автоматическое создание аккаунтов отключено.');
        }

        if (get_row_count('users') >= (int)$maxusers) {
            social_auth_fail('Социальный вход', 'Достигнут лимит пользователей. Автоматическая регистрация временно недоступна.');
        }

        $emailInfo = social_auth_prepare_email($identity);
        $username = social_auth_generate_username($identity);

        $randomPassword = social_auth_base64url_encode(random_bytes(24));
        $secret = mksecret();
        $passhash = hash_legacy_password($randomPassword, $secret);
        $modernHash = tracker_hash_password($randomPassword);
        $passkey = tracker_generate_passkey();
        $provider = (string)($identity['provider'] ?? '');
        $providerUsername = trim((string)($identity['provider_username'] ?? ''));
        $displayName = trim((string)($identity['display_name'] ?? ''));
        $telegram = $provider === 'telegram' && $providerUsername !== '' ? '@' . ltrim($providerUsername, '@') : '';
        $title = $displayName !== '' ? mb_substr($displayName, 0, 120, 'UTF-8') : '';
        $modcomment = 'Автоматически создан через ' . ($provider === 'apple' ? 'Apple ID' : 'Telegram') . ' ' . date('Y-m-d H:i:s');

        sql_query("
            INSERT INTO users (
                username, passhash, secret, pss, email, status, added, enabled, parked,
                passkey, telegram, gender, title, modcomment
            ) VALUES (
                " . sqlesc($username) . ",
                " . sqlesc($passhash) . ",
                " . sqlesc($secret) . ",
                " . sqlesc($modernHash) . ",
                " . sqlesc((string)$emailInfo['email']) . ",
                'confirmed',
                NOW(),
                'yes',
                'no',
                " . sqlesc($passkey) . ",
                " . sqlesc($telegram) . ",
                '3',
                " . sqlesc($title) . ",
                " . sqlesc($modcomment) . "
            )
        ") or sqlerr(__FILE__, __LINE__);

        $userId = (int)mysqli_insert_id($GLOBALS['mysqli']);
        if ($userId <= 0) {
            social_auth_fail('Социальный вход', 'Не удалось создать пользователя.');
        }

        $welcomeSubject = 'Добро пожаловать на сайт';
        $welcomeMessage = 'Ваш аккаунт был создан через ' . ($provider === 'apple' ? 'Apple ID' : 'Telegram') . ".\n\n"
            . 'Логин: ' . $username . "\n"
            . ($emailInfo['placeholder'] ? "В профиле лучше сразу указать рабочий email.\n" : '')
            . 'Пароль через эту соцсеть не нужен, но вы можете задать обычный пароль позже в настройках профиля.';

        sql_query("
            INSERT INTO messages (poster, sender, receiver, added, msg, subject, unread, location, saved)
            VALUES (0, 0, " . $userId . ", NOW(), " . sqlesc($welcomeMessage) . ", " . sqlesc($welcomeSubject) . ", 'yes', 1, 'no')
        ") or sqlerr(__FILE__, __LINE__);

        write_log('Создан новый аккаунт ' . $username . ' через ' . ($provider === 'apple' ? 'Apple ID' : 'Telegram'), 'B0E0FF', 'tracker');

        return $userId;
    }
}

if (!function_exists('social_auth_upsert_link')) {
    function social_auth_upsert_link(int $userId, array $identity): void
    {
        social_auth_require_table();

        $raw = json_encode($identity['raw'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($raw === false) {
            $raw = '{}';
        }

        sql_query("
            INSERT INTO social_accounts (
                user_id, provider, provider_user_id, provider_email, provider_username,
                display_name, avatar_url, raw_profile, created_at, updated_at, last_login_at
            ) VALUES (
                " . sqlesc($userId) . ",
                " . sqlesc((string)$identity['provider']) . ",
                " . sqlesc((string)$identity['provider_user_id']) . ",
                " . sqlesc((string)($identity['email'] ?? '')) . ",
                " . sqlesc((string)($identity['provider_username'] ?? '')) . ",
                " . sqlesc((string)($identity['display_name'] ?? '')) . ",
                " . sqlesc((string)($identity['avatar_url'] ?? '')) . ",
                " . sqlesc($raw) . ",
                NOW(),
                NOW(),
                NOW()
            )
            ON DUPLICATE KEY UPDATE
                provider_email = VALUES(provider_email),
                provider_username = VALUES(provider_username),
                display_name = VALUES(display_name),
                avatar_url = VALUES(avatar_url),
                raw_profile = VALUES(raw_profile),
                updated_at = NOW(),
                last_login_at = NOW()
        ") or sqlerr(__FILE__, __LINE__);
    }
}

if (!function_exists('social_auth_sync_user_fields')) {
    function social_auth_sync_user_fields(int $userId, array $identity): void
    {
        $updates = [];
        $provider = (string)($identity['provider'] ?? '');
        $providerUsername = trim((string)($identity['provider_username'] ?? ''));
        $email = trim((string)($identity['email'] ?? ''));

        if ($provider === 'telegram' && $providerUsername !== '') {
            $updates[] = "telegram = CASE WHEN telegram = '' THEN " . sqlesc('@' . ltrim($providerUsername, '@')) . " ELSE telegram END";
        }

        if ($provider === 'apple' && $email !== '' && validemail($email) && !social_auth_find_user_by_email($email)) {
            $updates[] = "email = CASE WHEN email LIKE '%@users.invalid' OR email = '' THEN " . sqlesc($email) . " ELSE email END";
        }

        if ($updates === []) {
            return;
        }

        sql_query("UPDATE users SET " . implode(', ', $updates) . " WHERE id = " . $userId . " LIMIT 1") or sqlerr(__FILE__, __LINE__);
        tracker_invalidate_user_auth_cache($userId);
    }
}

if (!function_exists('social_auth_login_user')) {
    function social_auth_login_user(int $userId, string $returnto = ''): void
    {
        $res = sql_query("
            SELECT id, passhash, enabled, status
            FROM users
            WHERE id = " . $userId . "
            LIMIT 1
        ") or sqlerr(__FILE__, __LINE__);

        $user = mysqli_fetch_assoc($res);
        if (!$user) {
            social_auth_fail('Социальный вход', 'Пользователь не найден после авторизации.');
        }

        if (($user['enabled'] ?? 'no') !== 'yes') {
            social_auth_fail('Социальный вход', 'Этот аккаунт отключён.');
        }

        if (($user['status'] ?? 'pending') !== 'confirmed') {
            social_auth_fail('Социальный вход', 'Этот аккаунт ещё не подтверждён.');
        }

        tracker_invalidate_user_auth_cache($userId);
        logincookie((int)$user['id'], (string)$user['passhash']);
        sql_query("UPDATE users SET last_login = NOW(), ip = " . sqlesc(getip()) . " WHERE id = " . $userId . " LIMIT 1") or sqlerr(__FILE__, __LINE__);

        header('Location: ' . social_auth_redirect_url($returnto));
        exit;
    }
}

if (!function_exists('social_auth_finish')) {
    function social_auth_finish(array $identity, string $returnto = ''): void
    {
        $provider = (string)($identity['provider'] ?? '');
        $providerUserId = (string)($identity['provider_user_id'] ?? '');

        if ($provider === '' || $providerUserId === '') {
            social_auth_fail('Социальный вход', 'Не удалось определить пользователя провайдера.');
        }

        $linked = social_auth_find_link($provider, $providerUserId);
        if ($linked) {
            $userId = (int)($linked['user_id'] ?? 0);
            if (!empty($GLOBALS['CURUSER']['id']) && (int)$GLOBALS['CURUSER']['id'] !== $userId) {
                social_auth_fail('Социальный вход', 'Этот ' . social_auth_h($provider) . ' уже привязан к другому аккаунту.');
            }
            social_auth_upsert_link($userId, $identity);
            social_auth_sync_user_fields($userId, $identity);
            social_auth_login_user($userId, $returnto);
        }

        if (!empty($GLOBALS['CURUSER']['id'])) {
            $currentUserId = (int)$GLOBALS['CURUSER']['id'];
            social_auth_upsert_link($currentUserId, $identity);
            social_auth_sync_user_fields($currentUserId, $identity);
            header('Location: ' . social_auth_redirect_url($returnto));
            exit;
        }

        $userId = social_auth_create_user($identity);
        social_auth_upsert_link($userId, $identity);
        social_auth_login_user($userId, $returnto);
    }
}

if (!function_exists('social_auth_login_markup')) {
    function social_auth_login_markup(string $returnto = ''): string
    {
        if (!social_auth_any_enabled()) {
            return '';
        }

        $html = [];
        $html[] = '<div style="margin-top:14px;text-align:center">';
        $html[] = '<div style="margin-bottom:8px;color:#666;">Быстрый вход без регистрации</div>';

        if (social_auth_provider_enabled('apple')) {
            $appleUrl = 'social_auth.php?provider=apple';
            $safeReturnTo = social_auth_safe_returnto($returnto);
            if ($safeReturnTo !== '') {
                $appleUrl .= '&returnto=' . rawurlencode($safeReturnTo);
            }

            $html[] = '<div style="margin-bottom:8px;">'
                . '<a class="btn btn-ghost" href="' . social_auth_h($appleUrl) . '">Войти через Apple ID</a>'
                . '</div>';
        }

        if (social_auth_provider_enabled('telegram')) {
            $config = social_auth_config();
            $session = social_auth_begin('telegram', $returnto);
            $telegramUrl = social_auth_callback_url('telegram') . '&state=' . rawurlencode((string)$session['state']);
            $html[] = '<div style="display:flex;justify-content:center;min-height:40px;">'
                . '<script async src="https://telegram.org/js/telegram-widget.js?22"'
                . ' data-telegram-login="' . social_auth_h((string)$config['telegram']['bot_username']) . '"'
                . ' data-size="' . social_auth_h((string)$config['telegram']['widget_size']) . '"'
                . ' data-auth-url="' . social_auth_h($telegramUrl) . '"'
                . (!empty($config['telegram']['request_access']) ? ' data-request-access="write"' : '')
                . '></script>'
                . '</div>';
        }

        $html[] = '<div style="margin-top:6px;font-size:11px;color:#777;">Аккаунт создаётся автоматически и получает обычный ID пользователя.</div>';
        $html[] = '</div>';

        return implode("\n", $html);
    }
}
