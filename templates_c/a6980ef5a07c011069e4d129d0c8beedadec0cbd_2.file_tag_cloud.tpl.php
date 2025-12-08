<?php
/* Smarty version 5.5.1, created on 2025-10-03 11:13:35
  from 'file:partials/tag_cloud.tpl' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.5.1',
  'unifunc' => 'content_68df85af3f5dc7_15330108',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    'a6980ef5a07c011069e4d129d0c8beedadec0cbd' => 
    array (
      0 => 'partials/tag_cloud.tpl',
      1 => 1759479186,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_68df85af3f5dc7_15330108 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = 'C:\\OSPanel\\domains\\torrentside.ru\\templates\\partials';
?><div class="menu">
  <div class="m_tags">
    <div class="m_foot">
      <div class="m_t">
        <style>
          #sidebarTags {
            --min: 12px;
            --max: 18px;
            display: flex;
            flex-wrap: wrap;
            gap: 6px 8px;
            line-height: 1.25;
            user-select: none;
            max-width: 100%;
            overflow: hidden;
          }
          #sidebarTags a.tag,
          #sidebarTags a {
            font-size: clamp(var(--min),
               calc(var(--min) + (var(--max) - var(--min)) * var(--w, .5)),
               var(--max));
            color: #0073aa;
            text-decoration: none;
            font-weight: 500;
            display: inline;
            white-space: normal;
            word-break: break-word;
            margin: 0;
            padding: 0;
            border-radius: 0;
            transition: color .15s ease;
          }
          #sidebarTags a.tag:hover,
          #sidebarTags a:hover {
            color: #00a0d2;
            text-decoration: underline;
          }
          #sidebarTags a.tag[data-count]::after {
            content: " (" attr(data-count) ")";
            font-size: .85em;
            color: #6b7280;
          }
          @media (max-width: 480px) {
            #sidebarTags { --min: 11px; --max: 16px; gap: 5px 6px; }
          }
        </style>

        <div id="sidebarTags" style="padding:12px 10px 8px 10px;">
          <?php echo $_smarty_tpl->getValue('tagsHtml');?>

        </div>

        <?php echo '<script'; ?>
>
          (function () {
            var r = document.getElementById('sidebarTags');
            if (!r) return;
            r.querySelectorAll('a:not(.tag)').forEach(a => a.classList.add('tag'));
          })();
        <?php echo '</script'; ?>
>
      </div>
    </div>
  </div>
</div>
<?php }
}
