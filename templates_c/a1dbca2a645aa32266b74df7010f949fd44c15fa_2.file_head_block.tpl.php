<?php
/* Smarty version 5.5.1, created on 2026-03-29 13:06:13
  from 'file:partials/head_block.tpl' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.5.1',
  'unifunc' => 'content_69c923c593cfe8_13833629',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    'a1dbca2a645aa32266b74df7010f949fd44c15fa' => 
    array (
      0 => 'partials/head_block.tpl',
      1 => 1774789572,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_69c923c593cfe8_13833629 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = '/var/www/html/templates/partials';
?><!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title><?php echo htmlspecialchars((string) ($_smarty_tpl->getValue('title')), ENT_QUOTES, 'UTF-8');?>
</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Стили -->
    <link rel="stylesheet" href="/styles/engine.css?v=<?php echo htmlspecialchars((string) ($_smarty_tpl->getValue('cssVersion')), ENT_QUOTES, 'UTF-8');?>
" type="text/css">

    <!-- Favicon & RSS -->
    <link rel="alternate" type="application/rss+xml" title="Последние торренты" href="<?php echo htmlspecialchars((string) ($_smarty_tpl->getValue('baseUrl')), ENT_QUOTES, 'UTF-8');?>
/rss.php">
    <link rel="shortcut icon" href="<?php echo htmlspecialchars((string) ($_smarty_tpl->getValue('baseUrl')), ENT_QUOTES, 'UTF-8');?>
/favicon.ico" type="image/x-icon">

    <!-- jQuery 3.7.1 -->
    <?php echo '<script'; ?>
 src="/js/jquery-3.7.1.min.js"><?php echo '</script'; ?>
>
    <?php echo '<script'; ?>
>
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
    <?php echo '</script'; ?>
>

    <!-- Прочие скрипты -->
    <?php echo '<script'; ?>
 src="/js/functions.js" defer><?php echo '</script'; ?>
>
    <?php echo '<script'; ?>
 src="/js/show_hide.js" defer><?php echo '</script'; ?>
>
    <?php echo '<script'; ?>
 src="/js/overlib.js" defer><?php echo '</script'; ?>
>
	
	<style>
  /* --- базовый сброс + фон страницы --- */
  html, body { height:100%; margin:0; padding:0; background:#0b1421; }
  table { border-collapse:collapse; border-spacing:0; }
  img { max-width:100%; height:auto; vertical-align:middle; } /* убираем «ступеньку» под картинкой */

  /* --- контейнеры шапки/меню должны красить фон на всю ширину --- */
  #header-wrap, #menucase { width:100%; max-width:100%; margin:0 auto; background-color:#0b1421; }

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
                            <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('navItems'), 'it');
$foreach0DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('it')->value) {
$foreach0DoElse = false;
?>
                                <li>
                                    <a href="<?php echo htmlspecialchars((string) ($_smarty_tpl->getValue('it')['href']), ENT_QUOTES, 'UTF-8');?>
">
                                        <?php if (!( !true || empty($_smarty_tpl->getValue('it')['raw']))) {?>
                                            <?php echo $_smarty_tpl->getValue('it')['label'];?>

                                        <?php } else { ?>
                                            <?php echo htmlspecialchars((string) ($_smarty_tpl->getValue('it')['label']), ENT_QUOTES, 'UTF-8');?>

                                        <?php }?>
                                    </a>
                                </li>
                            <?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
                        </ul>
        </div>
      </div>
    </td>
  </tr>
</table>
<?php }
}
