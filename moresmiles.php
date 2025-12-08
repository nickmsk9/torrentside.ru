<?php

require_once "include/bittorrent.php";
dbconn(false);
loggedinorreturn();

global $smilies;

$ss_uri = '';
if ($CURUSER && isset($CURUSER["stylesheet"])) {
    $ss_a = mysqli_fetch_assoc(sql_query("SELECT uri FROM stylesheets WHERE id=" . (int)$CURUSER["stylesheet"]));
    if ($ss_a && !empty($ss_a["uri"])) {
        $ss_uri = $ss_a["uri"];
    }
}
if (!$ss_uri) {
    $ss_uri = $default_theme ?? 'default';
}

header('Content-Type: text/html; charset=utf-8');
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Смайлики</title>
    <link rel="stylesheet" href="./themes/<?= htmlspecialchars($ss_uri) . "/" . htmlspecialchars($ss_uri) ?>.css" type="text/css">
    <script type="text/javascript">
        function SmileIT(smile, form, text) {
            let f = window.opener.document.forms[form];
            f.elements[text].value += " " + smile + " ";
            f.elements[text].focus();
        }
    </script>
</head>
<body>

<h2 align="center">Смайлики</h2>

<table width="100%" border="1" cellspacing="2" cellpadding="2">
    <?php
    $count = 0;
    foreach ($smilies as $code => $url) {
        if ($count % 3 == 0) echo "<tr>";

        $code_safe = str_replace("'", "\\'", $code);
        $form = htmlspecialchars($_GET['form'] ?? '');
        $text = htmlspecialchars($_GET['text'] ?? '');

        echo "<td align=\"center\">";
        echo "<a href=\"javascript: SmileIT('{$code_safe}','{$form}','{$text}')\">";
        echo "<img border=\"0\" src=\"pic/smilies/" . htmlspecialchars($url) . "\" alt=\"" . htmlspecialchars($code) . "\"></a>";
        echo "</td>";

        $count++;
        if ($count % 3 == 0) echo "</tr>";
    }

    // Если не кратно 3 — закрываем строку
    if ($count % 3 !== 0) {
        while ($count % 3 !== 0) {
            echo "<td></td>";
            $count++;
        }
        echo "</tr>";
    }
    ?>
</table>

<div align="center" style="margin-top:10px;">
    <a class="altlink_green" href="javascript: window.close()">Закрыть окно</a>
</div>

</body>
</html>
