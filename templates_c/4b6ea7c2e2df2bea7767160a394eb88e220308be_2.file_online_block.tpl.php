<?php
/* Smarty version 5.5.1, created on 2025-10-03 10:56:37
  from 'file:partials/online_block.tpl' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.5.1',
  'unifunc' => 'content_68df81b589ae54_17800227',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '4b6ea7c2e2df2bea7767160a394eb88e220308be' => 
    array (
      0 => 'partials/online_block.tpl',
      1 => 1759478132,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_68df81b589ae54_17800227 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = 'C:\\OSPanel\\domains\\torrentside.ru\\templates\\partials';
?><div class="menu">
  <div class="m_online">
    <div class="m_foot">
      <div class="m_t">

        <table border="0" class="main6" width="100%">
          <?php if ($_smarty_tpl->getValue('online')['total'] > 0) {?>
            <tr valign="middle">
              <td align="left" class="embedded">
                <font color="#D89A21">
                  <b>Кто онлайн (<?php echo htmlspecialchars((string) ($_smarty_tpl->getValue('online')['total']), ENT_QUOTES, 'UTF-8');?>
): </b><br>
                </font>
                <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('online')['users'], 'u', true);
$_smarty_tpl->getVariable('u')->iteration = 0;
$foreach0DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('u')->value) {
$foreach0DoElse = false;
$_smarty_tpl->getVariable('u')->iteration++;
$_smarty_tpl->getVariable('u')->last = $_smarty_tpl->getVariable('u')->iteration === $_smarty_tpl->getVariable('u')->total;
$foreach0Backup = clone $_smarty_tpl->getVariable('u');
?>
                  <?php echo $_smarty_tpl->getValue('u');
if (!$_smarty_tpl->getVariable('u')->last) {?>, <?php }?>
                <?php
$_smarty_tpl->setVariable('u', $foreach0Backup);
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
              </td>
            </tr>
          <?php } else { ?>
            <tr valign="middle">
              <td align="left" class="embedded">
                <font color="#D89A21">
                  <b>Кто онлайн: </b><br>
                  Нет пользователей за последние 10 минут.
                </font>
              </td>
            </tr>
          <?php }?>
        </table>

        <hr>

        <center>
          <table class="main" cellspacing="0" cellpadding="5" border="0" width="100%">
            <tr>
              <td class="embedded">
                <div align="left">
                  <span title="Скрыть/Показать" style="cursor:pointer;" onclick="show_hide('s15')">
                    <b>
                      <font color="red"><?php echo htmlspecialchars((string) ($_smarty_tpl->getValue('today')['count']), ENT_QUOTES, 'UTF-8');?>
</font>
                      <font color="#D89A21"> посетило сегодня &nabla;</font>
                    </b>
                  </span>
                </div>
                <div align="left">
                  <span id="ss15" style="display:none;">
                    <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('today')['users'], 'u', true);
$_smarty_tpl->getVariable('u')->iteration = 0;
$foreach1DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('u')->value) {
$foreach1DoElse = false;
$_smarty_tpl->getVariable('u')->iteration++;
$_smarty_tpl->getVariable('u')->last = $_smarty_tpl->getVariable('u')->iteration === $_smarty_tpl->getVariable('u')->total;
$foreach1Backup = clone $_smarty_tpl->getVariable('u');
?>
                      <?php echo $_smarty_tpl->getValue('u');
if (!$_smarty_tpl->getVariable('u')->last) {?>, <?php }?>
                    <?php
$_smarty_tpl->setVariable('u', $foreach1Backup);
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
                  </span>
                </div>
              </td>
            </tr>
          </table>

          <div>Сайту <?php echo htmlspecialchars((string) ($_smarty_tpl->getValue('daysAlive')), ENT_QUOTES, 'UTF-8');?>
 дней</div>
        </center>

      </div>
    </div>
  </div>
</div>
<?php }
}
