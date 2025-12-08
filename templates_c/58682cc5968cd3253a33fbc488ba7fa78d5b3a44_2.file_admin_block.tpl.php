<?php
/* Smarty version 5.5.1, created on 2025-10-03 10:45:11
  from 'file:partials/admin_block.tpl' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.5.1',
  'unifunc' => 'content_68df7f071540c3_13076622',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '58682cc5968cd3253a33fbc488ba7fa78d5b3a44' => 
    array (
      0 => 'partials/admin_block.tpl',
      1 => 1759477267,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_68df7f071540c3_13076622 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = 'C:\\OSPanel\\domains\\torrentside.ru\\templates\\partials';
if ($_smarty_tpl->getValue('is_admin')) {?>
<!-- Блок админки (Smarty) -->
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
