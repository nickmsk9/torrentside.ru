<?php
/* Smarty version 5.5.1, created on 2026-03-29 12:17:27
  from 'file:partials/footer_block.tpl' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.5.1',
  'unifunc' => 'content_69c91857aef6d7_40385159',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    'dba5989b47ebdc00c084eb75612359657df6458d' => 
    array (
      0 => 'partials/footer_block.tpl',
      1 => 1774785100,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_69c91857aef6d7_40385159 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = '/var/www/html/templates/partials';
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
