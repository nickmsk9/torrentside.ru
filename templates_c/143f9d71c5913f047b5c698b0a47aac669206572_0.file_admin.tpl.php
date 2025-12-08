<?php
/* Smarty version 5.5.1, created on 2025-10-03 10:28:50
  from 'file:admin.tpl' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.5.1',
  'unifunc' => 'content_68df7b32c5b278_58963211',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '143f9d71c5913f047b5c698b0a47aac669206572' => 
    array (
      0 => 'admin.tpl',
      1 => 1759412484,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_68df7b32c5b278_58963211 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = 'C:\\OSPanel\\domains\\torrentside.ru\\templates';
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
<?php } else { ?>
    <p>У вас нет доступа к админке.</p>
<?php }
}
}
