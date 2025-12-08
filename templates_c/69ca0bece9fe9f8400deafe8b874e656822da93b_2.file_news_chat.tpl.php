<?php
/* Smarty version 5.5.1, created on 2025-10-03 11:31:01
  from 'file:partials/news_chat.tpl' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.5.1',
  'unifunc' => 'content_68df89c5762662_97109387',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '69ca0bece9fe9f8400deafe8b874e656822da93b' => 
    array (
      0 => 'partials/news_chat.tpl',
      1 => 1759480210,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_68df89c5762662_97109387 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = 'C:\\OSPanel\\domains\\torrentside.ru\\templates\\partials';
if ($_smarty_tpl->getValue('latestNews')) {?>
  <table width="100%" border="1" cellspacing="0" cellpadding="10">
    <tr>
      <td class="text">
        <ul style="margin:0; padding-left:16px;">
          <li>
            <span id="ss<?php echo htmlspecialchars((string) ($_smarty_tpl->getValue('latestNews')['id']), ENT_QUOTES, 'UTF-8');?>
" style="display:block;">
              <?php echo $_smarty_tpl->getValue('latestNews')['body_html'];?>

            </span>
            <?php if ($_smarty_tpl->getValue('latestNews')['edit_url']) {?>
              <div class="small" style="margin-top:6px;">
                [<a class="altlink" href="<?php echo htmlspecialchars((string) ($_smarty_tpl->getValue('latestNews')['edit_url']), ENT_QUOTES, 'UTF-8');?>
"><b>Редактировать</b></a>]
              </div>
            <?php }?>
          </li>
        </ul>
      </td>
    </tr>
  </table>
<?php }?>

<br>

<iframe
  src="<?php echo htmlspecialchars((string) ($_smarty_tpl->getValue('chat')['iframe_src']), ENT_QUOTES, 'UTF-8');?>
"
  width="95%"
  height="200"
  align="center"
  frameborder="0"
  name="sbox"
  marginwidth="0"
  marginheight="0"
  style="border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.1);">
</iframe>

<br>

<?php if ($_smarty_tpl->getValue('chat')['show_form']) {?>
  <form action="shoutbox.php" method="get" target="sbox" name="shbox" onsubmit="mySubmit()"
        style="text-align:center; margin-top:10px;">
    <input type="text" name="shbox_text" size="100" placeholder="Введите сообщение..." style="
        padding:6px; border:1px solid #ccc; border-radius:6px; font-family:Verdana, sans-serif; font-size:12px;">
    <input type="hidden" name="sent" value="yes">
    <input type="submit" value="Сказать" style="
        padding:6px 12px; margin-left:6px; border:none;
        background:linear-gradient(to right,#5b5bff,#9e72ff); color:#fff; border-radius:6px;
        font-weight:bold; cursor:pointer;">
    &nbsp;
    <a href="shoutbox.php" target="sbox" style="
        padding:6px 10px; background:#eee; border-radius:6px; text-decoration:none;
        font-weight:bold; font-size:12px; color:#333; margin-left:5px;">Обновить</a>

    <br><br>

    <fieldset style="
        display:inline-block; border:1px solid #ccc; padding:10px; margin-top:10px;
        border-radius:10px; background:#f5f5ff; max-width:90%; text-align:left;">
      <legend style="font-weight:bold;">Смайлы</legend>

      <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('smiles'), 's');
$foreach0DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('s')->value) {
$foreach0DoElse = false;
?>
        <a href="javascript: SmileIT('<?php echo htmlspecialchars((string) ($_smarty_tpl->getValue('s')['code_escaped']), ENT_QUOTES, 'UTF-8');?>
','shbox','shbox_text')">
          <img src="<?php echo htmlspecialchars((string) ($_smarty_tpl->getValue('s')['src']), ENT_QUOTES, 'UTF-8');?>
" alt="" style="margin:2px; vertical-align:middle;">
        </a>
      <?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
    </fieldset>
  </form>
<?php }
}
}
