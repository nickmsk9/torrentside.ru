<?php

require_once 'include/bittorrent.php';
require_once 'classes/rating.class.php';
dbconn();

global $CURUSER, $mysqli;

header("Content-Type: text/html; charset=" . $tracker_lang['language_charset']);

if ($_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {

    $rating = isset($_REQUEST['rating']) ? (int)$_REQUEST['rating'] : 0;
    $torrentid = isset($_REQUEST['tid']) && is_numeric($_REQUEST['tid']) ? (int)$_REQUEST['tid'] : 0;
    $do = isset($_REQUEST['do']) ? strip_tags($_REQUEST['do']) : '';

    switch ($do) {
        case 'rate':
    if ($rating > 0 && $rating <= 5 && $torrentid > 0 && $CURUSER) {
        $oRating = new rating($torrentid);

        if ($oRating->AllowedToRate()) {
            $uid = (int)$CURUSER['id'];
            $now = sqlesc(date('Y-m-d H:i:s'));

            sql_query("UPDATE torrents SET numratings = numratings + 1, ratingsum = ratingsum + $rating WHERE id = $torrentid") or sqlerr(__FILE__, __LINE__);
            sql_query("INSERT INTO ratings (`torrent`, `user`, `rating`, `added`) VALUES ($torrentid, $uid, $rating, $now)") or sqlerr(__FILE__, __LINE__);

            if (mysqli_errno($mysqli) === 0) {
                echo $oRating->getRatingBar();
            }
        }
    }
    break;


       case 'delete':
    if ($torrentid > 0 && $CURUSER) {
        $oRating = new rating($torrentid);
        $r = $oRating->getUserVote();

        if ($r > 0) {
            $uid = (int)$CURUSER['id'];
            sql_query("DELETE FROM ratings WHERE torrent = $torrentid AND user = $uid") or sqlerr(__FILE__, __LINE__);

            // Безопасное уменьшение
            sql_query("
                UPDATE torrents 
                SET 
                    numratings = IF(numratings > 0, numratings - 1, 0), 
                    ratingsum = IF(ratingsum >= $r, ratingsum - $r, 0) 
                WHERE id = $torrentid
            ") or sqlerr(__FILE__, __LINE__);

            if (mysqli_errno($mysqli) === 0) {
                echo $oRating->getRatingBar();
            }
        }
    }
    break;

    }
}
?>
