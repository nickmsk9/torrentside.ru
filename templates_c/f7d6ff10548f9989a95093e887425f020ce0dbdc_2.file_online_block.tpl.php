<?php
/* Smarty version 5.5.1, created on 2026-03-29 12:17:27
  from 'file:partials/online_block.tpl' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.5.1',
  'unifunc' => 'content_69c91857ae1306_32337496',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    'f7d6ff10548f9989a95093e887425f020ce0dbdc' => 
    array (
      0 => 'partials/online_block.tpl',
      1 => 1774785100,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_69c91857ae1306_32337496 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = '/var/www/html/templates/partials';
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
$foreach2DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('u')->value) {
$foreach2DoElse = false;
$_smarty_tpl->getVariable('u')->iteration++;
$_smarty_tpl->getVariable('u')->last = $_smarty_tpl->getVariable('u')->iteration === $_smarty_tpl->getVariable('u')->total;
$foreach2Backup = clone $_smarty_tpl->getVariable('u');
?>
                  <?php echo $_smarty_tpl->getValue('u');
if (!$_smarty_tpl->getVariable('u')->last) {?>, <?php }?>
                <?php
$_smarty_tpl->setVariable('u', $foreach2Backup);
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
$foreach3DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('u')->value) {
$foreach3DoElse = false;
$_smarty_tpl->getVariable('u')->iteration++;
$_smarty_tpl->getVariable('u')->last = $_smarty_tpl->getVariable('u')->iteration === $_smarty_tpl->getVariable('u')->total;
$foreach3Backup = clone $_smarty_tpl->getVariable('u');
?>
                      <?php echo $_smarty_tpl->getValue('u');
if (!$_smarty_tpl->getVariable('u')->last) {?>, <?php }?>
                    <?php
$_smarty_tpl->setVariable('u', $foreach3Backup);
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
