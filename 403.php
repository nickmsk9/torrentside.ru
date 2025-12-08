<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<HTML>
<HEAD>
   <style>
   BODY {align:center; font-family: Tahoma, HElvetica, Verdana, Arial, sans-serif }
   a {color: #000; font-size: 16pt;}   
   .errsrvErr { font-size: 20pt; font-weight: bold; color: #000 }
   .errsrvName {font-size: 15pt; font-weight: bold; color: #000}
   .descript {font-size: 16pt; color: red}
   </style>

<center><script language=javascript>
var tl = new Array(
"Вам отказано в доступе.",
"Возможные причины:",
"- Вы неоднакратно нарушили правила;",
"- Вы использовали накрутку рейтинга;",
"- Ваш IP был внесен в черный список;",
"- Вы из сети Волгателеком.",
"- Все разборы в ICQ 297065.",
""
);

var speed = 30;
var index = 0; text_pos = 0;
var str_length = tl[0].length;
var contents, row;

function type_text()
{
    contents = '';
    row = Math.max(0, index-7);
    while (row<index) contents += tl[row++] + '<br />';
    
    document.getElementById('err_text').innerHTML = contents + tl[index].substring(0,text_pos) + "_";
    if (text_pos ++== str_length)
    {
        text_pos = 0;
        index++;
        if (index != tl.length)
        {
            str_length = tl[index].length;
            setTimeout("type_text()", 1500);
        }
    } else
    setTimeout("type_text()", speed);
}
</script>
</HEAD>

<BODY>
</SCRIPT></td>
<center>
   <td align="center" width="760">
      <table align="center" height="220" width="100%" border="0"><tr>
         <td width="5%"><img src="/pic/affiliate-login.jpg" height="128" width="128" alt="" style="margin:0 1em 1em 0"/></td>
         <td valign="center" valign="center" width="100%">
            <div class="descript" id="err_text"></div>
            <script>type_text();</script>
         </td>


</tr>
</table>
</BODY>
</HTML>