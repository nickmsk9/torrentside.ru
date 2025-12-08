<?php
/* Smarty version 5.5.1, created on 2025-10-03 10:59:49
  from 'file:partials/friends_block.tpl' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.5.1',
  'unifunc' => 'content_68df8275175673_77170615',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '1801b5f7144c16186af9a4aef5e0d1110f782f19' => 
    array (
      0 => 'partials/friends_block.tpl',
      1 => 1759478357,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_68df8275175673_77170615 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = 'C:\\OSPanel\\domains\\torrentside.ru\\templates\\partials';
?><div class="menu">
  <div class="m_friends">
    <div class="m_foot">
      <div class="m_t" style="text-align: center; padding: 10px;">

        <?php if ($_smarty_tpl->getSmarty()->getModifierCallback('count')($_smarty_tpl->getValue('friends')) > 0) {?>
          <div style="margin-bottom: 6px; font-weight: bold;">Мои друзья:</div>
          <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('friends'), 'f');
$foreach0DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('f')->value) {
$foreach0DoElse = false;
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
