<?php
declare(strict_types=1);

require_once __DIR__ . '/../include/secrets.php';

function userbar_get_user_id(): int
{
    $id = $_GET['id'] ?? null;
    if ($id !== null && ctype_digit((string)$id)) {
        return (int)$id;
    }

    $pathInfo = (string)($_SERVER['PATH_INFO'] ?? '');
    if ($pathInfo !== '' && preg_match('~/(\d+)(?:\.png)?$~', $pathInfo, $matches)) {
        return (int)$matches[1];
    }

    return 0;
}

function userbar_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function userbar_compact_size(float $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
    $value = max(0.0, $bytes);
    $unitIndex = 0;

    while ($value >= 1024 && $unitIndex < count($units) - 1) {
        $value /= 1024;
        $unitIndex++;
    }

    if ($unitIndex === 0) {
        return (string)(int)round($value) . ' ' . $units[$unitIndex];
    }

    $precision = $value >= 100 ? 0 : ($value >= 10 ? 1 : 2);
    return rtrim(rtrim(number_format($value, $precision, '.', ''), '0'), '.') . ' ' . $units[$unitIndex];
}

function userbar_ratio_meta(float $uploaded, float $downloaded): array
{
    if ($downloaded <= 0.0) {
        if ($uploaded <= 0.0) {
            return ['text' => '---', 'color' => '#8aa4b8'];
        }

        return ['text' => 'Inf.', 'color' => '#6fd1a8'];
    }

    $ratio = $uploaded / $downloaded;
    $text = $ratio > 100 ? '100+' : number_format($ratio, 2, '.', '');
    $color = '#d96a6a';

    if ($ratio >= 1.0) {
        $color = '#6fd1a8';
    } elseif ($ratio >= 0.7) {
        $color = '#f2c96d';
    }

    return ['text' => $text, 'color' => $color];
}

function userbar_trim_username(string $username, int $limit = 20): string
{
    $username = trim($username);
    if ($username === '') {
        return 'Unknown user';
    }

    if (function_exists('mb_strwidth') && function_exists('mb_strimwidth')) {
        return mb_strwidth($username, 'UTF-8') > $limit
            ? mb_strimwidth($username, 0, $limit, '...', 'UTF-8')
            : $username;
    }

    return strlen($username) > $limit ? (substr($username, 0, $limit - 3) . '...') : $username;
}

function userbar_render_svg(string $username, float $uploaded, float $downloaded): void
{
    $ratio = userbar_ratio_meta($uploaded, $downloaded);
    $displayName = userbar_trim_username($username);
    $upText = userbar_compact_size($uploaded);
    $downText = userbar_compact_size($downloaded);
    $fontStack = "system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif";

    header('Content-Type: image/svg+xml; charset=UTF-8');
    header('Cache-Control: public, max-age=300');
    header('X-Content-Type-Options: nosniff');

    echo <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="350" height="60" viewBox="0 0 350 60" role="img" aria-label="Userbar">
  <defs>
    <linearGradient id="bg" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="#f7fbff" />
      <stop offset="100%" stop-color="#dbeaf6" />
    </linearGradient>
    <linearGradient id="accent" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="#2d6f96" />
      <stop offset="100%" stop-color="#17364e" />
    </linearGradient>
  </defs>
  <rect width="350" height="60" rx="10" fill="url(#bg)" />
  <rect x="1" y="1" width="348" height="58" rx="9" fill="none" stroke="#b8d1e3" />
  <rect x="10" y="10" width="104" height="40" rx="8" fill="url(#accent)" />
  <text x="22" y="25" fill="#cfeeff" font-family="{$fontStack}" font-size="8" letter-spacing="1.4">USERBAR</text>
  <text x="22" y="42" fill="#ffffff" font-family="{$fontStack}" font-size="15" font-weight="700">{$displayName}</text>
  <text x="132" y="22" fill="#6b859a" font-family="{$fontStack}" font-size="8" letter-spacing="1">RATIO</text>
  <text x="132" y="40" fill="{$ratio['color']}" font-family="{$fontStack}" font-size="18" font-weight="700">{$ratio['text']}</text>
  <text x="210" y="22" fill="#6b859a" font-family="{$fontStack}" font-size="8" letter-spacing="1">UP</text>
  <text x="210" y="40" fill="#224e6f" font-family="{$fontStack}" font-size="12" font-weight="600">{$upText}</text>
  <text x="285" y="22" fill="#6b859a" font-family="{$fontStack}" font-size="8" letter-spacing="1">DL</text>
  <text x="285" y="40" fill="#224e6f" font-family="{$fontStack}" font-size="12" font-weight="600">{$downText}</text>
</svg>
SVG;
}

function userbar_render_error(string $message, int $statusCode = 200): void
{
    http_response_code($statusCode);
    userbar_render_svg($message, 0.0, 0.0);
}

$userId = userbar_get_user_id();
if ($userId <= 0) {
    userbar_render_error('Invalid user', 400);
    exit;
}

if (!($mysqli instanceof mysqli)) {
    userbar_render_error('DB unavailable', 503);
    exit;
}

$stmt = $mysqli->prepare('SELECT username, uploaded, downloaded FROM users WHERE id = ? LIMIT 1');
if (!$stmt) {
    userbar_render_error('Query error', 500);
    exit;
}

$stmt->bind_param('i', $userId);
$stmt->execute();
$stmt->bind_result($username, $uploaded, $downloaded);
$found = $stmt->fetch();
$stmt->close();

if (!$found) {
    userbar_render_error('User not found', 404);
    exit;
}

userbar_render_svg((string)$username, (float)$uploaded, (float)$downloaded);
