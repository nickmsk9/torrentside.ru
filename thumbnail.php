<?php
declare(strict_types=1);

/**
 * Secure image resizer/thumbnail proxy for TBDev
 * Usage (совместимо со старым стилем):
 *   /thumb.php?f=torrents/images/cover.jpg
 *   /thumb.php?torrents/images/cover.jpg   (старый стиль через QUERY_STRING)
 */

//// CONFIG ////
define('IMAGE_BASE', __DIR__ . '/torrents/images'); // абсолютный путь на сервере
define('MAX_WIDTH',  1280);
define('MAX_HEIGHT', 1024);
define('JPEG_QUALITY', 90);  // 0..100
define('PNG_COMP', 5);       // 0..9
define('THUMB_CACHE_DIR', __DIR__ . '/cache/thumbs');

//// helpers ////
function send_404(): void {
    http_response_code(404);
    header('Content-Type: image/png');
    // маленькая серо-чёрная «заглушка» 1x1, base64
    echo base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR4nGNgYAAAAAMAAWgmWQ0AAAAASUVORK5CYII=');
    exit;
}
function sanitize_rel_path(string $s): string {
    // убираем query leftovers и опасности
    $s = trim($s);
    $s = preg_replace('~[#?].*$~', '', $s);
    $s = str_replace(["\0", '\\'], ['', '/'], $s);
    // запрет на выход вверх
    $s = preg_replace('~/+~', '/', $s);
    $s = ltrim($s, '/');
    if (str_contains($s, '..')) send_404();
    return $s;
}
function ext_of(string $path): string {
    return strtolower(pathinfo($path, PATHINFO_EXTENSION));
}
function real_under_base(string $absPath, string $base): bool {
    $rp = realpath($absPath);
    $rb = realpath($base);
    return $rp !== false && $rb !== false && str_starts_with($rp, $rb);
}
function thumb_mime_by_ext(string $ext): string {
    return match ($ext) {
        'jpg', 'jpeg' => 'image/jpeg',
        'png'         => 'image/png',
        'gif'         => 'image/gif',
        default       => 'application/octet-stream',
    };
}
function thumb_cache_file(string $relPath, int $mtime, string $ext, int $origW, int $origH): string {
    $signature = sha1(implode('|', [
        $relPath,
        (string)$mtime,
        $ext,
        (string)$origW,
        (string)$origH,
        (string)MAX_WIDTH,
        (string)MAX_HEIGHT,
        (string)JPEG_QUALITY,
        (string)PNG_COMP,
    ]));

    $dir = THUMB_CACHE_DIR . '/' . substr($signature, 0, 2) . '/' . substr($signature, 2, 2);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    return $dir . '/' . $signature . '.' . $ext;
}
function thumb_etag(string $cacheFile, int $mtime): string {
    return '"' . sha1($cacheFile . '|' . $mtime . '|' . (is_file($cacheFile) ? (string)filesize($cacheFile) : '0')) . '"';
}
function thumb_is_not_modified(string $etag, int $mtime): bool {
    $ifNoneMatch = trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''));
    if ($ifNoneMatch !== '' && $ifNoneMatch === $etag) {
        return true;
    }

    $ifModifiedSince = trim((string)($_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? ''));
    if ($ifModifiedSince !== '') {
        $since = strtotime($ifModifiedSince);
        if ($since !== false && $since >= $mtime) {
            return true;
        }
    }

    return false;
}
function thumb_send_file(string $file, string $ext, int $mtime): void {
    if (!is_file($file)) {
        send_404();
    }

    $etag = thumb_etag($file, $mtime);
    if (thumb_is_not_modified($etag, $mtime)) {
        http_response_code(304);
        header('ETag: ' . $etag);
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
        header('Cache-Control: public, max-age=2592000, immutable');
        exit;
    }

    header('Content-Type: ' . thumb_mime_by_ext($ext));
    header('Content-Length: ' . (string)filesize($file));
    header('ETag: ' . $etag);
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
    header('Cache-Control: public, max-age=2592000, immutable');
    readfile($file);
    exit;
}
function auto_orient_jpeg($img, string $file): GdImage {
    if (!function_exists('exif_read_data')) return $img;
    $ext = ext_of($file);
    if ($ext !== 'jpg' && $ext !== 'jpeg') return $img;
    $exif = @exif_read_data($file);
    if (!$exif || empty($exif['Orientation'])) return $img;
    switch ((int)$exif['Orientation']) {
        case 3: $img = imagerotate($img, 180, 0); break;
        case 6: $img = imagerotate($img, -90, 0); break;
        case 8: $img = imagerotate($img, 90, 0);  break;
    }
    return $img;
}

