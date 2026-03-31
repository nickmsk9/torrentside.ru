{* templates/partials/online_block.tpl *}
<div class="menu">
  <div class="m_online">
    <div class="m_foot">
      <div class="m_t">
        <table border="0" class="main6" width="100%">
          <tr valign="middle">
            <td align="left" class="embedded">
              <font color="#D89A21">
                <b>Кто онлайн ({$online.total}): </b><br>
              </font>
              {if $online.preview|@count > 0}
                {foreach $online.preview as $u}
                  {$u.link_html nofilter}{if !$u@last}, {/if}
                {/foreach}
                {if $online.more_count > 0}
                  <br><small>И ещё {$online.more_count} участн.</small>
                {/if}
              {elseif $online.guests_count > 0}
                <small>Пользователей сейчас нет, гостей: {$online.guests_count}.</small>
              {else}
                Нет пользователей за последние {$online.window_minutes} минут.
              {/if}
            </td>
          </tr>
        </table>

        <hr>

        <center>
          <table class="main" cellspacing="0" cellpadding="5" border="0" width="100%">
            <tr>
              <td class="embedded">
                <div align="left">
                  <span title="Скрыть/Показать" style="cursor:pointer;" onclick="return show_hide('online_today_list')">
                    <b>
                      <font color="red">{$today.count}</font>
                      <font color="#D89A21"> посетило сегодня &nabla;</font>
                    </b>
                  </span>
                </div>
                <div align="left">
                  <span id="online_today_list" style="display:none;">
                    {if $today.preview|@count > 0}
                      {foreach $today.preview as $u}
                        {$u.link_html nofilter}{if !$u@last}, {/if}
                      {/foreach}
                      {if $today.more_count > 0}
                        <br><small>И ещё {$today.more_count} сегодня.</small>
                      {/if}
                    {else}
                      <small>Сегодня пока никто не заходил.</small>
                    {/if}
                  </span>
                </div>
              </td>
            </tr>
          </table>

          <div>Сайту {$siteAge.days} дней</div>
        </center>
      </div>
    </div>
  </div>
</div>
