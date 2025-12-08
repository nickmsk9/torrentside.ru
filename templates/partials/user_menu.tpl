<div class="menu">
  <div class="m_login">
    <div class="m_foot">
      <div class="m_t">
        <div class="m_hi">

          {if $userMenu.loggedin}
            <!-- приветствие + ник -->
            <div class="greet">
              <span id="greet-text"></span>
              <b>
                <a href="userdetails.php?id={$userMenu.id}">
                  {$userMenu.username nofilter}
                </a>
              </b>
            </div>

            <!-- аватар -->
            <div class="user-avatar" style="margin:6px 0;text-align:center">
              <a href="my.php">
                <img src="{$userMenu.avatar}" alt="Аватар"
                     style="max-width:60px;max-height:60px;border-radius:50%;box-shadow:0 0 4px rgba(0,0,0,0.3)">
              </a>
            </div>

            <!-- статистика -->
            <span style="color:#FFD700">Рейтинг:</span> {$userMenu.ratio}<br>
            <span style="color:#ADFF2F">Раздал:</span> {$userMenu.uploaded}<br>
            <span style="color:#FF0000">Скачал:</span> {$userMenu.download}<br>
            <span style="color:#00BFFF">Бонус:</span>
            <a href="mybonus.php" class="online">
              <span class="js-bonus-balance">{$userMenu.bonus}</span>
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

          {else}
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
          {/if}

        </div>
      </div>
    </div>
  </div>
</div>

<!-- приветствие и автообновление бонусов -->
<script>
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
</script>
