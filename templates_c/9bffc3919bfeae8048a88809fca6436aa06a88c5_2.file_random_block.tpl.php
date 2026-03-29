<?php
/* Smarty version 5.5.1, created on 2026-03-29 12:17:27
  from 'file:partials/random_block.tpl' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.5.1',
  'unifunc' => 'content_69c91857ae7483_34010033',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '9bffc3919bfeae8048a88809fca6436aa06a88c5' => 
    array (
      0 => 'partials/random_block.tpl',
      1 => 1774785100,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_69c91857ae7483_34010033 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = '/var/www/html/templates/partials';
?><div class="menu">
  <div class="m_best">
    <div class="m_foot">
      <div class="m_t" style="text-align: center; padding: 10px; color: white;">
        <div style="font-size: 12px; margin-top: 40px; margin-bottom: 12px;">
          Хочешь случайное число? Лови!
        </div>
        <div id="random-result" style="font-size: 14px; font-weight: bold;">Число: —</div>
        <button
          onclick="document.getElementById('random-result').innerText = 'Число: ' + Math.floor(Math.random() * 100 + 1);"
          style="margin-top: 10px; padding: 6px 12px; font-size: 12px; cursor: pointer;">
          Сгенерировать
        </button>
      </div>
    </div>
  </div>
</div>
<?php }
}
