{* templates/partials/random_block.tpl *}
<div class="menu">
  <div class="m_best">
    <div class="m_foot">
      <div class="m_t">
        {if !empty($bestBlock.trophy_rows)}
          <div class="best-trophy-board">
            <div class="best-trophy-title">Переходящие кубки</div>
            <table class="best-trophy-table">
              {foreach $bestBlock.trophy_rows as $row}
                <tr>
                  <td class="best-trophy-pos">{$row.position|escape}.</td>
                  <td class="best-trophy-user">{$row.user_html nofilter}</td>
                  <td class="best-trophy-icons">{$row.icons_html nofilter}</td>
                </tr>
              {/foreach}
            </table>
            {if !empty($bestBlock.trophy_stats)}
              <div class="best-trophy-stats">
                {foreach $bestBlock.trophy_stats as $stat}
                  <div>{$stat|escape}</div>
                {/foreach}
              </div>
            {/if}
          </div>
        {/if}
      </div>
    </div>
  </div>
</div>
