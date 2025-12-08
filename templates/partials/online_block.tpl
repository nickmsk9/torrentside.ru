{* templates/partials/online_block.tpl *}
<div class="menu">
  <div class="m_online">
    <div class="m_foot">
      <div class="m_t">

        <table border="0" class="main6" width="100%">
          {if $online.total > 0}
            <tr valign="middle">
              <td align="left" class="embedded">
                <font color="#D89A21">
                  <b>Кто онлайн ({$online.total}): </b><br>
                </font>
                {foreach $online.users as $u}
                  {$u nofilter}{if !$u@last}, {/if}
                {/foreach}
              </td>
            </tr>
          {else}
            <tr valign="middle">
              <td align="left" class="embedded">
                <font color="#D89A21">
                  <b>Кто онлайн: </b><br>
                  Нет пользователей за последние 10 минут.
                </font>
              </td>
            </tr>
          {/if}
        </table>

        <hr>

        <center>
          <table class="main" cellspacing="0" cellpadding="5" border="0" width="100%">
            <tr>
              <td class="embedded">
                <div align="left">
                  <span title="Скрыть/Показать" style="cursor:pointer;" onclick="show_hide('s15')">
                    <b>
                      <font color="red">{$today.count}</font>
                      <font color="#D89A21"> посетило сегодня &nabla;</font>
                    </b>
                  </span>
                </div>
                <div align="left">
                  <span id="ss15" style="display:none;">
                    {foreach $today.users as $u}
                      {$u nofilter}{if !$u@last}, {/if}
                    {/foreach}
                  </span>
                </div>
              </td>
            </tr>
          </table>

          <div>Сайту {$daysAlive} дней</div>
        </center>

      </div>
    </div>
  </div>
</div>
