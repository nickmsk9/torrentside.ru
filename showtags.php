<?

require_once("include/bittorrent.php");
dbconn();

header ("Content-Type: text/html; charset=" . $tracker_lang['language_charset']);

$category = (int) $_POST["cat"];

if (empty($category)) {
	stdmsg($tracker_lang["error"], "Не пытайся меня взломать!");
}

$res = sql_query("SELECT name FROM categories WHERE id=".sqlesc($category));
$row = mysql_fetch_array($res);

$r = "<span id=\"ss".$category."\" style=\"display: block;\"><fieldset id='tags' style='border: 2px solid gray;min-width:95%;display: block;'><legend style='color:#555;'> Теги для категории \"".$row["name"]."\"&nbsp;&nbsp;&nbsp;<a href=\"#\" style=\"font-weight:normal\" onClick=\"javascript:this.style.display='none';document.getElementById('ss".$category."').innerHTML='';\">[свернуть]</a></legend><table cellpadding=\"5\" class=\"bottom\"><tr>";
        $tags = taggenrelist($category);
        if (!$tags)
        $r .= "<font style=\"font-size:8pt;color:#555555;padding-left:2px;\">Нет тегов в выбранной категории</font>";
        else {
        $j = 0;
        foreach ($tags as $row)
            {
            $tagsperrow = 7;
            $r .= ($j && $j % $tagsperrow == 0) ? "</tr><tr>" : "";
    	    $r .= "<td class=\"bottom\" style=\"padding-bottom: 2px;padding-left: 2px\"><a style=\"font-weight: normal;\" href=\"browse.php?tag=".$row["name"]."&incldead=1&cat=$category\">".htmlspecialchars($row["name"])."</a><font size=\"1\" color=\"#808080\">&nbsp;(".$row["howmuch"].")</font></td>";
            $j++;
            }
        }
$r .= "</tr></table></fieldset></span>";

echo $r;

?>