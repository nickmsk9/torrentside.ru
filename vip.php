<?


require "include/bittorrent.php";
dbconn();
loggedinorreturn();
stdhead("VIP");

begin_frame("VIP");
?>
<table>
<td align="center" width="100%" style='border: none'>
<center><br /><b><font size=2>Вся денежная помощь идет на оплату сервера, поэтому мы рады любым вашим пожертвованиям...</font><p>
<font size=2><font color=DarkOrange>Пожертвовавший определённую сумму на помощь нашему сайту получает
статус <br /><font color=gold>VIP</font> и золотую звезду донора рядом со своим ником!</font></font></b><p>

<hr>
<b><font size=2>Какие преимущества имеют статус <font color=gold>VIP</font>:</font><br />
• У Вас нет ограничения на количество одновременно скачиваемых торрентов<br>
• Для статуса <font color=gold>VIP</font> полностью не учитывается скачанный трафик.<br>
• Вы можете делать свои раздачи</br>
• Для Вас обеспечена специальная тех.поддержка на трекере</br>
• У вас есть спец.привилегии и возможности на трекере</br>
• По вашему заказу на трекере разрабатываются новые функции и возможности</br><br>
<font color=gold>VIP</font> - <font color="#FF0000">200 Руб.</font> <br>
<br>

<font color="#008000">
<u>Финансовая поддержка возможна с помощью сервисов WebMoney</u>
</font>
<br>
<b>- цены указаны в рублях: не путайте, пожалуйста!<br>
- после того, как Вы сделаете перевод, обязательно свяжитесь с администрацией и сообщите cумму перевода и от кого он.</b><br /> <br />
         <br>
<a href=\"http://www.webmoney.ru/\" title=\"WebMoney\" target=\"_blank\"><img src="/pic/logo_wm.gif" alt=\"WebMoney\" longdesc=\"http://www.webmoney.ru/\" border=\"0\"></a>
<br>
<hr>
<table width=\"100%\" cellspacing=3 cellpadding=2><tr><td class=embedded  align=center><table width=\"33%\" class=embedded cellspacing=3 cellpadding=3 align=center><tr><td style='border: none'><b><center>WMR</center></b></td><td style='border: none'><center>R244037800228</center></td></tr></table></td></tr></table>
</td>
</table>
<?
end_frame();

stdfoot();