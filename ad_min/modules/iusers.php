<?php
if (!defined("ADMIN_FILE")) die("Illegal File Access");

function iUsers() {
    global $admin_file;
    
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        // Получаем данные из POST
        $iname = isset($_POST['iname']) ? $_POST['iname'] : '';
        $ipass = isset($_POST['ipass']) ? $_POST['ipass'] : '';
        $imail = isset($_POST['imail']) ? $_POST['imail'] : '';
        
        $updateset = array();
        if (!empty($ipass)) {
            $secret = mksecret();
            $hash = md5($secret.$ipass.$secret);
            $updateset[] = "secret = ".sqlesc($secret);
            $updateset[] = "passhash = ".sqlesc($hash);
        }
        if (!empty($imail) && validemail($imail))
            $updateset[] = "email = ".sqlesc($imail);
        
        if (!empty($iname) && count($updateset)) {
            $res = sql_query("UPDATE users SET ".implode(", ", $updateset)." WHERE username = ".sqlesc($iname)) or sqlerr(__FILE__,__LINE__);
            if (mysqli_modified_rows() < 1)
                stdmsg("Ошибка", "Смена пароля завершилась неудачей! Возможно указано несуществующее имя пользователя.", "error");
            else
                stdmsg("Изменения пользователя прошло успешно", "Имя пользователя: ".$iname.(!empty($ipass) ? "<br />Новый пароль: ".$ipass : "").(!empty($imail) ? "<br />Новая почта: ".$imail : ""));
        } else {
            stdmsg("Ошибка", "Не указано имя пользователя или не заполнены поля для изменения.", "error");
        }
    } else {
        echo "<form method=\"post\" action=\"".$admin_file.".php?op=iusers\">"
        ."<table border=\"0\" cellspacing=\"0\" cellpadding=\"3\">"
        ."<tr><td class=\"colhead\" colspan=\"2\">Смена пароля</td></tr>"
        ."<tr>"
        ."<td><b>Пользователь</b></td>"
        ."<td><input name=\"iname\" type=\"text\"></td>"
        ."</tr>"
        ."<tr>"
        ."<td><b>Новый пароль</b></td>"
        ."<td><input name=\"ipass\" type=\"password\"></td>"
        ."</tr>"
        ."<tr>"
        ."<td><b>Новая почта</b></td>"
        ."<td><input name=\"imail\" type=\"text\"></td>"
        ."</tr>"
        ."<tr><td colspan=\"2\" align=\"center\"><input type=\"submit\" name=\"isub\" value=\"Сделать\"></td></tr>"
        ."</table>"
        ."<input type=\"hidden\" name=\"op\" value=\"iusers\" />"
        ."</form>";
    }
}

switch ($op) {
    case "iUsers":
    case "iusers":
        iUsers();
        break;
}
?>