//// resolve requested file ////
// 1) предпочтительно ?f=...; 2) бэкап — «старый стиль» когда весь QUERY_STRING = путь
$req = '';
if (isset($_GET['f']) && is_string($_GET['f'])) {
    $req = $_GET['f'];
} else {
    // если в QUERY_STRING нет '=', считаем что это «/thumb.php?relative/path.jpg»
    $qs = $_SERVER['QUERY_STRING'] ?? '';
    if ($qs !== '' && !str_contains($qs, '=')) $req = $qs;
}
if ($req === '') send_404();

$relPath = sanitize_rel_path($req);

// если вдруг прислали абсолютный внутри torrents/images, отрежем префикс
$relPath = preg_replace('~^torrents/images/~i', '', $relPath);

$absPath = IMAGE_BASE . '/' . $relPath;
if (!real_under_base($absPath, IMAGE_BASE) || !is_file($absPath)) send_404();

$ext = ext_of($absPath);
$allowed = ['jpg','jpeg','png','gif'];
if (!in_array($ext, $allowed, true)) send_404();

//// fast-path: если даже по EXIF не надо поворачивать и картинка не больше лимитов — отдать как есть ////
[$origW, $origH] = @getimagesize($absPath) ?: [0,0];
if ($origW <= 0 || $origH <= 0) send_404();

$scale = min(MAX_WIDTH / $origW, MAX_HEIGHT / $origH, 1.0);
$needsResize = ($scale < 1.0);

// Попробуем понять, нужен ли автоповорот по EXIF (для JPEG)
$needsOrient = false;
if ($ext === 'jpg' || $ext === 'jpeg') {
    if (function_exists('exif_read_data')) {
        $exif = @exif_read_data($absPath);
        $needsOrient = !empty($exif['Orientation']) && in_array((int)$exif['Orientation'], [3,6,8], true);
    }
}

// Если правки не нужны — отдадим оригинал с корректным типом и кэшем
if (!$needsResize && !$needsOrient) {
    $mtime = filemtime($absPath) ?: time();
    thumb_send_file($absPath, $ext, $mtime);
}

$mtime = filemtime($absPath) ?: time();
$cacheFile = thumb_cache_file($relPath, $mtime, $ext, $origW, $origH);
$lockFile = $cacheFile . '.lock';
$lockHandle = @fopen($lockFile, 'c');

if ($lockHandle) {
    @flock($lockHandle, LOCK_EX);
}

clearstatcache(true, $cacheFile);
if (is_file($cacheFile) && filesize($cacheFile) > 0) {
    if ($lockHandle) {
        @flock($lockHandle, LOCK_UN);
        @fclose($lockHandle);
    }
    thumb_send_file($cacheFile, $ext, $mtime);
}

//// load ////
$img = match ($ext) {
    'jpg','jpeg' => @imagecreatefromjpeg($absPath),
    'png'        => @imagecreatefrompng($absPath),
    'gif'        => @imagecreatefromgif($absPath),
    default      => null,
};
if (!$img) send_404();

// EXIF orientation
$img = auto_orient_jpeg($img, $absPath);
$width  = imagesx($img);
$height = imagesy($img);

// Resize if needed
if ($needsResize) {
    $newW = max(1, (int)floor($width  * $scale));
    $newH = max(1, (int)floor($height * $scale));
    $dst = imagecreatetruecolor($newW, $newH);

    // preserve alpha for PNG/GIF
    if ($ext === 'png') {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefilledrectangle($dst, 0, 0, $newW, $newH, $transparent);
    } elseif ($ext === 'gif') {
        $trnIndex = imagecolortransparent($img);
        if ($trnIndex >= 0) {
            $trnColor = imagecolorsforindex($img, $trnIndex);
            $trnIndexNew = imagecolorallocate($dst, $trnColor['red'], $trnColor['green'], $trnColor['blue']);
            imagefill($dst, 0, 0, $trnIndexNew);
            imagecolortransparent($dst, $trnIndexNew);
        }
    }

    imagecopyresampled($dst, $img, 0, 0, 0, 0, $newW, $newH, $width, $height);
    imagedestroy($img);
    $img = $dst;
}

// Output to disk cache first, then serve cached file
$tmpFile = $cacheFile . '.tmp';
$writeOk = false;
switch ($ext) {
    case 'jpg':
    case 'jpeg':
        imageinterlace($img, true);
        $writeOk = imagejpeg($img, $tmpFile, JPEG_QUALITY);
        break;
    case 'png':
        $writeOk = imagepng($img, $tmpFile, PNG_COMP);
        break;
    case 'gif':
        $writeOk = imagegif($img, $tmpFile);
        break;
}
imagedestroy($img);

if ($writeOk) {
    @rename($tmpFile, $cacheFile);
    @chmod($cacheFile, 0664);
} elseif (is_file($tmpFile)) {
    @unlink($tmpFile);
}

if ($lockHandle) {
    @flock($lockHandle, LOCK_UN);
    @fclose($lockHandle);
}

clearstatcache(true, $cacheFile);
if (is_file($cacheFile) && filesize($cacheFile) > 0) {
    thumb_send_file($cacheFile, $ext, $mtime);
}

send_404();
