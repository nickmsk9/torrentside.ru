{* показываем блок только если есть непрочитанные *}
{if $inbox.unread > 0}
<style>
.glass-notice {
  position: relative;
  display: inline-flex;
  align-items: center;
  gap: 10px;
  padding: 10px 16px;
  margin: 10px auto;
  border-radius: 14px;
  font: 600 14px/1.2 Verdana, system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
  color: #fff;
  background:
    radial-gradient(120% 120% at 0% 0%, rgba(255,255,255,.14), rgba(255,255,255,.05) 60%, rgba(255,255,255,0) 100%),
    linear-gradient(to right, rgba(153,0,0,.80), rgba(204,0,0,.80));
  box-shadow:
    inset 0 1px 0 rgba(255,255,255,.22),
    0 8px 20px rgba(102, 0, 0, .30);
  border: 1px solid rgba(255,255,255,.22);
  -webkit-backdrop-filter: blur(14px) saturate(140%);
  backdrop-filter: blur(14px) saturate(140%);
  transition: transform .15s ease, box-shadow .2s ease, background .2s ease;
}
@supports not ((-webkit-backdrop-filter: blur(1px)) or (backdrop-filter: blur(1px))) {
  .glass-notice {
    background: linear-gradient(to right, #990000, #cc0000);
  }
}
.glass-notice:hover {
  transform: translateY(-1px);
  box-shadow:
    inset 0 1px 0 rgba(255,255,255,.25),
    0 10px 26px rgba(102, 0, 0, .36);
}
.glass-notice a { color: #fff; text-decoration: none; }
.glass-notice a:focus-visible{
  outline: 2px solid rgba(255,255,255,.9);
  outline-offset: 2px;
  border-radius: 6px;
}
.gn-icon {
  width: 18px; height: 18px; flex: 0 0 18px;
  filter: drop-shadow(0 1px 0 rgba(0,0,0,.25));
}
</style>

<div class="glass-notice" role="status" aria-live="polite">
  <svg class="gn-icon" viewBox="0 0 24 24" aria-hidden="true">
    <path fill="white" d="M12 22a2.5 2.5 0 0 0 2.45-2h-4.9A2.5 2.5 0 0 0 12 22Zm7-6V11a7 7 0 1 0-14 0v5l-2 2v1h20v-1l-2-2Z"/>
  </svg>
  <a href="{$inbox.url|escape:'html'}">{$inbox.new_text|escape:'html'}</a>
</div>
{/if}
