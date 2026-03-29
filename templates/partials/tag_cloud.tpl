<div class="menu">
  <div class="m_tags">
    <div class="m_foot">
      <div class="m_t">
        <style>
          #sidebarTags {
            max-width: 100%;
            padding: 12px 10px 10px;
            line-height: 0.95;
            user-select: text;
            box-sizing: border-box;
            overflow: hidden;
            text-align: justify;
            text-justify: inter-word;
            font-family: "Trebuchet MS", Verdana, Arial, sans-serif;
          }
          #sidebarTags:after {
            content: "";
            display: inline-block;
            width: 100%;
          }
          #sidebarTags a.tag,
          #sidebarTags a {
            display: inline-block;
            vertical-align: middle;
            max-width: 100%;
            margin: 0 8px 8px 0 !important;
            text-decoration: none !important;
            white-space: nowrap !important;
            text-align: center !important;
            padding: 0;
            box-sizing: border-box;
            text-shadow: 0 1px 0 rgba(255,255,255,.22);
            transition: color .18s ease, transform .18s ease, text-shadow .18s ease;
          }
          #sidebarTags a.tag:hover,
          #sidebarTags a:hover {
            color: #ffffff !important;
            transform: translateY(-1px);
            text-shadow: 0 0 10px rgba(255,255,255,.65), 0 0 18px rgba(255,255,255,.28);
            text-decoration: underline !important;
            text-decoration-thickness: 1px;
            text-underline-offset: 2px;
          }
          #sidebarTags a.tag:active,
          #sidebarTags a:active {
            transform: translateY(0);
          }
          #sidebarTags a.tag[data-count]:after,
          #sidebarTags a[data-count]:after {
            content: " (" attr(data-count) ")";
            font-size: .44em;
            font-weight: 700;
            color: #d9e2ec;
            vertical-align: middle;
          }
          @media (max-width: 700px) {
            #sidebarTags {
              padding: 10px 8px 8px 8px;
            }
            #sidebarTags a.tag,
            #sidebarTags a {
              margin: 0 7px 7px 0 !important;
            }
          }
          @media (max-width: 480px) {
            #sidebarTags a.tag,
            #sidebarTags a {
              margin: 0 6px 6px 0 !important;
              max-width: 100%;
            }
          }
        </style>

        <div id="sidebarTags">
          {$tagsHtml nofilter}
        </div>
      </div>
    </div>
  </div>
</div>
