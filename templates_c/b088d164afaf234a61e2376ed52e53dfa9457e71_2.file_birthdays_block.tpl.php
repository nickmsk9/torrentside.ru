<?php
/* Smarty version 5.5.1, created on 2026-03-29 12:17:27
  from 'file:partials/birthdays_block.tpl' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.5.1',
  'unifunc' => 'content_69c91857aec554_88328491',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    'b088d164afaf234a61e2376ed52e53dfa9457e71' => 
    array (
      0 => 'partials/birthdays_block.tpl',
      1 => 1774785100,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_69c91857aec554_88328491 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = '/var/www/html/templates/partials';
?><div class="menu">
  <div class="m_friends">
    <div class="m_foot">
      <div class="m_t" style="text-align: center; padding: 10px;">

        <?php if ($_smarty_tpl->getSmarty()->getModifierCallback('count')($_smarty_tpl->getValue('friends')) > 0) {?>
          <div style="margin-bottom: 6px; font-weight: bold;">Мои друзья:</div>
          <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('friends'), 'f');
$foreach4DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('f')->value) {
$foreach4DoElse = false;
?>
            <div style="margin: 4px 0;">
              <a href="userdetails.php?id=<?php echo htmlspecialchars((string) ($_smarty_tpl->getValue('f')['id']), ENT_QUOTES, 'UTF-8');?>
">
                <?php echo $_smarty_tpl->getValue('f')['name'];?>

              </a>
              <?php if ($_smarty_tpl->getValue('f')['online']) {?>
                <span style="color:green; font-size:11px;">● онлайн</span>
              <?php } else { ?>
                <span style="color:gray; font-size:11px;">● оффлайн</span>
              <?php }?>
            </div>
          <?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
        <?php } else { ?>
          <div style="font-size:12px; color:#888;">
            У вас пока нет друзей.
          </div>
        <?php }?>

      </div>
    </div>
  </div>
</div>
<?php }
}
