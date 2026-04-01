<?php
declare(strict_types=1);

if (!defined('ADMIN_FILE')) {
    die('Illegal File Access');
}

function admincp_tool_registry(): array
{
    static $registry = null;

    if (is_array($registry)) {
        return $registry;
    }

    $linksFile = __DIR__ . '/links/all.php';
    $loaded = is_file($linksFile) ? include $linksFile : [];

    return $registry = is_array($loaded) ? $loaded : [];
}

function admincp_route_definitions(): array
{
    static $routes = null;

    if (is_array($routes)) {
        return $routes;
    }

    $routes = [
        'faq' => [
            'title' => 'FAQ',
            'module' => 'faq.php',
            'dispatch' => [
                'faq' => 'FaqAdmin',
                'faqadmin' => 'FaqAdmin',
                'faqaction' => 'FaqAction',
            ],
        ],
        'iusers' => [
            'title' => 'Пароли и почта пользователей',
            'module' => 'iusers.php',
            'dispatch' => [
                'iusers' => 'iUsers',
            ],
        ],
        'class_permissions' => [
            'title' => 'Классы и права',
            'module' => 'class_permissions.php',
            'dispatch' => [
                'class_permissions' => 'class_permissions',
                'classpermissions' => 'class_permissions',
            ],
        ],
        'database' => [
            'title' => 'Операции с базой данных',
            'module' => 'database.php',
            'dispatch' => [
                'database' => 'database',
                'statusdb' => 'database',
            ],
        ],
        'ai_helper' => [
            'title' => 'AI-помощник',
            'module' => 'ai_helper.php',
            'dispatch' => [
                'ai_helper' => 'ai_helper',
                'aihelper' => 'ai_helper',
            ],
        ],
    ];

    return $routes;
}

function admincp_resolve_route(?string $requestedOp): array
{
    $requested = trim((string)$requestedOp);

    if ($requested === '') {
        return [
            'found' => true,
            'is_dashboard' => true,
            'requested' => '',
            'canonical' => 'dashboard',
            'dispatch_op' => '',
            'module_path' => null,
            'title' => 'Панель администратора',
            'url' => 'admincp.php',
        ];
    }

    $needle = strtolower($requested);
    foreach (admincp_route_definitions() as $canonical => $route) {
        foreach (($route['dispatch'] ?? []) as $alias => $dispatchOp) {
            if ($needle !== strtolower((string)$alias)) {
                continue;
            }

            $modulePath = __DIR__ . '/modules/' . $route['module'];

            return [
                'found' => is_file($modulePath),
                'is_dashboard' => false,
                'requested' => $requested,
                'canonical' => $canonical,
                'dispatch_op' => (string)$dispatchOp,
                'module_path' => is_file($modulePath) ? $modulePath : null,
                'title' => (string)($route['title'] ?? $canonical),
                'url' => 'admincp.php?op=' . rawurlencode($canonical),
            ];
        }
    }

    return [
        'found' => false,
        'is_dashboard' => true,
        'requested' => $requested,
        'canonical' => 'dashboard',
        'dispatch_op' => '',
        'module_path' => null,
        'title' => 'Панель администратора',
        'url' => 'admincp.php',
    ];
}

function admincp_validate_tool_link(array $tool): array
{
    $url = trim((string)($tool['url'] ?? ''));
    if ($url === '') {
        return ['ok' => false, 'reason' => 'empty-url'];
    }

    if (preg_match('~^https?://~i', $url)) {
        return ['ok' => true, 'reason' => 'external-url'];
    }

    $parts = parse_url($url);
    $path = (string)($parts['path'] ?? '');
    if ($path === '') {
        $path = $url;
    }

    $normalizedPath = ltrim($path, '/');
    if ($normalizedPath === 'admincp.php') {
        parse_str((string)($parts['query'] ?? ''), $query);
        $route = admincp_resolve_route((string)($query['op'] ?? ''));

        return [
            'ok' => $route['found'],
            'reason' => $route['found'] ? 'admincp-module' : 'missing-admincp-module',
            'route' => $route,
        ];
    }

    $fullPath = dirname(__DIR__) . '/' . $normalizedPath;

    return [
        'ok' => is_file($fullPath),
        'reason' => is_file($fullPath) ? 'php-page' : 'missing-php-page',
        'path' => $fullPath,
    ];
}
