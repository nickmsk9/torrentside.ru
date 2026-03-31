<?php

if (!function_exists('tracker_cache_bootstrap')) {
    function tracker_cache_bootstrap(): ?Memcached
    {
        foreach (['memcached', 'mc', 'mc1'] as $name) {
            $candidate = $GLOBALS[$name] ?? null;
            if ($candidate instanceof Memcached) {
                return $candidate;
            }
        }

        if (!class_exists('Memcached')) {
            return null;
        }

        static $bootstrapping = false;
        if ($bootstrapping) {
            return null;
        }

        $bootstrapping = true;

        try {
            $poolId = 'torrentside_pool';
            $host = trim((string)(
                $GLOBALS['memcache_host']
                ?? getenv('MEMCACHE_HOST')
                ?? (($GLOBALS['site_config']['MEMCACHE_HOST'] ?? null))
                ?? '127.0.0.1'
            ));
            $port = (int)(
                $GLOBALS['memcache_port']
                ?? getenv('MEMCACHE_PORT')
                ?? (($GLOBALS['site_config']['MEMCACHE_PORT'] ?? null))
                ?? 11211
            );

            if ($host === '') {
                $host = '127.0.0.1';
            }
            if ($port <= 0) {
                $port = 11211;
            }

            $memcached = new Memcached($poolId);

            if (empty($memcached->getServerList())) {
                $memcached->addServer($host, $port);
            }

            if (defined('Memcached::OPT_BINARY_PROTOCOL')) {
                $memcached->setOption(Memcached::OPT_BINARY_PROTOCOL, true);
            }
            if (defined('Memcached::OPT_TCP_NODELAY')) {
                $memcached->setOption(Memcached::OPT_TCP_NODELAY, true);
            }
            if (defined('Memcached::OPT_CONNECT_TIMEOUT')) {
                $memcached->setOption(Memcached::OPT_CONNECT_TIMEOUT, 80);
            }
            if (defined('Memcached::OPT_RETRY_TIMEOUT')) {
                $memcached->setOption(Memcached::OPT_RETRY_TIMEOUT, 1);
            }
            if (defined('Memcached::OPT_POLL_TIMEOUT')) {
                $memcached->setOption(Memcached::OPT_POLL_TIMEOUT, 80);
            }

            $GLOBALS['memcached'] = $memcached;
            $GLOBALS['mc'] = $memcached;
            $GLOBALS['mc1'] = $memcached;

            return $memcached;
        } finally {
            $bootstrapping = false;
        }
    }
}

if (!function_exists('tracker_cache_instance')) {
    function tracker_cache_instance(): ?Memcached
    {
        foreach (['memcached', 'mc', 'mc1'] as $name) {
            $candidate = $GLOBALS[$name] ?? null;
            if ($candidate instanceof Memcached) {
                return $candidate;
            }
        }

        return tracker_cache_bootstrap();
    }
}

