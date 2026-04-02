<table align="center" class="main" border="1" cellspacing="0" cellpadding="5">
  <tr>
    <td class="colhead">Написание</td>
    <td class="colhead">Смайл</td>
  </tr>
  {foreach from=$smileyRows item=row}
    <tr>
      <td>{$row.code|escape}</td>
      <td>{$row.emoji_html nofilter}</td>
    </tr>
  {/foreach}
</table>
