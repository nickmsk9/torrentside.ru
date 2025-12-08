<?php
/* Smarty version 5.5.1, created on 2025-10-03 12:17:16
  from 'file:index/stats.tpl' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.5.1',
  'unifunc' => 'content_68df949c3721c7_91401423',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    'b0619a97eacc6f56a87142e6e77900f8bbeff606' => 
    array (
      0 => 'index/stats.tpl',
      1 => 1759483002,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_68df949c3721c7_91401423 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = 'C:\\OSPanel\\domains\\torrentside.ru\\templates\\index';
?><div class="panel widget pd20 clearfix">
  <div class="h1">Статистика</div>

  <div class="table-wrap">
    <table class="main w100" cellpadding="6" cellspacing="0" border="0">
      <tbody>
        <tr>
          <td class="lol" align="left"><b>Мест на трекере</b></td>
          <td class="lol" align="right"><?php echo htmlspecialchars((string) ((($tmp = $_smarty_tpl->getValue('maxusers') ?? null)===null||$tmp==='' ? 0 ?? null : $tmp)), ENT_QUOTES, 'UTF-8');?>
</td>
        </tr>
        <tr>
          <td class="lol" align="left"><b><?php echo htmlspecialchars((string) ((($tmp = $_smarty_tpl->getValue('lang')['users_registered'] ?? null)===null||$tmp==='' ? "Пользователей" ?? null : $tmp)), ENT_QUOTES, 'UTF-8');?>
</b></td>
          <td class="lol" align="right" class="b"><?php echo htmlspecialchars((string) ((($tmp = $_smarty_tpl->getValue('stats')['registered_fmt'] ?? null)===null||$tmp==='' ? "0" ?? null : $tmp)), ENT_QUOTES, 'UTF-8');?>
</td>
        </tr>
        <tr>
          <td class="lol" align="left"><b>Парней</b></td>
          <td class="lol" align="right"><?php echo htmlspecialchars((string) ((($tmp = $_smarty_tpl->getValue('stats')['male_fmt'] ?? null)===null||$tmp==='' ? "0" ?? null : $tmp)), ENT_QUOTES, 'UTF-8');?>
</td>
        </tr>
        <tr>
          <td class="lol" align="left"><b>Девушек</b></td>
          <td class="lol" align="right"><?php echo htmlspecialchars((string) ((($tmp = $_smarty_tpl->getValue('stats')['female_fmt'] ?? null)===null||$tmp==='' ? "0" ?? null : $tmp)), ENT_QUOTES, 'UTF-8');?>
</td>
        </tr>
        <tr>
          <td class="lol" align="left"><b><?php echo htmlspecialchars((string) ((($tmp = $_smarty_tpl->getValue('lang')['tracker_torrents'] ?? null)===null||$tmp==='' ? "Торренты" ?? null : $tmp)), ENT_QUOTES, 'UTF-8');?>
</b></td>
          <td class="lol" align="right" class="a"><?php echo htmlspecialchars((string) ((($tmp = $_smarty_tpl->getValue('stats')['torrents_fmt'] ?? null)===null||$tmp==='' ? "0" ?? null : $tmp)), ENT_QUOTES, 'UTF-8');?>
</td>
        </tr>
        <tr>
          <td class="lol" align="left"><b><?php echo htmlspecialchars((string) ((($tmp = $_smarty_tpl->getValue('lang')['tracker_peers'] ?? null)===null||$tmp==='' ? "Пиры" ?? null : $tmp)), ENT_QUOTES, 'UTF-8');?>
</b></td>
          <td class="lol" align="right" class="a"><?php echo htmlspecialchars((string) ((($tmp = $_smarty_tpl->getValue('stats')['peers_fmt'] ?? null)===null||$tmp==='' ? "0" ?? null : $tmp)), ENT_QUOTES, 'UTF-8');?>
</td>
        </tr>
        <tr>
          <td class="lol" align="left"><b><?php echo htmlspecialchars((string) ((($tmp = $_smarty_tpl->getValue('lang')['tracker_seeders'] ?? null)===null||$tmp==='' ? "Сиды" ?? null : $tmp)), ENT_QUOTES, 'UTF-8');?>
</b></td>
          <td class="lol" align="right" class="b"><?php echo htmlspecialchars((string) ((($tmp = $_smarty_tpl->getValue('stats')['seeders_fmt'] ?? null)===null||$tmp==='' ? "0" ?? null : $tmp)), ENT_QUOTES, 'UTF-8');?>
</td>
        </tr>
        <tr>
          <td class="lol" align="left"><b><?php echo htmlspecialchars((string) ((($tmp = $_smarty_tpl->getValue('lang')['tracker_leechers'] ?? null)===null||$tmp==='' ? "Личеры" ?? null : $tmp)), ENT_QUOTES, 'UTF-8');?>
</b></td>
          <td class="lol" align="right" class="a"><?php echo htmlspecialchars((string) ((($tmp = $_smarty_tpl->getValue('stats')['leechers_fmt'] ?? null)===null||$tmp==='' ? "0" ?? null : $tmp)), ENT_QUOTES, 'UTF-8');?>
</td>
        </tr>
        <tr>
          <td class="lol" align="left"><b>Всего траффика</b></td>
          <td class="lol" align="right" class="a"><?php echo htmlspecialchars((string) ((($tmp = $_smarty_tpl->getValue('stats')['traffic_fmt'] ?? null)===null||$tmp==='' ? "0 B" ?? null : $tmp)), ENT_QUOTES, 'UTF-8');?>
</td>
        </tr>
        <tr>
          <td class="lol" align="left"><b>Общий размер раздач</b></td>
          <td class="lol" align="right" class="a"><?php echo htmlspecialchars((string) ((($tmp = $_smarty_tpl->getValue('stats')['total_size_fmt'] ?? null)===null||$tmp==='' ? "0 B" ?? null : $tmp)), ENT_QUOTES, 'UTF-8');?>
</td>
        </tr>
      </tbody>
    </table>
  </div>
</div>
<?php }
}
