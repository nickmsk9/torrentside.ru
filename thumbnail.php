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
define('JPEG_QUALITY', 85);  // 0..100
define('PNG_COMP', 6);       // 0..9

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
    $mime  = match ($ext) {
        'jpg','jpeg' => 'image/jpeg',
        'png'        => 'image/png',
        'gif'        => 'image/gif',
        default      => 'application/octet-stream',
    };

    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($absPath));
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
    header('Cache-Control: public, max-age=2592000, immutable'); // 30 дней
    readfile($absPath);
    exit;
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

// Output
$mtime = filemtime($absPath) ?: time();
switch ($ext) {
    case 'jpg':
    case 'jpeg':
        header('Content-Type: image/jpeg');
        header('Cache-Control: public, max-age=2592000, immutable');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
        imagejpeg($img, null, JPEG_QUALITY);
        break;
    case 'png':
        header('Content-Type: image/png');
        header('Cache-Control: public, max-age=2592000, immutable');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
        imagepng($img, null, PNG_COMP);
        break;
    case 'gif':
        header('Content-Type: image/gif');
        header('Cache-Control: public, max-age=2592000, immutable');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
        imagegif($img);
        break;
}
imagedestroy($img);
