<?php
/* Smarty version 5.5.1, created on 2025-10-08 11:17:29
  from 'file:partials/debug_block.tpl' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.5.1',
  'unifunc' => 'content_68e61e1938cae1_72395417',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '7872a3aa1941d6e1409aeef3f9310fb7c0051d78' => 
    array (
      0 => 'partials/debug_block.tpl',
      1 => 1759911447,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_68e61e1938cae1_72395417 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = 'C:\\OSPanel\\domains\\torrentside.ru\\templates\\partials';
?><div class="debug-panel">
  <table class="debug-table">
    <thead>
      <tr><th>#</th><th>сек</th><th>запрос</th></tr>
    </thead>
    <tbody>
      <?php if ($_smarty_tpl->getSmarty()->getModifierCallback('count')($_smarty_tpl->getValue('debug')['rows']) > 0) {?>
        <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('debug')['rows'], 'r');
$foreach0DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('r')->value) {
$foreach0DoElse = false;
?>
          <?php $_smarty_tpl->assign('cls', $_smarty_tpl->getValue('r')['class'], false, NULL);?>
          <?php $_smarty_tpl->assign('sec', $_smarty_tpl->getValue('r')['sec'], false, NULL);?>
          <tr>
            <td><?php echo htmlspecialchars((string) ($_smarty_tpl->getValue('r')['n']), ENT_QUOTES, 'UTF-8');?>
</td>
            <td>
              <?php if ($_smarty_tpl->getValue('cls') == 'err') {?><span class="badge err"><?php echo htmlspecialchars((string) ($_smarty_tpl->getValue('sec')), ENT_QUOTES, 'UTF-8');?>
</span>
              <?php } elseif ($_smarty_tpl->getValue('cls') == 'warn') {?><span class="badge warn"><?php echo htmlspecialchars((string) ($_smarty_tpl->getValue('sec')), ENT_QUOTES, 'UTF-8');?>
</span>
              <?php } else { ?><span class="badge ok"><?php echo htmlspecialchars((string) ($_smarty_tpl->getValue('sec')), ENT_QUOTES, 'UTF-8');?>
</span><?php }?>
            </td>
            <td style="word-break:break-word"><?php echo htmlspecialchars((string) ($_smarty_tpl->getValue('r')['sql']), ENT_QUOTES, 'UTF-8');?>
</td>
          </tr>
        <?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
        <?php if ($_smarty_tpl->getValue('debug')['hidden_count'] > 0) {?>
          <tr><td colspan="3"><em>… скрыто ещё <?php echo htmlspecialchars((string) ($_smarty_tpl->getValue('debug')['hidden_count']), ENT_QUOTES, 'UTF-8');?>
 строк</em></td></tr>
        <?php }?>
      <?php } else { ?>
        <tr><td colspan="3">—</td></tr>
      <?php }?>
    </tbody>
  </table>

  <div class="kv" style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px">
    <span>SQL-время: <b><?php echo htmlspecialchars((string) ($_smarty_tpl->getValue('debug')['total_sql_time']), ENT_QUOTES, 'UTF-8');?>
 с</b></span>
    <span>Запросов: <b><?php echo htmlspecialchars((string) ($_smarty_tpl->getValue('debug')['total_queries']), ENT_QUOTES, 'UTF-8');?>
</b></span>
    <span>Страница: <b><?php echo htmlspecialchars((string) ($_smarty_tpl->getValue('debug')['page_time']), ENT_QUOTES, 'UTF-8');?>
</b></span>
    <span>Пик памяти: <b><?php echo htmlspecialchars((string) ($_smarty_tpl->getValue('debug')['mem_peak_mb']), ENT_QUOTES, 'UTF-8');?>
 Миб</b></span>
    <span>Нагрузка: <b><?php echo htmlspecialchars((string) ($_smarty_tpl->getValue('debug')['server_load']), ENT_QUOTES, 'UTF-8');?>
</b></span>
  </div>
</div>
<?php }
}
