<?php
/* Smarty version 5.5.1, created on 2025-10-20 09:09:28
  from 'file:partials/birthdays_block.tpl' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.5.1',
  'unifunc' => 'content_68f5d218b92859_78733061',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '16df9b1dff5e82076b5fd6d8628a83ce0dd9ccee' => 
    array (
      0 => 'partials/birthdays_block.tpl',
      1 => 1760940566,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_68f5d218b92859_78733061 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = 'C:\\OSPanel\\domains\\torrentside.ru\\templates\\partials';
?><div class="menu">
  <div class="m_birthdays">
    <div class="m_foot">
      <div class="m_t" style="text-align:center; padding:10px;">

        <div style="margin-bottom:6px; font-weight:bold;"><?php echo htmlspecialchars((string) ($_smarty_tpl->getValue('birthdays_title')), ENT_QUOTES, 'UTF-8');?>
</div>

        <?php if ($_smarty_tpl->getSmarty()->getModifierCallback('count')($_smarty_tpl->getValue('birthdays')) > 0) {?>
          <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('birthdays'), 'u');
$foreach0DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('u')->value) {
$foreach0DoElse = false;
?>
            <div style="margin:4px 0;">
              <a href="userdetails.php?id=<?php echo htmlspecialchars((string) ($_smarty_tpl->getValue('u')['id']), ENT_QUOTES, 'UTF-8');?>
">
                <?php echo $_smarty_tpl->getValue('u')['name_html'];?>

              </a>
              <?php if ((true && (true && null !== ($_smarty_tpl->getValue('u')['genderIcon'] ?? null))) && $_smarty_tpl->getValue('u')['genderIcon']) {?>
                <img src="<?php echo htmlspecialchars((string) ($_smarty_tpl->getValue('u')['genderIcon']['src']), ENT_QUOTES, 'UTF-8');?>
" alt="<?php echo htmlspecialchars((string) ($_smarty_tpl->getValue('u')['genderIcon']['alt']), ENT_QUOTES, 'UTF-8');?>
"
                     style="vertical-align:middle; margin-left:4px;" />
              <?php }?>
            </div>
          <?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
        <?php } else { ?>
          <div style="font-size:12px; color:#888;">
            Ждем ждем ...
          </div>
        <?php }?>

      </div>
    </div>
  </div>
</div>
<?php }
}
