<?php
/* Smarty version 5.5.1, created on 2025-10-03 10:57:38
  from 'file:partials/random_block.tpl' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.5.1',
  'unifunc' => 'content_68df81f267a3b9_46202416',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '34c277ae08c37cbbad2ddc647fcfbc6793aa6de0' => 
    array (
      0 => 'partials/random_block.tpl',
      1 => 1759478236,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_68df81f267a3b9_46202416 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = 'C:\\OSPanel\\domains\\torrentside.ru\\templates\\partials';
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
