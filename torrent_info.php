<?php
require "include/bittorrent.php";
require_once "include/benc.php";

function print_array($array, $offset_symbol = "|--", $offset = "", $parent = "")
{
    if (!is_array($array)) {
        echo "[$array] is not an array!<br>";
        return;
    }

    switch ($array['type'] ?? '') {
        case "string":
            printf(
                "<li><div class=string> - <span class=icon>[STRING]</span> <span class=title>[%s]</span> <span class=length>(%d)</span>: <span class=value>%s</span></div></li>",
                htmlspecialchars((string)$parent),
                (int)($array['strlen'] ?? 0),
                htmlspecialchars((string)($array['value'] ?? ''))
            );
            break;

        case "integer":
            printf(
                "<li><div class=integer> - <span class=icon>[INT]</span> <span class=title>[%s]</span> <span class=length>(%d)</span>: <span class=value>%s</span></div></li>",
                htmlspecialchars((string)$parent),
                (int)($array['strlen'] ?? 0),
                htmlspecialchars((string)($array['value'] ?? ''))
            );
            break;

        case "list":
            printf(
                "<li><div class=list> + <span class=icon>[LIST]</span> <span class=title>[%s]</span> <span class=length>(%d)</span></div>",
                htmlspecialchars((string)$parent),
                (int)($array['strlen'] ?? 0)
            );
            echo "<ul>";
            print_array($array['value'] ?? [], $offset_symbol, $offset . $offset_symbol);
            echo "</ul></li>";
            break;

        case "dictionary":
            printf(
                "<li><div class=dictionary> + <span class=icon>[DICT]</span> <span class=title>[%s]</span> <span class=length>(%d)</span></div>",
                htmlspecialchars((string)$parent),
                (int)($array['strlen'] ?? 0)
            );
            foreach ($array as $key => $val) {
                if (is_array($val)) {
                    echo "<ul>";
                    print_array($val, $offset_symbol, $offset . $offset_symbol, (string)$key);
                    echo "</ul>";
                }
            }
            echo "</li>";
            break;

        default:
            foreach ($array as $key => $val) {
                if (is_array($val)) {
                    print_array($val, $offset_symbol, $offset, (string)$key);
                }
            }
            break;
    }
}

dbconn(false);
loggedinorreturn();

$id = (int)($_GET["id"] ?? 0);
if ($id <= 0) httperr();

$res = sql_query("SELECT name FROM torrents WHERE id = " . sqlesc($id)) or sqlerr(__FILE__, __LINE__);
// БЫЛО: $row = mysql_fetch_assoc($res);
$row = mysqli_fetch_assoc($res);

global $torrent_dir;
$fn = rtrim($torrent_dir, "/") . "/$id.torrent";

if (!$row || !is_file($fn) || !is_readable($fn)) httperr();

// Standard html headers
stdhead("Данные о торенте");
?>

<style type="text/css">
/* list styles */
ul ul { margin-left: 15px; }
ul, li { padding: 0; margin: 0; list-style-type: none; color: #000; font-weight: normal; }
ul a, li a { color: #009; text-decoration: none; font-weight: normal; }
li { display: inline; } /* fix for IE blank line bug */
ul > li { display: list-item; }

li div.string  {padding: 3px;}
li div.integer {padding: 3px;}
li div.dictionary {padding: 3px;}
li div.list {padding: 3px;}
li div.string span.icon {color:#090;padding: 2px;}
li div.integer span.icon {color:#990;padding: 2px;}
li div.dictionary span.icon {color:#909;padding: 2px;}
li div.list span.icon {color:#009;padding: 2px;}

li span.title {font-weight: bold;}
</style>

<?php
begin_main_frame();
begin_frame("Данные о торенте");

$dict = bdec_file($fn, (1024*1024));

// Start table
print("<table width='100%' border='1' cellspacing='0' cellpadding='5'>");
print("<tr><td class='colhead' colspan='1'>Данные о торенте " . htmlspecialchars($row['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</td></tr>\n");
print("<tr><td>");

// сократим pieces для читаемости
if (isset($dict['value']['info']['value']['pieces']['value'])) {
    $dict['value']['info']['value']['pieces']['value'] =
        "0x" . bin2hex(substr($dict['value']['info']['value']['pieces']['value'], 0, 25)) . "...";
}

echo "<ul id='colapse'>";
print_array($dict, "*", "", "root");
echo "</ul>";

// End table
print("</td></tr></table>");
end_frame();
end_main_frame();
stdfoot();
