<?php
/* Smarty version 5.5.1, created on 2026-03-29 12:17:27
  from 'file:partials/admin_block.tpl' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.5.1',
  'unifunc' => 'content_69c91857ae4b04_92822903',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '46c0e9283a4928bacd66c8d01615837f597729c1' => 
    array (
      0 => 'partials/admin_block.tpl',
      1 => 1774785100,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_69c91857ae4b04_92822903 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = '/var/www/html/templates/partials';
if ($_smarty_tpl->getValue('is_admin')) {?>
<div class="menu">
    <div class="m_admin">
        <div class="m_foot">
            <div class="m_t">
                <a class="menu" href="admincp.php">Админка</a>
            </div>
        </div>
    </div>
</div>
<?php }
}
}
