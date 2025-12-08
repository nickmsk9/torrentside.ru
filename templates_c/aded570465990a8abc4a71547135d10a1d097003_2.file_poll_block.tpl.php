<?php
/* Smarty version 5.5.1, created on 2025-10-03 11:38:52
  from 'file:partials/poll_block.tpl' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.5.1',
  'unifunc' => 'content_68df8b9ca7c9d2_08105417',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    'aded570465990a8abc4a71547135d10a1d097003' => 
    array (
      0 => 'partials/poll_block.tpl',
      1 => 1759480503,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_68df8b9ca7c9d2_08105417 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = 'C:\\OSPanel\\domains\\torrentside.ru\\templates\\partials';
if ($_smarty_tpl->getValue('is_mod')) {?>
  <div style="text-align:right; margin-bottom:10px;">
    <a class="btn-create" href="<?php echo htmlspecialchars((string) ($_smarty_tpl->getValue('makepoll_url')), ENT_QUOTES, 'UTF-8');?>
">Создать опрос</a>
  </div>
<?php }?>

<!-- Подключаем JS-ядро опроса и дергаем загрузку -->
<?php echo '<script'; ?>
 type="text/javascript" src="js/poll.core.js"><?php echo '</script'; ?>
>
<?php echo '<script'; ?>
 type="text/javascript">
  $jq(function(){ 
    if (typeof loadpoll === 'function') loadpoll();
  });
<?php echo '</script'; ?>
>

<table width="100%" align="center" border="0" cellspacing="0" cellpadding="10">
  <tr>
    <td align="center">
      <div id="poll_container">
        <div id="loading_poll" style="display:none"></div>
        <noscript><b>Для отображения опроса включите JavaScript.</b></noscript>
      </div>
    </td>
  </tr>
</table>

<style>
  /* Голубая округлая кнопка "Создать опрос" */
  .btn-create{
    display:inline-block;
    padding:8px 14px;
    border-radius:9999px;
    background:#1da1f2;
    color:#fff;
    text-decoration:none;
    font-weight:600;
    font-family:Verdana, sans-serif;
    box-shadow:0 2px 6px rgba(0,0,0,.12);
    transition:transform .07s ease, box-shadow .15s ease, background .15s ease;
  }
  .btn-create:hover{ background:#1596e6; box-shadow:0 3px 10px rgba(0,0,0,.18); }
  .btn-create:active{ transform:translateY(1px); }
  .btn-create:focus{ outline:2px solid rgba(29,161,242,.5); outline-offset:2px; }
</style>
<?php }
}
