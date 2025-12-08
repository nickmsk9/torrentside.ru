<table align="center" class="main" border="1" cellspacing="0" cellpadding="5">
  <tr>
    <td class="colhead">Написание</td>
    <td class="colhead">Смайл</td>
  </tr>
  {foreach from=$smilies key=code item=url}
    <tr>
      <td>{$code|escape}</td>
      <td><img src="{$baseurl|escape}/pic/smilies/{$url|escape}" alt="" /></td>
    </tr>
  {/foreach}
</table>
