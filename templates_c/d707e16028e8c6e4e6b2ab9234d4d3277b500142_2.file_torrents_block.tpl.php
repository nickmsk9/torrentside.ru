<?php
/* Smarty version 5.5.1, created on 2025-10-03 11:36:48
  from 'file:partials/torrents_block.tpl' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.5.1',
  'unifunc' => 'content_68df8b20ad7610_48147194',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    'd707e16028e8c6e4e6b2ab9234d4d3277b500142' => 
    array (
      0 => 'partials/torrents_block.tpl',
      1 => 1759480607,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_68df8b20ad7610_48147194 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = 'C:\\OSPanel\\domains\\torrentside.ru\\templates\\partials';
if ($_smarty_tpl->getValue('torrents_hdr_btn')) {?>
  <div style="text-align:right; margin:-6px 0 8px 0;">
    <a href="<?php echo htmlspecialchars((string) ($_smarty_tpl->getValue('torrents_hdr_btn')['href']), ENT_QUOTES, 'UTF-8');?>
" class="btn-blue"><?php echo htmlspecialchars((string) ($_smarty_tpl->getValue('torrents_hdr_btn')['label']), ENT_QUOTES, 'UTF-8');?>
</a>
  </div>
<?php }?>

<?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('torrents_cards'), 't');
$foreach0DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('t')->value) {
$foreach0DoElse = false;
?>
<div class="c">
  <div class="c1"><div class="c2"><div class="c3"><div class="c4"><div class="c5">
  <div class="c6"><div class="c7"><div class="c8">
    <div class="ci" align="left">

      <div class="c_tit"><?php echo $_smarty_tpl->getValue('t')['name_html'];?>
</div>

      <table width="100%" border="0" cellspacing="0" cellpadding="3">
        <tr valign="top">
          <td width="100%" class="text">
            <div align="center">
              <a href="<?php echo htmlspecialchars((string) ($_smarty_tpl->getValue('t')['details']), ENT_QUOTES, 'UTF-8');?>
">
                <?php if ($_smarty_tpl->getValue('t')['img'] != '') {?>
                  <div class="glass-frame">
                    <img src="<?php echo htmlspecialchars((string) ($_smarty_tpl->getValue('t')['img']), ENT_QUOTES, 'UTF-8');?>
" alt="Постер">
                  </div>
                  <br>
                <?php }?>
              </a>
            </div>

            <div><?php echo $_smarty_tpl->getValue('t')['descr_html'];?>
</div>
          </td>
        </tr>
      </table>

      <br>

      <!-- rating -->
      <table border="0" cellpadding="0" cellspacing="0">
        <tr>
          <td>
            <div class="sbg"><div class="ss1"><div class="rat st">
              <?php echo htmlspecialchars((string) ($_smarty_tpl->getValue('t')['added']), ENT_QUOTES, 'UTF-8');?>

              <div class="cl"></div>
            </div></div></div>
          </td>
          <td><div class="ss2"></div></td>
        </tr>
      </table>
      <!-- /rating -->

      <div class="s"><div class="s1"><div class="s2">
        <div class="st">
          <table width="100%" cellpadding="0" cellspacing="0" border="0">
            <tr>
              <td>
                <font color="white"><b>
                  <font color="green"><img src="pic/up.gif" title="Раздают" /> <?php echo htmlspecialchars((string) ($_smarty_tpl->getValue('t')['seeders']), ENT_QUOTES, 'UTF-8');?>
</font> |
                  <font color="red"><img src="pic/ardown.gif" title="Качают" /> <?php echo htmlspecialchars((string) ($_smarty_tpl->getValue('t')['leechers']), ENT_QUOTES, 'UTF-8');?>
</font> |
                  <font color="orange">Скачавших: <?php echo htmlspecialchars((string) ($_smarty_tpl->getValue('t')['completed']), ENT_QUOTES, 'UTF-8');?>
</font>
                </b></font>
              </td>
              <td align="right" class="r">
                <a href="<?php echo htmlspecialchars((string) ($_smarty_tpl->getValue('t')['details']), ENT_QUOTES, 'UTF-8');?>
">
                  <font color="#ADFF2F">Подробнее/Скачать</font>
                </a>
              </td>
            </tr>
          </table>
        </div>
      </div></div></div>

    </div>
  </div></div></div></div></div></div></div></div>
</div>
<?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>

<style>
  /* Аккуратная голубая кнопка (для «Загрузить») */
  .btn-blue{
    display:inline-block; padding:8px 14px; border-radius:9999px;
    background:#1da1f2; color:#fff; text-decoration:none;
    font-weight:600; font-family:Verdana, sans-serif;
    box-shadow:0 2px 6px rgba(0,0,0,.12);
    transition:transform .07s ease, box-shadow .15s ease, background .15s ease;
  }
  .btn-blue:hover{ background:#1596e6; box-shadow:0 3px 10px rgba(0,0,0,.18); }
  .btn-blue:active{ transform:translateY(1px); }
  .btn-blue:focus{ outline:2px solid rgba(29,161,242,.5); outline-offset:2px; }

  /* Стеклянная рамка вокруг постера — как у тебя */
  .glass-frame{
    display:inline-block; border-radius:12px; padding:6px;
    background: rgba(255,255,255,.08);
    box-shadow: 0 4px 18px rgba(0,0,0,.18), inset 0 1px 0 rgba(255,255,255,.2);
    -webkit-backdrop-filter: blur(6px) saturate(140%);
    backdrop-filter: blur(6px) saturate(140%);
  }
  .glass-frame img{ max-width:240px; height:auto; display:block; border-radius:8px; }
</style>
<?php }
}
