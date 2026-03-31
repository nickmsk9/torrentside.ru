{if $adminPanel.show}
<div class="menu">
    <div class="m_admin">
        <div class="m_foot">
            <div class="m_t">
                <div><a class="menu" href="{$adminPanel.dashboard_url|escape}"><b>Пульт</b></a></div>
                <div><small><a href="{$adminPanel.queue_url|escape}">На проверке: {$adminPanel.unchecked_torrents}</a></small></div>
                <div><small><a href="{$adminPanel.pending_users_url|escape}">Анкет: {$adminPanel.pending_users}</a></small></div>
                <div><small><a href="{$adminPanel.inbox_url|escape}">ЛС: {$adminPanel.unread}</a> | <a href="{$adminPanel.staff_url|escape}">стафф</a></small></div>
            </div>
        </div>
    </div>
</div>
{/if}
