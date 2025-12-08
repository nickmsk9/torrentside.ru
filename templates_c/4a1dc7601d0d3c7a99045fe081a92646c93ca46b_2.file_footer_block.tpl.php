<?php
/* Smarty version 5.5.1, created on 2025-10-03 11:01:52
  from 'file:partials/footer_block.tpl' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.5.1',
  'unifunc' => 'content_68df82f0f24d46_83885284',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '4a1dc7601d0d3c7a99045fe081a92646c93ca46b' => 
    array (
      0 => 'partials/footer_block.tpl',
      1 => 1759478511,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_68df82f0f24d46_83885284 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = 'C:\\OSPanel\\domains\\torrentside.ru\\templates\\partials';
?><div id="menucase">
  <div id="stylefour">
    <span class="small" style="color:#000;">
      Powered by FTEDev (Based on YSE)<br />
      <?php echo htmlspecialchars((string) ($_smarty_tpl->getValue('page_generated')), ENT_QUOTES, 'UTF-8');?>
 | PHP версия: <?php echo htmlspecialchars((string) ($_smarty_tpl->getValue('phpversion')), ENT_QUOTES, 'UTF-8');?>

    </span>
  </div>
</div>
<?php }
}
