{* partials/debug_block.tpl *}
<div class="debug-panel">
  <table class="debug-table">
    <thead>
      <tr><th>#</th><th>сек</th><th>запрос</th></tr>
    </thead>
    <tbody>
      {if $debug.rows|@count > 0}
        {foreach from=$debug.rows item=r}
          {assign var=cls value=$r.class}
          {assign var=sec value=$r.sec}
          <tr>
            <td>{$r.n}</td>
            <td>
              {if $cls == 'err'}<span class="badge err">{$sec}</span>
              {elseif $cls == 'warn'}<span class="badge warn">{$sec}</span>
              {else}<span class="badge ok">{$sec}</span>{/if}
            </td>
            <td style="word-break:break-word">{$r.sql|escape}</td>
          </tr>
        {/foreach}
        {if $debug.hidden_count > 0}
          <tr><td colspan="3"><em>… скрыто ещё {$debug.hidden_count} строк</em></td></tr>
        {/if}
      {else}
        <tr><td colspan="3">—</td></tr>
      {/if}
    </tbody>
  </table>

  <div class="kv" style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px">
    <span>SQL-время: <b>{$debug.total_sql_time} с</b></span>
    <span>Запросов: <b>{$debug.total_queries}</b></span>
    <span>Страница: <b>{$debug.page_time}</b></span>
    <span>Пик памяти: <b>{$debug.mem_peak_mb} Миб</b></span>
    <span>Нагрузка: <b>{$debug.server_load|escape}</b></span>
  </div>
</div>
