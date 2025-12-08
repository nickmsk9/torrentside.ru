<?php
/* Smarty version 5.5.1, created on 2025-10-03 11:15:54
  from 'file:partials/header_logo.tpl' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.5.1',
  'unifunc' => 'content_68df863ac5b0c2_53072845',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '2ee0b84722bb6e7b3af8d637962b00aa4ea7d6b9' => 
    array (
      0 => 'partials/header_logo.tpl',
      1 => 1759479352,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_68df863ac5b0c2_53072845 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = 'C:\\OSPanel\\domains\\torrentside.ru\\templates\\partials';
?><table width="100%" align="center" cellpadding="0" cellspacing="0" border="0">
  <tr>
    <td align="center" style="padding:0;">
      <div id="header-wrap"
           style="width:100%; height:146px; position:relative;
                  background:url('<?php echo htmlspecialchars((string) ($_smarty_tpl->getValue('baseUrl')), ENT_QUOTES, 'UTF-8');?>
/styles/images/_up_05.jpg') repeat-x top center;">
        <!-- Левый угол -->
        <div style="position:absolute; top:0; left:0; width:146px; height:146px;
                    background:url('<?php echo htmlspecialchars((string) ($_smarty_tpl->getValue('baseUrl')), ENT_QUOTES, 'UTF-8');?>
/styles/images/_dup_06.jpg') no-repeat top left;">
        </div>
        <!-- Правый угол -->
        <div style="position:absolute; top:0; right:0; width:146px; height:146px;
                    background:url('<?php echo htmlspecialchars((string) ($_smarty_tpl->getValue('baseUrl')), ENT_QUOTES, 'UTF-8');?>
/styles/images/_up_06.jpg') no-repeat top right;">
        </div>
        <!-- Логотип -->
        <div style="height:146px; display:flex; align-items:center; justify-content:center;">
          <img src="<?php echo htmlspecialchars((string) ($_smarty_tpl->getValue('baseUrl')), ENT_QUOTES, 'UTF-8');?>
/styles/images/logo.png"
               alt="Логотип"
               style="height:auto; max-height:100%;">
        </div>
      </div>
    </td>
  </tr>
</table>
<?php }
}
