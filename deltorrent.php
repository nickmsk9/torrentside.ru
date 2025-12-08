<?
require "include/bittorrent.php";

gzip();

dbconn();

loggedinorreturn();

if (get_user_class() < UC_MODERATOR)
	stderr($tracker_lang["error"], $tracker_lang["access_denied"]);

stdhead("Удалить торрент");
begin_main_frame();

$mode = $_GET["mode"];

if ($mode == "delete") {
	$res = sql_query("SELECT id, name FROM torrents WHERE id IN (" . implode(", ", array_map("sqlesc", $_POST["delete"])) . ")");
	echo "Следующие торренты удалены:<br><br>";
	while ($row = mysql_fetch_array($res)) {
		echo "ID: $row[id] - $row[name]<br>";
		$reasonstr = "Старый или не подходил под правила.";
		$text = "Торрент $row[id] ($row[name]) был удален пользователем $CURUSER[username]. Причина: $reasonstr\n";
		write_log($text);
	}
	sql_query("DELETE FROM torrents WHERE id IN (" . implode(", ", array_map("sqlesc", $_POST["delete"])) . ")") or sqlerr(__FILE__,__LINE__);
	sql_query("DELETE FROM snatched WHERE torrent IN (" . implode(", ", array_map("sqlesc", $_POST["delete"])) . ")") or sqlerr(__FILE__,__LINE__);	
	sql_query("DELETE FROM ratings WHERE torrent IN (" . implode(", ", array_map("sqlesc", $_POST["delete"])) . ")") or sqlerr(__FILE__,__LINE__);
	sql_query("DELETE FROM checkcomm WHERE checkid IN (" . implode(", ", array_map("sqlesc", $_POST["delete"])) . ") AND torrent = 1") or sqlerr(__FILE__,__LINE__);
	sql_query("DELETE FROM files WHERE torrent IN (" . implode(", ", array_map("sqlesc", $_POST["delete"])) . ")") or sqlerr(__FILE__,__LINE__);
} else
	echo "Unknown mode...";

end_main_frame();
stdfoot();

?>