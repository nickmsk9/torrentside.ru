{* Боковое меню пользователя *}
<div class="menu">
  <div class="m_menu">
    <div class="m_foot" style="padding: 60px 0 20px 30px;">
      {foreach $menuLinks as $it}
        <a class="menu"
           href="{$it.href|escape:'html'}">{$it.label|escape:'html'}</a>
      {/foreach}

      {if $showUtorrent}
        <div style="text-align:center; margin-top:5px;">
          <a href="http://www.myutorrent.ru">
            <img src="./pic/utorrent.gif"
                 alt="uTorrent" title="uTorrent" border="0">
          </a>
        </div>
      {/if}
    </div>
  </div>
</div>
