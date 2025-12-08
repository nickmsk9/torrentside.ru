<?
require "include/bittorrent.php";
dbconn();
loggedinorreturn();

if (get_user_class() < UC_SYSOP) stderr($tracker_lang['error'],$tracker_lang['access_denied']);


if((array)$_POST["delmp"]) {
  foreach ($_POST['delmp'] as $delid)
  if (!is_valid_id($delid)) stderr($tracker_lang['error'],$tracker_lang['invalid_id']);
  
    $do = "DELETE FROM messages WHERE id IN (".implode(", ", $_POST[delmp]).")";
    $res=sql_query($do);
    header("Location: spam.php");
    } else {
    stdhead($tracker_lang['error']);
    print("<div class='error'><b>".$tracker_lang['not_chosen_message']."</b></div>");
    print("<center><INPUT TYPE='button' VALUE='".$tracker_lang['back']."' onClick=\"history.go(-1)\"></center>");
    stdfoot();
    die;
    }
?>