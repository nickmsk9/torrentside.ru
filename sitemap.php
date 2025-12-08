<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

define('LOG_FILE', __DIR__ . '/logs/sitemap.log');

require_once "include/bittorrent.php";
dbconn(false);

function log_event($msg) {
    $timestamp = date('[Y-m-d H:i:s]');
    file_put_contents(LOG_FILE, "$timestamp $msg\n", FILE_APPEND);
}

echo '<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Генерация sitemap</title>
</head>
<body>
<pre>';

try {
    log_event("Генерация карты сайта запущена.");
    gensitemap();
    echo "✅ Sitemap успешно сгенерирован!\n";
    log_event("Генерация завершена успешно.");
} catch (Throwable $e) {
    echo "❌ Ошибка: " . $e->getMessage();
    log_event("ФАТАЛЬНАЯ ОШИБКА: " . $e->getMessage());
}

echo "\n\nПоследние строки лога:\n";
echo htmlspecialchars(shell_exec("tail -n 10 " . LOG_FILE) ?? '');

echo '</pre></body></html>';

function gensitemap(): void {
    global $mysqli;

    $host = 'http://' . $_SERVER['HTTP_HOST'];
    $now = date('c');

    $txt = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<?xml-stylesheet type="text/xsl" href="gss.xsl"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:schemaLocation="http://www.google.com/schemas/sitemap/0.84 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd">

<url><loc>{$host}/browse.php</loc><lastmod>{$now}</lastmod><changefreq>hourly</changefreq><priority>1.0</priority></url>
<url><loc>{$host}/</loc><lastmod>{$now}</lastmod><changefreq>hourly</changefreq><priority>1.0</priority></url>

XML;

    $res = sql_query("SELECT id, added FROM torrents ORDER BY added DESC LIMIT 300");
    $torrents = 0;
    while ($row = mysqli_fetch_assoc($res)) {
        $txt .= "<url><loc>{$host}/details.php?id={$row['id']}</loc><lastmod>" . date('c', strtotime($row['added'])) . "</lastmod><changefreq>daily</changefreq><priority>0.5</priority></url>\n";
        $torrents++;
    }
    log_event("Добавлено $torrents торрентов в sitemap.");

    $res = sql_query("SELECT id FROM categories");
    $cats = 0;
    while ($row = mysqli_fetch_assoc($res)) {
        $txt .= "<url><loc>{$host}/browse.php?cat={$row['id']}</loc><lastmod>{$now}</lastmod><changefreq>hourly</changefreq><priority>0.5</priority></url>\n";
        $cats++;
    }
    log_event("Добавлено $cats категорий в sitemap.");

    $txt .= "</urlset>\n";

    if (!file_put_contents('./sitemap.xml', $txt)) {
        throw new RuntimeException("Не удалось записать sitemap.xml");
    }

    log_event("Файл sitemap.xml успешно записан.");
}