if (!function_exists('tracker_cache_key')) {
    function tracker_cache_key(string $prefix, ...$parts): string
    {
        $segments = ['ts', trim($prefix) !== '' ? trim($prefix) : 'default'];

        foreach ($parts as $part) {
            if (is_array($part) || is_object($part)) {
                $json = json_encode($part, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $part = $json === false ? md5(serialize($part)) : md5($json);
            } elseif (is_bool($part)) {
                $part = $part ? '1' : '0';
            } elseif ($part === null) {
                $part = 'null';
            } else {
                $part = (string)$part;
            }

            $part = trim($part);
            if ($part === '') {
                $part = 'x';
            }

            if (strlen($part) > 96) {
                $part = substr($part, 0, 32) . ':' . md5($part);
            }

            $part = preg_replace('~[^a-zA-Z0-9_\-:.]+~', '_', $part) ?? 'x';
            $segments[] = $part;
        }

        $key = implode(':', $segments);
        if (strlen($key) > 220) {
            $key = substr($key, 0, 180) . ':h:' . md5($key);
        }

        return $key;
    }
}

if (!function_exists('tracker_cache_get')) {
    function tracker_cache_get(string $key, ?bool &$hit = null)
    {
        $mc = tracker_cache_instance();
        if (!($mc instanceof Memcached)) {
            $hit = false;
            return null;
        }

        $value = $mc->get($key);
        $hit = ($mc->getResultCode() === Memcached::RES_SUCCESS);

        return $hit ? $value : null;
    }
}

if (!function_exists('tracker_cache_set')) {
    function tracker_cache_set(string $key, $value, int $ttl = 300): bool
    {
        $mc = tracker_cache_instance();
        if (!($mc instanceof Memcached)) {
            return false;
        }

        return $mc->set($key, $value, max(0, $ttl));
    }
}

if (!function_exists('tracker_cache_delete')) {
    function tracker_cache_delete(string $key): bool
    {
        $mc = tracker_cache_instance();
        if (!($mc instanceof Memcached)) {
            return false;
        }

        return $mc->delete($key);
    }
}

if (!function_exists('tracker_cache_delete_many')) {
    function tracker_cache_delete_many(array $keys): void
    {
        $mc = tracker_cache_instance();
        if (!($mc instanceof Memcached)) {
            return;
        }

        $keys = array_values(array_filter(array_map('strval', $keys), static fn(string $key): bool => $key !== ''));
        if (!$keys) {
            return;
        }

        if (method_exists($mc, 'deleteMulti')) {
            $mc->deleteMulti($keys);
            return;
        }

        foreach ($keys as $key) {
            $mc->delete($key);
        }
    }
}

if (!function_exists('tracker_cache_remember')) {
    function tracker_cache_remember(string $key, int $ttl, callable $resolver, ?bool &$cacheHit = null)
    {
        $value = tracker_cache_get($key, $hit);
        if ($hit) {
            $cacheHit = true;
            return $value;
        }

        $cacheHit = false;
        $value = $resolver();
        tracker_cache_set($key, $value, $ttl);

        return $value;
    }
}

if (!function_exists('tracker_cache_namespace_version')) {
    function tracker_cache_namespace_version(string $namespace): int
    {
        if (!isset($GLOBALS['tracker_cache_namespace_versions']) || !is_array($GLOBALS['tracker_cache_namespace_versions'])) {
            $GLOBALS['tracker_cache_namespace_versions'] = [];
        }
        $versions =& $GLOBALS['tracker_cache_namespace_versions'];

        $namespace = trim($namespace);
        if ($namespace === '') {
            $namespace = 'default';
        }

        if (isset($versions[$namespace])) {
            return $versions[$namespace];
        }

        $key = tracker_cache_key('nsver', $namespace);
        $value = tracker_cache_get($key, $hit);
        if (!$hit || !is_numeric($value)) {
            tracker_cache_set($key, 1, 0);
            $value = 1;
        }

        return $versions[$namespace] = max(1, (int)$value);
    }
}

if (!function_exists('tracker_cache_bump_namespace')) {
    function tracker_cache_bump_namespace(string $namespace): int
    {
        if (!isset($GLOBALS['tracker_cache_namespace_versions']) || !is_array($GLOBALS['tracker_cache_namespace_versions'])) {
            $GLOBALS['tracker_cache_namespace_versions'] = [];
        }
        $versions =& $GLOBALS['tracker_cache_namespace_versions'];

        $namespace = trim($namespace);
        if ($namespace === '') {
            $namespace = 'default';
        }

        $key = tracker_cache_key('nsver', $namespace);
        $mc = tracker_cache_instance();

        if ($mc instanceof Memcached) {
            $next = $mc->increment($key, 1, 2);
            if ($next === false) {
                $mc->set($key, 2, 0);
                $next = 2;
            }
        } else {
            $next = ($versions[$namespace] ?? 1) + 1;
        }

        $versions[$namespace] = max(1, (int)$next);

        return $versions[$namespace];
    }
}

if (!function_exists('tracker_cache_ns_key')) {
    function tracker_cache_ns_key(string $namespace, ...$parts): string
    {
        return tracker_cache_key('ns', $namespace, 'v' . tracker_cache_namespace_version($namespace), ...$parts);
    }
}

if (!function_exists('tracker_cache_render')) {
    function tracker_cache_render(string $key, int $ttl, callable $renderer): string
    {
        $cached = tracker_cache_remember($key, $ttl, static function () use ($renderer): string {
            return (string)$renderer();
        });

        return is_string($cached) ? $cached : '';
    }
}
