<?php
require_once("include/bittorrent.php");
dbconn();

stdhead("Вход в систему");
begin_frame("Вы должны войти в систему");

// returnto
$returnto = "";
$showWarn = false;
if (!empty($_GET["returnto"])) {
    $returnto = htmlspecialchars($_GET["returnto"], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $showWarn = !isset($_GET["nowarn"]);
}
?>

<style>
.login-form{max-width:360px;margin:16px auto 8px}
.login-form *{box-sizing:border-box}

.notice,.input,.btn-primary{width:100%;display:block}

.notice{
  margin:0 0 12px 0;padding:12px 14px;border-radius:10px;
  background:rgba(255,80,80,.12);
  border:1px solid rgba(255,80,80,.35);
  color:#b30000;font-size:14px
}
.input{
  padding:12px 14px;margin:0 0 12px 0;border-radius:10px;
  border:1px solid rgba(0,0,0,.2);outline:0;background:#fff;
  transition:border-color .15s, box-shadow .15s
}
.input:focus{border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.2)}
.btn{
  display:inline-block;border:0;border-radius:10px;cursor:pointer;
  padding:12px 14px;font-weight:600;text-align:center
}
.btn-primary{background:#2563eb;color:#fff}
.btn-primary:hover{opacity:.92}
.actions{display:flex;gap:8px;justify-content:center;margin-top:10px}
.btn-ghost{
  flex:1;text-align:center;
  padding:10px;border-radius:10px;
  background:transparent;border:1px solid rgba(0,0,0,.2);font-size:13px
}
.btn-ghost:hover{background:rgba(0,0,0,.04)}
@media (prefers-color-scheme: dark){
  .input{background:#1c1c20;border-color:rgba(255,255,255,.14);color:#fff}
  .btn-ghost{border-color:rgba(255,255,255,.2);color:#fff}
  .btn-ghost:hover{background:rgba(255,255,255,.06)}
  .notice{background:rgba(255,80,80,.14);border-color:rgba(255,80,80,.45)}
}
</style>

<div class="login-form">
  <?php if ($showWarn): ?>
    <div class="notice">Эта страница доступна только зарегистрированным пользователям.</div>
  <?php endif; ?>

  <form id="loginForm" method="post" action="takelogin.php" autocomplete="on">
    <input class="input" type="text" name="username" id="username"
           placeholder="Логин" autocomplete="username" required autofocus>
    <input class="input" type="password" name="password" id="password"
           placeholder="Пароль" autocomplete="current-password" required>

    <?php if (!empty($returnto)): ?>
      <input type="hidden" name="returnto" value="<?= $returnto ?>">
    <?php endif; ?>

    <button type="submit" class="btn btn-primary" id="loginBtn">Войти</button>
  </form>

  <div class="actions">
    <a class="btn btn-ghost" href="recover.php">Забыли пароль?</a>
    <a class="btn btn-ghost" href="signup.php">Регистрация</a>
  </div>
</div>

<script>
// отправка по Enter без «двойного сабмита»
document.getElementById('loginForm').addEventListener('keydown', function(e){
  if (e.key === 'Enter') {
    e.preventDefault();
    document.getElementById('loginBtn').click();
  }
});
</script>

<?php
end_frame();
stdfoot();
