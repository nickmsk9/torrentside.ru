<?php
/* Smarty version 5.5.1, created on 2025-10-03 11:10:50
  from 'file:partials/user_menu.tpl' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.5.1',
  'unifunc' => 'content_68df850a896bf5_53058101',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    'fcabd4d3a34a2184bbae9df1e799eb70a81371cd' => 
    array (
      0 => 'partials/user_menu.tpl',
      1 => 1759478791,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_68df850a896bf5_53058101 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = 'C:\\OSPanel\\domains\\torrentside.ru\\templates\\partials';
?><div class="menu">
  <div class="m_login">
    <div class="m_foot">
      <div class="m_t">
        <div class="m_hi">

          <?php if ($_smarty_tpl->getValue('userMenu')['loggedin']) {?>
            <!-- приветствие + ник -->
            <div class="greet">
              <span id="greet-text"></span>
              <b>
                <a href="userdetails.php?id=<?php echo htmlspecialchars((string) ($_smarty_tpl->getValue('userMenu')['id']), ENT_QUOTES, 'UTF-8');?>
">
                  <?php echo $_smarty_tpl->getValue('userMenu')['username'];?>

                </a>
              </b>
            </div>

            <!-- аватар -->
            <div class="user-avatar" style="margin:6px 0;text-align:center">
              <a href="my.php">
                <img src="<?php echo htmlspecialchars((string) ($_smarty_tpl->getValue('userMenu')['avatar']), ENT_QUOTES, 'UTF-8');?>
" alt="Аватар"
                     style="max-width:60px;max-height:60px;border-radius:50%;box-shadow:0 0 4px rgba(0,0,0,0.3)">
              </a>
            </div>

            <!-- статистика -->
            <span style="color:#FFD700">Рейтинг:</span> <?php echo htmlspecialchars((string) ($_smarty_tpl->getValue('userMenu')['ratio']), ENT_QUOTES, 'UTF-8');?>
<br>
            <span style="color:#ADFF2F">Раздал:</span> <?php echo htmlspecialchars((string) ($_smarty_tpl->getValue('userMenu')['uploaded']), ENT_QUOTES, 'UTF-8');?>
<br>
            <span style="color:#FF0000">Скачал:</span> <?php echo htmlspecialchars((string) ($_smarty_tpl->getValue('userMenu')['download']), ENT_QUOTES, 'UTF-8');?>
<br>
            <span style="color:#00BFFF">Бонус:</span>
            <a href="mybonus.php" class="online">
              <span class="js-bonus-balance"><?php echo htmlspecialchars((string) ($_smarty_tpl->getValue('userMenu')['bonus']), ENT_QUOTES, 'UTF-8');?>
</span>
            </a><br>

            <!-- выход -->
            <div class="out" style="margin-top:6px">
              <a href="logout.php" onclick="showBusyLayer()"><b>Завершить сеанс!</b></a>
            </div>

            <!-- поиск -->
            <form method="get" action="browse.php" style="margin-top:8px">
              <input type="hidden" name="do" value="search">
              <input type="hidden" name="subaction" value="search">
              <input type="search" name="search" id="search-q"
                     class="login_input" placeholder="Поиск" autocomplete="off">
            </form>

          <?php } else { ?>
            <!-- форма для гостей -->
            <form method="post" action="takelogin.php">
              <input type="text" name="username" class="login_input"
                     placeholder="Логин" autocomplete="username" required>
              <input type="password" name="password" class="login_input"
                     placeholder="Пароль" autocomplete="current-password" required>
              <div align="center" style="margin-top:6px">
                <button type="submit" class="btn_go" title="Войти"
                        style="border:0;background:none;cursor:pointer">
                  <img src="styles/images/login_send.gif" alt="Отправить">
                </button>
              </div>
              <input type="hidden" name="login" value="submit">
            </form>

            <div style="margin-top:6px">
              <a href="/signup.php">Регистрация на сайте!</a><br>
              <a href="/recover.php">Напомнить пароль?</a><br>
            </div>
          <?php }?>

        </div>
      </div>
    </div>
  </div>
</div>

<!-- приветствие и автообновление бонусов -->
<?php echo '<script'; ?>
>
(function(){
  try{
    var h=new Date().getHours();
    var t=(h>=6&&h<12)?"Доброе утро,":(h>=12&&h<18)?"Добрый день,":(h>=18)?"Добрый вечер,":"Доброй ночи,";
    var el=document.getElementById('greet-text'); if(el) el.textContent=t;

    // обновление бонуса по событию
    window.addEventListener('bonus:update', function(e){
      var v=(e.detail&&typeof e.detail.bonus!=='undefined')? e.detail.bonus : null;
      if (v===null) return;
      document.querySelectorAll('.js-bonus-balance').forEach(function(n){ n.textContent=String(v); });
    });
  }catch(e){}
})();
<?php echo '</script'; ?>
>
<?php }
}
