{* templates/partials/friends_block.tpl *}
<div class="menu">
  <div class="m_friends">
    <div class="m_foot">
      <div class="m_t">
        {if $socialBlock.loggedin}
          <div align="center"><a class="menu" href="{$socialBlock.url|escape}"><b>Круг общения</b></a></div>
          <div><small>Друзей: {$socialBlock.friends_count} | онлайн: {$socialBlock.online_count}</small></div>
          <div><small>Заявки: {$socialBlock.pending_count} | праздник: {$socialBlock.birthdays_count}</small></div>

          {if $socialBlock.friends_preview|@count > 0}
            <div><small>
              {foreach $socialBlock.friends_preview as $f}
                {$f.link_html nofilter}{if !$f@last}, {/if}
              {/foreach}
            </small></div>
          {elseif $socialBlock.pending_preview|@count > 0}
            <div><small>Ждут ответа:
              {foreach $socialBlock.pending_preview as $f}
                {$f.link_html nofilter}{if !$f@last}, {/if}
              {/foreach}
            </small></div>
          {elseif $socialBlock.friends_count > 0}
            <div><small>Сегодня у друзей тихо.</small></div>
          {else}
            <div><small><a href="users.php">Найдите людей</a> и соберите круг.</small></div>
          {/if}
        {else}
          <div align="center"><b>Круг общения</b></div>
          <div><small>После входа здесь появятся друзья и заявки.</small></div>
        {/if}
      </div>
    </div>
  </div>
</div>
