<?php
/* Smarty version 5.5.1, created on 2025-10-03 11:14:14
  from 'file:partials/inbox_notice.tpl' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.5.1',
  'unifunc' => 'content_68df85d60cb6b2_01058937',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '616f20b65d2c97d8f2822ef9be19bda36483f694' => 
    array (
      0 => 'partials/inbox_notice.tpl',
      1 => 1759479252,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_68df85d60cb6b2_01058937 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = 'C:\\OSPanel\\domains\\torrentside.ru\\templates\\partials';
if ($_smarty_tpl->getValue('inbox')['unread'] > 0) {?>
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
  <a href="<?php echo htmlspecialchars((string) ($_smarty_tpl->getValue('inbox')['url']), ENT_QUOTES, 'UTF-8');?>
"><?php echo htmlspecialchars((string) ($_smarty_tpl->getValue('inbox')['new_text']), ENT_QUOTES, 'UTF-8');?>
</a>
</div>
<?php }
}
}
