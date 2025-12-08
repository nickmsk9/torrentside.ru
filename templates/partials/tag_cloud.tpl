<div class="menu">
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
          {$tagsHtml nofilter}
        </div>

        <script>
          (function () {
            var r = document.getElementById('sidebarTags');
            if (!r) return;
            r.querySelectorAll('a:not(.tag)').forEach(a => a.classList.add('tag'));
          })();
        </script>
      </div>
    </div>
  </div>
</div>
