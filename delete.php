<?
require_once("include/bittorrent.php");


function bark($msg) {
  stdhead($tracker_lang['error']);
  stdmsg($tracker_lang['error'], $msg);
  stdfoot();
  exit;
}


dbconn();

loggedinorreturn();

if (!mkglobal("id"))
	bark("Нехватает данных");

$id = 0 + $id;
if (!$id)
	die();
if (!is_valid_id($_POST["id"])) 			
stderr($tracker_lang['error'], $tracker_lang['invalid_id']);

$id = 0 + $_POST["id"];


$res = sql_query("SELECT name,owner,seeders,image1,image2,image3,image4,image5,tags FROM torrents WHERE id = $id");
$row = mysql_fetch_array($res);
if (!$row)
	stderr($tracker_lang['error'],"Такого торрента не существует.");

if ($CURUSER["id"] != $row["owner"] && get_user_class() < UC_MODERATOR)
	bark("Вы не владелец! Как такое могло произойти?\n");


$tags = explode(",", $row["tags"]);

foreach ($tags as $tag) {
		@sql_query('UPDATE tags SET howmuch=howmuch-1 WHERE name LIKE CONCAT(\'%\', '.sqlesc($tag).', \'%\')') or sqlerr(__FILE__, __LINE__);
	}

$rt = (int) $_POST["reasontype"];

if ( $rt < 1 || $rt > 5)
	bark("Неверная причина $rt.");

$reason = $_POST["reason"];

if ($rt == 1)
	$reasonstr = "Мертвый: 0 раздающих, 0 качающих = 0 пиров";
elseif ($rt == 2)
	$reasonstr = "Двойник" . ($reason[0] ? (": " . trim($reason[0])) : "!");
elseif ($rt == 3)
	$reasonstr = "Nuked" . ($reason[1] ? (": " . trim($reason[1])) : "!");
elseif ($rt == 4)
{
	if (!$reason[2])
		bark("Вы не написали пукт правил, которые этот торрент нарушил.");
  $reasonstr = "Нарушение правил: " . trim($reason[2]);
}
else
{
	if (!$reason[3])
		bark("Вы не написали причину, почему удаляете торрент.");
  $reasonstr = trim($reason[3]);
}

deletetorrent($id);

write_log("Торрент $id ($row[name]) был удален пользователем $CURUSER[username] ($reasonstr)\n","F25B61","torrent");

stdhead("Торрент удален!");

if (isset($_POST["returnto"]))
	$ret = "<a href=\"" . htmlspecialchars($_POST["returnto"]) . "\">Назад</a>";
else
	$ret = "<a href=\"$DEFAULTBASEURL/\">На главную</a>";

?>
<h2>Торрент удален!</h2>
<p><?= $ret ?></p>
<?

stdfoot();

?>