{* templates/partials/friends_block.tpl *}
<div class="menu">
  <div class="m_friends">
    <div class="m_foot">
      <div class="m_t" style="text-align: center; padding: 10px;">

        {if $friends|@count > 0}
          <div style="margin-bottom: 6px; font-weight: bold;">Мои друзья:</div>
          {foreach $friends as $f}
            <div style="margin: 4px 0;">
              <a href="userdetails.php?id={$f.id}">
                {$f.name nofilter}
              </a>
              {if $f.online}
                <span style="color:green; font-size:11px;">● онлайн</span>
              {else}
                <span style="color:gray; font-size:11px;">● оффлайн</span>
              {/if}
            </div>
          {/foreach}
        {else}
          <div style="font-size:12px; color:#888;">
            У вас пока нет друзей.
          </div>
        {/if}

      </div>
    </div>
  </div>
</div>
