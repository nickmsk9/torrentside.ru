<?php
/* Smarty version 5.5.1, created on 2025-10-03 11:15:01
  from 'file:partials/restore_class.tpl' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.5.1',
  'unifunc' => 'content_68df860543b8c4_86825299',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '6baa2d66850e2217261b561b1f15aeb9a7b44fc5' => 
    array (
      0 => 'partials/restore_class.tpl',
      1 => 1759479299,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_68df860543b8c4_86825299 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = 'C:\\OSPanel\\domains\\torrentside.ru\\templates\\partials';
if ($_smarty_tpl->getValue('restoreClass')) {?>
<div style="
    display: inline-block;
    padding: 8px 15px;
    margin: 10px auto;
    background: linear-gradient(to right, #5b5bff, #9e72ff);
    border-radius: 10px;
    font-size: 14px;
    font-family: Verdana, sans-serif;
    font-weight: bold;
    color: #fff;
    box-shadow: 1px 1px 3px rgba(0,0,0,0.2);
">
  <a href="<?php echo htmlspecialchars((string) ($_smarty_tpl->getValue('restoreClass')['url']), ENT_QUOTES, 'UTF-8');?>
" style="color: #fff; text-decoration: none;">
    <?php echo htmlspecialchars((string) ($_smarty_tpl->getValue('restoreClass')['label']), ENT_QUOTES, 'UTF-8');?>

  </a>
</div>
<?php }
}
}
