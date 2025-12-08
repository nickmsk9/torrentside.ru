<?
require_once('../include/bittorrent.php'); 
// Database Presets
$config_path = "../include/secrets.php";

$template_file = "./template.png";

$rating_x = 66;
$rating_y = 10;

$upload_x = 60;
$upload_y = 43;

$download_x = 60;
$download_y = 26;

$digits_template = "./digits.png";
$digits_config = "./digits.ini";

//===========================================================================
// Funtions
//===========================================================================

function getParam() {
    $id = $_GET['id'] ?? null;

    if ($id !== null && is_numeric($id)) {
        return (int)$id;
    }

    if (!empty($_SERVER['PATH_INFO'])) {
        $res = basename($_SERVER['PATH_INFO'], ".png");
        if (is_numeric($res)) {
            return (int)$res;
        }
    }

    die("Invalid user_id or hacking attempt.");
}



function mysqli_init_db() {
    global $mysqli_host, $mysqli_user, $mysqli_pass, $mysqli_db;

    $mysqli = new mysqli($mysqli_host, $mysqli_user, $mysqli_pass, $mysqli_db);

    if ($mysqli->connect_error) {
        die("Ошибка подключения к базе данных: " . $mysqli->connect_error);
    }

    return $mysqli;
}


function ifthen($ifcondition, $iftrue, $iffalse) {
	if ($ifcondition) {
		return $iftrue;
	} else {
		return $iffalse;
	}
}

function getPostfix($val) {
	$postfix = "b";
	if ($val>=1024)             { $postfix = "kb"; }
	if ($val>=1048576)          { $postfix = "mb"; }
	if ($val>=1073741824)       { $postfix = "gb"; }
	if ($val>=1099511627776)    { $postfix = "tb"; }
	if ($val>=1125899906842624) { $postfix = "pb"; }
	if ($val>=1152921504606846976)       { $postfix = "eb"; }
	if ($val>=1180591620717411303424)    { $postfix = "zb"; }
	if ($val>=1208925819614629174706176) { $postfix = "yb"; }
	
	return $postfix;
}

function roundCounter($value, $postfix) {
	$val=$value;
	switch ($postfix) {
	case "kb": $val=$val / 1024;
		break;
	case "mb": $val=$val / 1048576;
		break;
	case "gb": $val=$val / 1073741824;
		break;
	case "tb": $val=$val / 1099511627776;
		break;
	case "pb": $val=$val / 1125899906842624;
		break;
	case "eb": $val=$val / 1152921504606846976;
		break;
	case "zb": $val=$val / 1180591620717411303424;
		break;
	case "yb": $val=$val / 1208925819614629174706176;
		break;
		
	default:
		break;
	}
	return $val;
}

//===========================================================================
// Main body
//===========================================================================

// Digits initialization - begin
$digits_ini = @parse_ini_file($digits_config) or die("Cannot load Digits Configuration file!");
$digits_img = @imagecreatefrompng($digits_template) or die("Cannot Initialize new GD image stream!");
// Digits initialization - end

$download_counter = 0;
$upload_counter = 0;
$rating_counter = 0;

$img = @imagecreatefrompng($template_file) or die ("Cannot Initialize new GD image stream!");

$userid = getParam();
if ($userid != "") {
    include($config_path);
    $mysqli = mysqli_init_db(); // получаем подключение

    $stmt = $mysqli->prepare("SELECT uploaded, downloaded FROM users WHERE id = ?");
    $stmt->bind_param("i", $userid);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->bind_result($uploaded, $downloaded);
        $stmt->fetch();

        $upload_counter = $uploaded;
        $download_counter = $downloaded;

        if ($download_counter > 0) {
            $rating_counter = $upload_counter / $download_counter;
        }
    }

    $stmt->close();
}


$dot_pos = strpos((string) $rating_counter, ".");
if ($dot_pos>0) {
    $rating_counter = (string) round(substr((string) $rating_counter, 0, $dot_pos+1+2), 2);
} else {
	$rating_counter = (string) $rating_counter;
}
$counter_x = $rating_x;
for ($i=0; $i<strlen($rating_counter); $i++) {
	$d_x=$digits_ini[ifthen($rating_counter[$i]==".", "dot", $rating_counter[$i])."_x"];
	$d_w=$digits_ini[ifthen($rating_counter[$i]==".", "dot", $rating_counter[$i])."_w"];
	imagecopy($img, $digits_img, $counter_x, $rating_y, $d_x, 0, $d_w, imagesy($digits_img));
	$counter_x=$counter_x+$d_w-1;
}


$postfix = getPostfix($upload_counter);
$upload_counter = roundCounter($upload_counter, $postfix);
$dot_pos = strpos((string) $upload_counter, ".");
if ($dot_pos>0) {
    $upload_counter = (string) round(substr((string) $upload_counter, 0, $dot_pos+1+2), 2);
} else {
	$upload_counter = (string) $upload_counter;
}
$counter_x = $upload_x;
for ($i=0; $i<strlen($upload_counter); $i++) {
	$d_x=$digits_ini[ifthen($upload_counter[$i]==".", "dot", $upload_counter[$i])."_x"];
	$d_w=$digits_ini[ifthen($upload_counter[$i]==".", "dot", $upload_counter[$i])."_w"];
	imagecopy($img, $digits_img, $counter_x, $upload_y, $d_x, 0, $d_w, imagesy($digits_img));
	$counter_x=$counter_x+$d_w-1;
}
$counter_x+=3;
$d_x=$digits_ini[$postfix."_x"];
$d_w=$digits_ini[$postfix."_w"];
imagecopy($img, $digits_img, $counter_x, $upload_y, $d_x, 0, $d_w, imagesy($digits_img));


$postfix = getPostfix($download_counter);
$download_counter = roundCounter($download_counter, $postfix);
$dot_pos = strpos((string) $download_counter, ".");
if ($dot_pos>0) {
    $download_counter = (string) round(substr((string) $download_counter, 0, $dot_pos+1+2), 2);
} else {
	$download_counter = (string) $download_counter;
}
$counter_x = $download_x;
for ($i=0; $i<strlen($download_counter); $i++) {
	$d_x=$digits_ini[ifthen($download_counter[$i]==".", "dot", $download_counter[$i])."_x"];
	$d_w=$digits_ini[ifthen($download_counter[$i]==".", "dot", $download_counter[$i])."_w"];
	imagecopy($img, $digits_img, $counter_x, $download_y, $d_x, 0, $d_w, imagesy($digits_img));
	$counter_x=$counter_x+$d_w-1;
}
$counter_x+=3;
$d_x=$digits_ini[$postfix."_x"];
$d_w=$digits_ini[$postfix."_w"];
imagecopy($img, $digits_img, $counter_x, $download_y, $d_x, 0, $d_w, imagesy($digits_img));
header("Content-type: image/png");
imagepng($img);
imagedestroy($img);
?>