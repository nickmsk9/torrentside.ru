{* Блок «Новые поступления» без рамки и кнопок *}
<h2 style="margin:10px 0;color:#fff;">Новые поступления</h2>

{foreach $torrents_cards as $t}
<div class="c">
  <div class="c1"><div class="c2"><div class="c3"><div class="c4"><div class="c5">
  <div class="c6"><div class="c7"><div class="c8">
    <div class="ci" align="left">

     <div class="c_tit">{$t.name}</div>


      <table width="100%" border="0" cellspacing="0" cellpadding="3">
        <tr valign="top">
          <td width="100%" class="text">
            <div align="center">
              <a href="{$t.url}">
                {if $t.img != ''}
  <div class="glass-frame">
    <img src="{$t.img}" alt="Постер">
  </div><br>
{/if}
              </a>
            </div>
<div>{$t.descr nofilter}</div>
          </td>
        </tr>
      </table>

      <br>

      <!-- rating -->
      <table border="0" cellpadding="0" cellspacing="0">
        <tr>
          <td>
            <div class="sbg"><div class="ss1"><div class="rat st">
              {$t.added}
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
                  <font color="green"><img src="pic/up.gif" title="Раздают" /> {$t.seeders}</font> |
                  <font color="red"><img src="pic/ardown.gif" title="Качают" /> {$t.leechers}</font> |
                  <font color="orange">Скачавших: {$t.completed}</font>
                </b></font>
              </td>
              <td align="right" class="r">
                <a href="{$t.url}">
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
{/foreach}

<style>
  .glass-frame{
    display:inline-block; border-radius:12px; padding:6px;
    background: rgba(255,255,255,.08);
    box-shadow: 0 4px 18px rgba(0,0,0,.18), inset 0 1px 0 rgba(255,255,255,.2);
    -webkit-backdrop-filter: blur(6px) saturate(140%);
    backdrop-filter: blur(6px) saturate(140%);
  }
  .glass-frame img{ max-width:240px; height:auto; display:block; border-radius:8px; }
</style>
