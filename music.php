<?php
// music.php — Upload Audio on TorrentSide (optimized for PHP 8.1)

declare(strict_types=1);

require_once __DIR__ . "/include/bittorrent.php";

dbconn(false);
loggedinorreturn();
parked();

global $CURUSER, $tracker_lang, $announce_urls, $max_torrent_size;

// Заголовки
stdhead("Загрузить Аудио на TorrentSide");
begin_frame("Загрузить Аудио на TorrentSide");

// --- Проверка прав
if (get_user_class() < UC_USER) {
    stdmsg($tracker_lang['error'] ?? 'Ошибка', $tracker_lang['upget'] ?? 'Недостаточно прав.');
    stdfoot();
    exit;
}

// --- Генерация passkey, если отсутствует или неверной длины
$passkey = (string)($CURUSER['passkey'] ?? '');
if (strlen($passkey) !== 32) {
    try {
        $passkey = bin2hex(random_bytes(16)); // 32 hex-символа
    } catch (Throwable $e) {
        // Фоллбэк (крайне маловероятно)
        $passkey = md5(($CURUSER['username'] ?? '') . get_date_time() . ($CURUSER['passhash'] ?? ''));
    }
    $CURUSER['passkey'] = $passkey;
    sql_query("UPDATE users SET passkey = " . sqlesc($passkey) . " WHERE id = " . (int)$CURUSER['id']);
}

// --- Кнопки шаблонов категорий
echo <<<HTML
<div style="text-align:center">
  <p><span style="color:green;font-weight:bold;">Вы можете выбрать один из шаблонов раздач.</span></p>
  <table width="500" cellspacing="0" align="center" class="menu">
    <tr>
      <td class="embedded">
        <form method="get" action="film.php">
          <input type="submit" value="Фильмы/Видео" style="height:20px;width:100px">
        </form>
      </td>
      <td class="embedded">
        <form method="get" action="music.php">
          <input type="submit" value="Музыка/Аудио" style="height:20px;width:100px">
        </form>
      </td>
      <td class="embedded">
        <form method="get" action="game.php">
          <input type="submit" value="Игры" style="height:20px;width:100px">
        </form>
      </td>
      <td class="embedded">
        <form method="get" action="soft.php">
          <input type="submit" value="Софт" style="height:20px;width:100px">
        </form>
      </td>
    </tr>
  </table>
HTML;

// --- Получаем/проверяем тип категории, если передан
$descrtype = filter_input(INPUT_GET, 'type', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1]
]);

if ($descrtype === null) {
    // type не передан — показать список доступных категорий
    $res = sql_query("SELECT id, name FROM categories WHERE id IN (10,20,24) ORDER BY id ASC");

    echo '<br><table border="1" width="100%">';
    echo '<center><p><span style="color:green;font-weight:bold;">Или загрузить раздачу по общему шаблону, выбрав категорию.</span></p></center>';

    while ($row = mysqli_fetch_assoc($res)) {
        $cid  = (int)$row['id'];
        $cname = htmlspecialchars((string)$row['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        echo "<tr><td class='lol' align='center'><a href=\"music.php?type={$cid}\">{$cname}</a></td></tr>";
    }
    echo "</table>";
    stdfoot();
    exit;
} elseif ($descrtype === false) {
    // type передан, но некорректен
    stdmsg($tracker_lang['error'] ?? 'Ошибка', "Неверный ID категории");
    stdfoot();
    exit;
}

// --- CSRF токен
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

// --- Опционально получим название категории (для селекта ниже)
$catName = '';
$res = sql_query("SELECT id, name FROM categories WHERE id = " . (int)$descrtype . " LIMIT 1");
if ($res && ($row = mysqli_fetch_assoc($res))) {
    $catName = (string)$row['name'];
}
$catNameSafe = htmlspecialchars($catName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

// --- Форма загрузки
?>
<div style="text-align:center">
  <p><span style="color:green;font-weight:bold;">
    После загрузки торрента вам нужно будет скачать торрент и поставить качаться в папку, где лежат оригиналы файлов.
  </span></p>

  <form id="upload" name="upload" enctype="multipart/form-data" action="takeuploadmusic.php" method="post" accept-charset="UTF-8" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <input type="hidden" name="MAX_FILE_SIZE" value="<?= (int)$max_torrent_size ?>" />
    <table border="1" cellspacing="0" cellpadding="5" style="margin:0 auto;min-width:800px">
      <tr><td class="colhead" colspan="2"><?= htmlspecialchars($tracker_lang['upload_torrent'] ?? 'Загрузка торрента', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td></tr>

      <?php
      tr($tracker_lang['announce_url'] ?? 'Announce URL', htmlspecialchars($announce_urls[0] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), 1);

      // Файл .torrent
      tr($tracker_lang['torrent_file'] ?? 'Файл торрента', '<input type="file" name="tfile" size="60" accept=".torrent" required>', 1);

      // Названия
      tr("Русское название", '<input type="text" name="name" size="60" maxlength="200" required placeholder="Например — Руки Вверх">', 1);
      tr("Альбом", '<input type="text" name="origname" size="60" maxlength="200" placeholder="Например — Без тормозов">', 1);

      // Картинка
      $imgHelp = '<input type="url" name="image0" size="60" maxlength="500" placeholder="https://..." pattern="https?://.+" />'
        . '<br><b>Укажите URL-адрес картинки</b><br>Если не знаете, куда загрузить: '
        . '<a href="https://imgur.com/" target="_blank" rel="nofollow noopener">Imgur</a>';
      tr($tracker_lang['images'] ?? 'Картинка', $imgHelp, 1);

      // Жанр/Время/Год
      tr("Жанр", '<input type="text" name="janr" size="40" maxlength="120" placeholder="Поп, Рок, Дэнс..." required>', 1);
      tr("Продолжительность", '<input type="text" name="time" size="20" maxlength="50" placeholder="00:42:15">', 1);
      tr("Год выхода", '<input type="number" name="year" min="1900" max="' . date('Y') . '" size="6" placeholder="' . date('Y') . '">', 1);

      echo "</td></tr><tr><td class='rowhead' style='padding:10px'>Треклист:</td><td class='lol'>";
      textbbcode("upload", "descr");
      echo "</td></tr>";

      // Аудио параметры
      tr(
          "Аудио",
          'Кодек: <input type="text" name="audiocodec" size="10" maxlength="20" placeholder="FLAC/MP3/AAC"> 
           Битрейт: <input type="text" name="audiobitrate" size="8" maxlength="10" placeholder="320/1411"> Кбит/с',
          1
      );

      // Категория (фиксированная выбранная)
      $s  = '<select name="type" required>';
      $s .= '<option value="' . (int)$descrtype . '" selected>' . ($catNameSafe !== '' ? $catNameSafe : 'Выбранная категория') . '</option>';
      $s .= '</select>';
      tr($tracker_lang['type'] ?? 'Категория', $s, 1);
      ?>

      <!-- Стили/JS для тегов -->
      <style type="text/css">
        code {font:99.9%/1.2 consolas,'courier new',monospace;}
        #from a {margin:2px 2px;font-weight:normal;display:inline-block;padding:2px 6px;border:1px solid #ddd;border-radius:4px;text-decoration:none;}
        #tags {width:36em;max-width:100%;}
        a.selected {background:#c00;color:#fff;}
        .addition {margin-top:2em;text-align:right;}
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
      // Теги (подгружаем и печатаем)
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
