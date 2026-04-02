<?php

require_once "include/bittorrent.php";
dbconn(false);

global $smilies;
$items = tracker_smiley_picker_items((array)$smilies);
$form = htmlspecialchars((string)($_GET['form'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$text = htmlspecialchars((string)($_GET['text'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

header('Content-Type: text/html; charset=utf-8');
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Смайлики</title>
    <script type="text/javascript">
        function SmileIT(smile, form, text) {
            if (!window.opener || !window.opener.document || !window.opener.document.forms[form]) {
                return;
            }
            let f = window.opener.document.forms[form];
            f.elements[text].value += " " + smile + " ";
            f.elements[text].focus();
        }
    </script>
    <style>
        body{margin:0;padding:16px;background:#f4f7fb;color:#223046;font:14px/1.45 Tahoma,Verdana,sans-serif}
        h2{margin:0 0 14px;text-align:center;color:#31455f}
        .emoji-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(92px,1fr));gap:10px}
        .emoji-btn{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;min-height:92px;padding:12px;border:1px solid #d7e1eb;border-radius:16px;background:#fff;text-decoration:none;color:inherit;box-shadow:0 6px 18px rgba(34,48,70,.06)}
        .emoji-btn:hover{border-color:#8ab3d1;transform:translateY(-1px)}
        .emoji-code{font-size:12px;color:#60758a;text-align:center;word-break:break-word}
        .smiley-emoji--popup{font-size:2rem}
        .footer-actions{margin-top:14px;text-align:center}
        .footer-actions a{display:inline-flex;align-items:center;justify-content:center;padding:10px 14px;border-radius:999px;background:#223c57;color:#fff;text-decoration:none}
    </style>
</head>
<body>

<h2 align="center">Смайлики</h2>

<div class="emoji-grid">
<?php foreach ($items as $item): ?>
    <?php $codeSafe = str_replace("'", "\\'", (string)$item['code']); ?>
    <a class="emoji-btn" href="javascript: SmileIT('<?= $codeSafe ?>','<?= $form ?>','<?= $text ?>')">
        <?= tracker_smiley_html((string)$item['code'], (string)$item['file'], ['class' => 'smiley-emoji--popup', 'title' => (string)$item['code']]) ?>
        <span class="emoji-code"><?= htmlspecialchars((string)$item['code'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
    </a>
<?php endforeach; ?>
</div>

<div class="footer-actions">
    <a href="javascript: window.close()">Закрыть окно</a>
</div>

</body>
</html>
