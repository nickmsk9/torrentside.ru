<?php

require_once "include/bittorrent.php";

dbconn(false);
loggedinorreturn();

// Устанавливаем кодировку страницы
header("Content-Type: text/html; charset=" . $tracker_lang['language_charset']);

// Проверяем, что это AJAX-запрос предпросмотра и сообщение передано
if (isset($_GET['ajax']) && isset($_POST['msg'])) {
    // Расшифровываем сообщение
    $realmsg = base64_decode($_POST['msg']);

    // Безопасно форматируем сообщение
    $formatted = format_comment($realmsg);

    // Собираем HTML предпросмотра
    $ret = <<<HTML
<span id="preview" style="display: block;">
  <fieldset id="preview" style="border: 2px solid gray; min-width: 95%; display: block;">
    <legend>Предпросмотр 
      <a href="#" style="font-weight: normal;" onclick="this.style.display='none'; document.getElementById('preview').innerHTML=''; return false;">[свернуть]</a>
    </legend>
    <table class="bottom" width="100%">
      <tr>
        <td style="border: none;">
          <font color="black">{$formatted}</font>
        </td>
      </tr>
    </table>
  </fieldset>
</span>
HTML;

    // Завершаем скрипт, отдав HTML предпросмотра
    die($ret);
}
