<?php

require "include/bittorrent.php";
dbconn(false);
loggedinorreturn();

if (get_user_class() < UC_SYSOP)
    stderr($tracker_lang['error'], $tracker_lang['access_denied']);

$body = $body ?? '';
$receiver = $receiver ?? 0;

stdhead("Общее сообщение", false);
begin_frame("Отправка общего сообщения");
?>

<form method="post" name="message" action="takestaffmess.php">

<?php if (!empty($_GET["returnto"]) || !empty($_SERVER["HTTP_REFERER"])): ?>
    <input type="hidden" name="returnto" value="<?= htmlspecialchars($_GET["returnto"] ?? $_SERVER["HTTP_REFERER"]) ?>">
<?php endif; ?>

<table cellspacing="0" cellpadding="5" width="100%">
    <tr>
        <td class="colhead" colspan="2">Общее сообщение всем членам администрации и пользователям</td>
    </tr>
    <tr>
        <td>Кому отправлять:<br>
            <table style="border: 0" width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td width="20"><input type="checkbox" name="clases[]" value="<?= UC_USER ?>"></td>
                    <td><?= get_user_class_name(UC_USER) ?></td>

                    <td width="20"><input type="checkbox" name="clases[]" value="<?= UC_POWER_USER ?>"></td>
                    <td><?= get_user_class_name(UC_POWER_USER) ?></td>

                    <td width="20"><input type="checkbox" name="clases[]" value="<?= UC_VIP ?>"></td>
                    <td><?= get_user_class_name(UC_VIP) ?></td>

                    <td width="20"><input type="checkbox" name="clases[]" value="<?= UC_UPLOADER ?>"></td>
                    <td><?= get_user_class_name(UC_UPLOADER) ?></td>
                </tr>
                <tr>
                    <td width="20"><input type="checkbox" name="clases[]" value="<?= UC_MODERATOR ?>"></td>
                    <td><?= get_user_class_name(UC_MODERATOR) ?></td>

                    <td width="20"><input type="checkbox" name="clases[]" value="<?= UC_ADMINISTRATOR ?>"></td>
                    <td><?= get_user_class_name(UC_ADMINISTRATOR) ?></td>

                    <td width="20"><input type="checkbox" name="clases[]" value="<?= UC_SYSOP ?>"></td>
                    <td><?= get_user_class_name(UC_SYSOP) ?></td>

                    <td>&nbsp;</td><td>&nbsp;</td>
                </tr>
            </table>
        </td>
    </tr>

    <tr>
        <td colspan="2">Тема:
            <input name="subject" type="text" size="70">
        </td>
    </tr>

    <tr>
        <td align="center" colspan="2">
            <?php textbbcode("message", "msg", htmlspecialchars($body ?? ""), 0); ?>
        </td>
    </tr>

    <tr>
        <td colspan="2" align="center">
            <b>Отправитель:&nbsp;</b>
            <?= $CURUSER['username'] ?>
            <input name="sender" type="radio" value="self" checked> &nbsp;
            Система <input name="sender" type="radio" value="system">
        </td>
    </tr>

    <tr>
        <td colspan="2" align="center">
            <input type="submit" value="Отправить" class="btn">
        </td>
    </tr>
</table>

<input type="hidden" name="receiver" value="<?= (int)$receiver ?>">

</form>

<?php
end_frame();
stdfoot();
