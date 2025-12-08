<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>{$title}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Стили -->
    <link rel="stylesheet" href="/styles/engine.css" type="text/css">

    <!-- Favicon & RSS -->
    <link rel="alternate" type="application/rss+xml" title="Последние торренты" href="{$baseUrl}/rss.php">
    <link rel="shortcut icon" href="{$baseUrl}/favicon.ico" type="image/x-icon">

    <!-- jQuery 3.7.1 -->
    <script src="/js/jquery-3.7.1.min.js"></script>
    <script>
      window.$jq = jQuery;
      $jq.browser = {};
      (function () {
          var ua = navigator.userAgent.toLowerCase();
          var match = /(chrome)[ \/]([\w.]+)/.exec(ua) ||
                      /(webkit)[ \/]([\w.]+)/.exec(ua) ||
                      /(opera)(?:.*version|)[ \/]([\w.]+)/.exec(ua) ||
                      /(msie) ([\w.]+)/.exec(ua) ||
                      ua.indexOf("trident") >= 0 && /(rv)(?::| )([\w.]+)/.exec(ua) ||
                      ua.indexOf("mozilla") >= 0 && ua.indexOf("compatible") < 0 && /(mozilla)(?:.*? rv:([\w.]+)|)/.exec(ua) || [];
          if (match[1]) $jq.browser[match[1]] = true;
      })();
    </script>

    <!-- Прочие скрипты -->
    <script src="/js/functions.js" defer></script>
    <script src="/js/show_hide.js" defer></script>
    <script src="/js/overlib.js" defer></script>
	<script src="/js/scroll-dock.js" defer></script>
	
	<style>
  /* --- базовый сброс + фон страницы --- */
  html, body { height:100%; margin:0; padding:0; background:#0b1421; }
  table { border-collapse:collapse; border-spacing:0; }
  img { max-width:100%; height:auto; vertical-align:middle; } /* убираем «ступеньку» под картинкой */

  /* --- контейнеры шапки/меню должны красить фон на всю ширину --- */
  #header-wrap, #menucase { width:100%; max-width:100%; margin:0 auto; background-color:#0b1421; }

  /* --- меню: без отступов у UL, кликабельная зона по высоте --- */
  #stylefour ul { margin:0; padding:0; list-style:none; display:flex; flex-wrap:wrap; }
  #stylefour ul li { display:block; }
  #stylefour a { display:block; padding:10px 12px; font-size:16px; -webkit-text-size-adjust:100%; }

  /* --- сама «лента» шапки: страхуемся от 1px-щелей на iOS --- */
  #header-wrap {
    min-height:146px;
    background-repeat:repeat-x;
    background-position: top center;
    background-size: auto 146px; /* фиксируем высоту повторяющегося бэкграунда */
    position: relative;
    transform: translateZ(0); /* заставляем Safari красить без швов */
  }

  /* «уголки» шапки — пусть всегда 146×146 и поверх ленты */
  #header-wrap .corner-left,
  #header-wrap .corner-right {
    position:absolute; top:0; width:146px; height:146px; pointer-events:none;
    background-repeat:no-repeat; background-size:146px 146px;
  }
  #header-wrap .corner-left  { left:0;  background-position:top left;  }
  #header-wrap .corner-right { right:0; background-position:top right; }

  /* --- iOS safe area (чтоб не появлялась белая полоска под статус-баром) --- */
  @supports (-webkit-touch-callout: none) {
    body { padding-top: env(safe-area-inset-top); }
  }
</style>
	
	
	
</head>

<body>
    <!-- Меню -->
    <table width="100%" align="center" cellpadding="0" cellspacing="0" border="0" style="background:#0b1421;">
  <tr>
    <td align="center" style="padding:0; margin:0; border:0; background:#0b1421;">
      <div id="menucase">
        <div id="stylefour">
                        <ul>
                            {foreach $navItems as $it}
                                <li>
                                    <a href="{$it.href|escape}">
                                        {if !empty($it.raw)}
                                            {$it.label nofilter}
                                        {else}
                                            {$it.label|escape}
                                        {/if}
                                    </a>
                                </li>
                            {/foreach}
                        </ul>
        </div>
      </div>
    </td>
  </tr>
</table>
