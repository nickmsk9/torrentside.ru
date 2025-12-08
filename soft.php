<?php
// soft.php — Upload Software on TorrentSide (PHP 8.1-ready)

declare(strict_types=1);

require_once __DIR__ . "/include/bittorrent.php";

dbconn(false);
loggedinorreturn();
parked();

global $CURUSER, $tracker_lang, $announce_urls, $max_torrent_size;

stdhead("Загрузить Софт на TorrentSide");
begin_frame("Загрузить Софт на TorrentSide");

// --- Проверка прав
if (get_user_class() < UC_USER) {
    stdmsg($tracker_lang['error'] ?? 'Ошибка', $tracker_lang['upget'] ?? 'Недостаточно прав.');
    stdfoot();
    exit;
}

// --- Генерация passkey, если отсутствует/битый
$passkey = (string)($CURUSER['passkey'] ?? '');
if (strlen($passkey) !== 32) {
    try {
        $passkey = bin2hex(random_bytes(16)); // 32 hex символа
    } catch (Throwable $e) {
        // маловероятный фоллбэк
        $passkey = md5(($CURUSER['username'] ?? '') . get_date_time() . ($CURUSER['passhash'] ?? ''));
    }
    $CURUSER['passkey'] = $passkey;
    sql_query("UPDATE users SET passkey = " . sqlesc($passkey) . " WHERE id = " . (int)$CURUSER['id']);
}

// --- Кнопки шаблонов
echo <<<HTML
<div style="text-align:center">
  <p><span style="color:green;font-weight:bold;">Вы можете выбрать один из шаблонов раздач.</span></p>
  <table width="500" cellspacing="0" align="center" class="menu">
    <tr>
      <td class="embedded"><form method="get" action="film.php"><input type="submit" value="Фильмы/Видео" style="height:20px;width:100px"></form></td>
      <td class="embedded"><form method="get" action="music.php"><input type="submit" value="Музыка/Аудио" style="height:20px;width:100px"></form></td>
      <td class="embedded"><form method="get" action="game.php"><input type="submit" value="Игры" style="height:20px;width:100px"></form></td>
      <td class="embedded"><form method="get" action="soft.php"><input type="submit" value="Софт" style="height:20px;width:100px"></form></td>
    </tr>
  </table>
HTML;

// --- Получаем type из GET
$descrtype = filter_input(INPUT_GET, 'type', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1]
]);

