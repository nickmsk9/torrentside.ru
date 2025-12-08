<?php

$op = (!isset($_REQUEST['op'])) ? "Main" : $_REQUEST['op'];


foreach ($_GET as $key => $value)
	$GLOBALS[$key] = $value;
foreach ($_POST as $key => $value)
	$GLOBALS[$key] = $value;
foreach ($_COOKIE as $key => $value)
	$GLOBALS[$key] = $value;

require_once($rootpath . 'ad_min/functions.php');

?>