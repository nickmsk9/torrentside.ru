<?php
/* Smarty version 5.5.1, created on 2025-10-03 11:17:01
  from 'file:partials/head_block.tpl' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.5.1',
  'unifunc' => 'content_68df867d738bd0_28521188',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '29c2edb920ecb692132bd1e9dbf4064b4529793a' => 
    array (
      0 => 'partials/head_block.tpl',
      1 => 1759479411,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_68df867d738bd0_28521188 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = 'C:\\OSPanel\\domains\\torrentside.ru\\templates\\partials';
?><!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title><?php echo htmlspecialchars((string) ($_smarty_tpl->getValue('title')), ENT_QUOTES, 'UTF-8');?>
</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Стили -->
    <link rel="stylesheet" href="/styles/engine.css" type="text/css">

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
</head>

<body>
    <!-- Меню -->
    <table width="100%" align="center" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td align="center" style="padding:0; margin:0; border:0;">
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