// --- Показ списка категорий, если type не задан
if ($descrtype === null) {
    $ids = [16, 20, 28];
    $idList = implode(',', array_map('intval', $ids));
    $res = sql_query("SELECT id, name FROM categories WHERE id IN ($idList) ORDER BY name ASC");

    echo '<br><table border="1" width="100%">';
    echo '<center><p><span style="color:green;font-weight:bold;">Или загрузите по общему шаблону, выбрав категорию:</span></p></center>';

    while ($row = mysqli_fetch_assoc($res)) {
        $cid = (int)$row['id'];
        $cname = htmlspecialchars((string)$row['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        echo "<tr><td class='lol' align='center'><a href=\"soft.php?type={$cid}\">{$cname}</a></td></tr>";
    }
    echo "</table>";
    stdfoot();
    exit;
} elseif ($descrtype === false) {
    stdmsg($tracker_lang['error'] ?? 'Ошибка', "Неверный ID категории");
    stdfoot();
    exit;
}

// --- CSRF токен
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

// --- Название выбранной категории (для селекта)
$catName = '';
$res = sql_query("SELECT id, name FROM categories WHERE id = " . (int)$descrtype . " LIMIT 1");
if ($res && ($row = mysqli_fetch_assoc($res))) {
    $catName = (string)$row['name'];
}
$catNameSafe = htmlspecialchars($catName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<div style="text-align:center">
  <p><span style="color:green;font-weight:bold;">После загрузки торрента вам нужно будет скачать торрент и начать сидировать из папки с оригинальными файлами.</span></p>

  <form id="upload" name="upload" enctype="multipart/form-data" action="takeuploadsoft.php" method="post" accept-charset="UTF-8" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <input type="hidden" name="MAX_FILE_SIZE" value="<?= (int)$max_torrent_size ?>" />
    <table border="1" cellspacing="0" cellpadding="5" style="margin:0 auto;min-width:800px">
      <tr><td class="colhead" colspan="2"><?= htmlspecialchars($tracker_lang['upload_torrent'] ?? 'Загрузка торрента', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td></tr>

      <?php
      tr($tracker_lang['announce_url'] ?? 'Announce URL', htmlspecialchars($announce_urls[0] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), 1);

      // Файл .torrent
      tr($tracker_lang['torrent_file'] ?? 'Файл торрента', '<input type="file" name="tfile" size="60" accept=".torrent" required>', 1);

      // Основные поля
      tr("Название", '<input type="text" name="name" size="60" maxlength="200" required placeholder="например — Nero">', 1);

      // Обложка
      $imgHelp = '<input type="url" name="image0" size="60" maxlength="500" placeholder="https://..." pattern="https?://.+" />'
        . '<br><b>Укажите URL картинки</b>';
      tr($tracker_lang['images'] ?? 'Картинка', $imgHelp, 1);

      tr("Год выхода", '<input type="number" name="year" min="1980" max="' . date('Y') . '" size="6" placeholder="' . date('Y') . '">', 1);
      tr("Разработчик", '<input type="text" name="director" size="40" maxlength="120" placeholder="например — Ahead">', 1);
      tr("Платформа", '<input type="text" name="plata" size="40" maxlength="120" placeholder="Windows / macOS / Linux">', 1);
      tr("Таблетка", '<input type="text" name="tablet" size="40" maxlength="120" placeholder="Не требуется / Ключ / Patch / Cracked">', 1);
      tr("Язык", '<input type="text" name="translation" size="40" maxlength="120" placeholder="Русский, Английский">', 1);

      // Описание
      echo "</td></tr><tr><td class='rowhead' style='padding:10px'>" . htmlspecialchars($tracker_lang['description'] ?? 'Описание', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</td><td class='lol'>";
      textbbcode("upload", "descr");
      echo "</td></tr>";

      // Системные требования
      echo "<tr><td class='rowhead' style='padding:10px'>Системные требования</td><td class='lol'>";
      textbbcode("upload", "opisanie", "", "0");
      echo "</td></tr>";

      // Скриншоты
      $shots = [];
      for ($i = 1; $i <= 4; $i++) {
          $shots[] = '<input type="url" name="image' . $i . '" size="60" maxlength="500" placeholder="https://..." pattern="https?://.+" />';
      }
      tr("Скриншоты", implode('<br>', $shots), 1);

      // Категория (зафиксированная выбранная)
      $s  = '<select name="type" required>';
      $s .= '<option value="' . (int)$descrtype . '" selected>' . ($catNameSafe !== '' ? $catNameSafe : 'Выбранная категория') . '</option>';
      $s .= '</select>';
      tr($tracker_lang['type'] ?? 'Категория', $s, 1);
      ?>

      <style type="text/css">
        code {font:99.9%/1.2 consolas,'courier new',monospace;}
        #from a {margin:2px; font-weight:normal; display:inline-block; padding:2px 6px; border:1px solid #ddd; border-radius:4px; text-decoration:none;}
        #tags {width:36em; max-width:100%;}
        a.selected {background:#c00; color:#fff;}
      </style>

      <script src="js/tagto.js"></script>
      <script>
        (function($){
          document.addEventListener('DOMContentLoaded', function(){
            if (typeof window.jQuery !== 'undefined' && typeof jQuery.fn.tagTo === 'function') {
              jQuery("#from").tagTo("#tags");
            }
          });
        })(window.jQuery || {});
      </script>

      <?php
      // Теги
      $tagsHtml = '<input type="text" id="tags" name="tags" placeholder="Выберите из списка ниже или введите свои">';
      $tagsHtml .= '<div id="from" aria-label="Список популярных тегов">';
      $tags = taggenrelist((int)$descrtype);
      if (empty($tags)) {
          $tagsHtml .= "Нет тегов для данной категории. Вы можете добавить собственные.";
      } else {
          foreach ($tags as $row) {
              $tname = htmlspecialchars((string)$row["name"], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
              $tagsHtml .= "<a href=\"#\" onclick=\"return false;\">{$tname}</a> ";
          }
      }
      $tagsHtml .= "</div>";
      tr("Теги", $tagsHtml, 1);

      // Скидка
      if (get_user_class() >= UC_USER) {
          $prc = '<select name="free">';
          for ($i = 0; $i <= 10; $i++) {
              $val = $i * 10;
              $prc .= '<option value="' . $val . '">' . $val . '</option>';
          }
          $prc .= '</select> процентов';
          tr("Скидка", $prc, 1);
      }
      ?>

      <tr>
        <td class="lol" align="center" colspan="2">
          <input type="submit" class="btn" value="<?= htmlspecialchars($tracker_lang['upload'] ?? 'Загрузить', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
        </td>
      </tr>
    </table>
  </form>
</div>
<?php
end_frame();
stdfoot();
