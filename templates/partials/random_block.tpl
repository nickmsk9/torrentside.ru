{* templates/partials/random_block.tpl *}
<div class="menu">
  <div class="m_best">
    <div class="m_foot">
      <div class="m_t">
        <div align="center"><b>{$bestBlock.title|escape}</b></div>
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
        {if $bestBlock.loggedin}
          <div>Рейтинг: {$bestBlock.ratio_html nofilter}</div>
          <div>Бонусы: <a href="{$bestBlock.bonus_url|escape}">{$bestBlock.bonus|escape}</a></div>
          <div>Инвайты: <a href="{$bestBlock.invites_url|escape}">{$bestBlock.invites}</a></div>
          <div align="center" style="padding-top:4px;"><a href="{$bestBlock.primary_url|escape}"><b>{$bestBlock.primary_label|escape}</b></a></div>
          <div align="center"><small>{$bestBlock.hint|escape}</small></div>
          <div align="center"><small><a href="{$bestBlock.secondary_url|escape}">{$bestBlock.secondary_label|escape}</a></small></div>
        {else}
          <div align="center" style="padding-top:4px;"><a href="{$bestBlock.primary_url|escape}"><b>{$bestBlock.primary_label|escape}</b></a></div>
          <div align="center"><small>{$bestBlock.hint|escape}</small></div>
          <div align="center"><small><a href="{$bestBlock.secondary_url|escape}">{$bestBlock.secondary_label|escape}</a></small></div>
        {/if}
      </div>
    </div>
  </div>
</div>
