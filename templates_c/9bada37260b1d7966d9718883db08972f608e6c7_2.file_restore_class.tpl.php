<?php
/* Smarty version 5.5.1, created on 2026-03-29 12:17:27
  from 'file:partials/restore_class.tpl' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.5.1',
  'unifunc' => 'content_69c91857ac5735_16532973',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '9bada37260b1d7966d9718883db08972f608e6c7' => 
    array (
      0 => 'partials/restore_class.tpl',
      1 => 1774785100,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_69c91857ac5735_16532973 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = '/var/www/html/templates/partials';
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
