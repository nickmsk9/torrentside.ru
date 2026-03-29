<?php
/* Smarty version 5.5.1, created on 2026-03-29 12:17:27
  from 'file:partials/user_nav.tpl' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.5.1',
  'unifunc' => 'content_69c91857ab5dc3_56808080',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    'c6ee074dfe3463dd85758b79a6eebc35afa83ccf' => 
    array (
      0 => 'partials/user_nav.tpl',
      1 => 1774785100,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_69c91857ab5dc3_56808080 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = '/var/www/html/templates/partials';
?><div class="menu">
  <div class="m_menu">
    <div class="m_foot" style="padding: 60px 0 20px 30px;">
      <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('menuLinks'), 'it');
$foreach1DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('it')->value) {
$foreach1DoElse = false;
?>
        <a class="menu"
           href="<?php echo htmlspecialchars((string) ($_smarty_tpl->getValue('it')['href']), ENT_QUOTES, 'UTF-8');?>
"><?php echo htmlspecialchars((string) ($_smarty_tpl->getValue('it')['label']), ENT_QUOTES, 'UTF-8');?>
</a>
      <?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>

      <?php if ($_smarty_tpl->getValue('showUtorrent')) {?>
        <div style="text-align:center; margin-top:5px;">
          <a href="http://www.myutorrent.ru">
            <img src="./pic/utorrent.gif"
                 alt="uTorrent" title="uTorrent" border="0">
          </a>
        </div>
      <?php }?>
    </div>
  </div>
</div>
<?php }
}
