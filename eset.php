<?php
Header("Content-type: image/png");
$pic = ImageCreateFromPng("nod4-logo.png");
imagesavealpha($pic, true);
$trans_colour = imagecolorallocatealpha($pic, 0, 0, 0, 127);
imagefill($pic, 0, 0, $trans_colour);
     
$color=ImageColorAllocate($pic, 0, 0, 0);
$font = 'arial.ttf';
$nod4='NOD32, ESS 3.0/4.0';
$grab=@file_get_contents("eset_upd/update.ver");
$grab = ereg_replace('(.*)ENGINE2]','', $grab); 
if ($grab!="") {
  eregi("version=([0-9\.]+)",$grab,$ver);
  eregi("\(([0-9]{4})([0-9]{2})([0-9]{2})\)",$grab,$data);
$dim[1] = "января";
$dim[2] = "февраля";
$dim[3] = "марта";
$dim[4] = "апреля";
$dim[5] = "мая";
$dim[6] = "июня";
$dim[7] = "июля";
$dim[8] = "августа";
$dim[9] = "сентября";
$dim[10] = "октября";
$dim[11] = "ноября";
$dim[12] = "декабря";
$i = $data[2] - 0;
$month = $dim[$i];
$version = $ver[1];
$day = $data[3] - $data[3] + $data[3];
$year = $data[1];
$nod=$version." (".$day." ".$month." ".$year." г.)";
}
else {
  $nod="Нет связи.";
}
ImageTTFtext($pic, 8, 0, 35, 13, $color, $font, $nod4);
ImageTTFtext($pic, 8, 0, 35, 25, $color, $font, $nod);
ImagePng($pic);
ImageDestroy($pic);

?>

