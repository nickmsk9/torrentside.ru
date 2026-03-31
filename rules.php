<?php
declare(strict_types=1);

require __DIR__ . "/include/bittorrent.php";

gzip();
dbconn();

stdhead("Правила");

/** Немного аккуратных стилей прямо на странице */
?>
<style>
  :root{
    --ink:#0f172a;        /* заголовки */
    --text:#334155;       /* основной текст */
    --muted:#64748b;
    --ok:#10b981;
    --warn:#f59e0b;
    --bad:#ef4444;
    --pill:#004E98;
    --bg:#f8fafc;
    --card:#ffffff;
    --border:#e2e8f0;
  }
  .rules-wrap{max-width: 1100px; margin: 8px auto 16px; font: 14px/1.55 system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Arial,sans-serif; color:var(--text)}
  .rules-note{display:flex; gap:.75rem; padding:10px 12px; background:var(--bg); border:1px solid var(--border); border-radius:12px; margin-bottom:10px}
  .rules-note b{color:var(--ink)}
  .rules-toc{display:flex; flex-wrap:wrap; gap:8px; margin:12px 0 18px}
  .rules-toc a{display:inline-block; padding:6px 10px; border:1px solid var(--border); background:var(--card); border-radius:999px; text-decoration:none; color:var(--pill); font-weight:600}
  .rules h2{margin:0 0 8px; color:var(--ink); font-size:18px}
  .rules h3{margin:12px 0 6px; color:var(--ink); font-size:16px}
  .rules ul{margin:6px 0 10px 18px}
  .rules li{margin:4px 0}
  .badge{display:inline-block; font-size:11px; padding:2px 7px; border-radius:8px; border:1px solid var(--border); color:#0f172a; background:#fff}
  .badge.ok{border-color:#bbf7d0; background:#f0fdf4; color:#166534}
  .badge.warn{border-color:#fde68a; background:#fffbeb; color:#92400e}
  .badge.bad{border-color:#fecaca; background:#fef2f2; color:#991b1b}
  .kbd{font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, "Liberation Mono", monospace; padding:0 6px; border:1px solid var(--border); border-bottom-width:2px; border-radius:6px; background:#fff; font-size:12px}
  .def{color:var(--muted)}
  .hr{height:1px; background:var(--border); margin:10px 0}
  .small{color:var(--muted); font-size:12px}
  /* аккуратные маркеры в списках */
  .rules ul li::marker{color:#94a3b8}
  /* ссылки-якоря мягче подсвечиваем при переходе */
  :target{scroll-margin-top:80px}
</style>

<?php 

begin_frame("Сводка");
?>
<div class="rules-wrap">
  <div class="rules-note">
    <div>📌</div>
    <div>
      <b>Кратко:</b> правила обязательны для всех пользователей трекера. Незнание не освобождает от ответственности.
      Рекомендовано сохранить страницу в закладки.
    </div>
  </div>

  <div class="rules-toc">
    <a href="#general">Общие</a>
    <a href="#account">Учетная запись</a>
    <a href="#behavior">Поведение и чат</a>
    <a href="#upload">Раздачи и оформление</a>
    <a href="#ratio">Скачивание и Ratio</a>
    <a href="#mods">Модераторы</a>
    <a href="#penalties">Предупреждения и санкции</a>
    <a href="#report">Жалобы и связь</a>
    <a href="#terms">Термины</a>
  </div>
</div>


<?php  end_frame();
?>

<?php
begin_main_frame("");
?>

<?php begin_frame('<span id="general"></span>Общие положения <span class="badge">обязательно к прочтению</span>'); ?>
<div class="rules">
  <ul>
    <li>Трекер предназначен исключительно для обмена .torrent-файлами и метаданными. Коммерческая деятельность запрещена.</li>
    <li>Запрещены публикации, нарушающие законодательство вашей страны/хостинга, а также <b>вирусы/вредоносные</b> материалы (<span class="badge bad">бан</span>).</li>
    <li>Администрация оставляет за собой право изменять правила. <span class="small">Дата актуализации: <?= htmlspecialchars(date('d.m.Y')) ?></span></li>
    <li>Игнорирование требований команды трекера — основание для ограничений доступа.</li>
  </ul>
</div>
<?php end_frame(); ?>

<?php begin_frame('<span id="account"></span>Учетная запись и безопасность'); ?>
<div class="rules">
  <ul>
    <li>Один человек — один аккаунт. Клоны без согласования удаляются. (<span class="badge warn">исключения по заявке</span>)</li>
    <li>Запрещено передавать аккаунт третьим лицам, продавать/покупать инвайты.</li>
    <li>Используйте сложный пароль, включите уведомления на почту. При подозрении на взлом — немедленно смените пароль.</li>
    <li>Запрещены ники/аватары с оскорблениями, разжиганием ненависти, NSFW без маркировки.</li>
  </ul>
</div>
<?php end_frame(); ?>

<?php begin_frame('<span id="behavior"></span>Поведение на сайте и в чате'); ?>
<div class="rules">
  <ul>
    <li>Уважайте участников. Запрещены оскорбления, травля, спам, флуд, политагитация и т.п. (<span class="badge warn">мут/варн</span>)</li>
    <li>Не публикуйте личные данные без согласия. Не выдавайте себя за админов/модеров.</li>
    <li>Язык общения — понятный большинству темы/раздела (RU/EN). Читайте закрепы.</li>
    <li>Реклама сторонних ресурсов — только с согласования администрации.</li>
  </ul>
</div>
<?php end_frame(); ?>

<?php begin_frame('<span id="upload"></span>Раздачи и оформление (для всех)'); ?>
<div class="rules">
  <h3>Минимальные требования <span class="badge ok">чек-лист</span></h3>
  <ul>
    <li>Название: без «капса», с указанием версии/качества/года при необходимости.</li>
    <li>Описание: кратко и по делу (жанр, автор/студия/издатель, версия/сборка, язык, системные требования/форматы).</li>
    <li>Теги: используйте заданные категории/префиксы трекера.</li>
    <li>Скриншоты/обложка (если применимо): не менее 2–3, без водяных знаков посторонних трекеров.</li>
    <li>Тех.качество: без битых файлов, по возможности — проверка хешей. Наличие файлов NFO/README приветствуется.</li>
    <li>Дубли: перед заливкой проверьте поиск. Дубликаты объединяются или удаляются.</li>
  </ul>
  <div class="hr"></div>
  <h3>Запрещено</h3>
  <ul>
    <li>Пароленные архивы без явного указания пароля в описании.</li>
    <li>Скрытая майнинг-активность, кряки с вредоносными инжектами, «репаки» с тулбарами/адварью (<span class="badge bad">пермабан</span>).</li>
    <li>Лички/ссылки на сторонние файлообменники вместо нормальной раздачи.</li>
  </ul>
  <div class="hr"></div>
  <h3>Поддержка раздачи</h3>
  <ul>
    <li>Сидер обязан поддерживать раздачу в первые 72 часа (<span class="badge">реком.</span> не менее 1:1 или 48 ч онлайн).</li>
    <li>Если раздача «умерла», просим апнуть тему, запросить помощь в чате или оформить повтор при согласовании.</li>
  </ul>
</div>
<?php end_frame(); ?>

<?php begin_frame('<span id="ratio"></span>Скачивание и соотношение (Ratio)'); ?>
<div class="rules">
  <ul>
    <li>Следите за <b>ratio</b> — отношением <i>отдано/скачано</i>. Критично низкое ratio ведёт к ограничениям скорости/доступа.</li>
    <li>Рекомендуемый минимум: <span class="badge ok">1.0</span>, для новых аккаунтов действует льготный период.</li>
    <li>Запрещены любые способы «накрутки»: чит-клиенты, правка статистики, мультиаккаунты для перелива трафика (<span class="badge bad">бан</span>).</li>
    <li>Нужна помощь с поднятием ratio — используйте раздел «Роздачи для раздачи»/бонус-систему (если включена).</li>
  </ul>
</div>
<?php end_frame(); ?>

<?php
/* ВОТ ЭТО — ваш исходный раздел, мы красиво вписали его в общую структуру */
begin_frame('<span id="mods"></span>Обязанности модераторов <span class="badge warn">за несоблюдение — понижение</span>');
?>
<div class="rules">
  <ul>
    <li>Проверять новые раздачи и контролировать качество старых.</li>
    <li>Удалять/редактировать неоформленные и «мёртвые» торренты по регламенту.</li>
    <li>Помогать новичкам с оформлением и правилами публикации.</li>
    <li>Следить за порядком на трекере и в чате, пресекать флуд/оскорбления/спам.</li>
    <li>Посещать трекер не реже <b>2 раз в неделю</b>.</li>
    <li>Заливать не менее <b>1 раздачи в неделю</b> (или курировать соответствующий раздел).</li>
    <li>В спорных случаях — фиксировать решения в мод-журнале для прозрачности.</li>
  </ul>
</div>
<?php end_frame(); ?>

<?php begin_frame('<span id="penalties"></span>Система предупреждений и санкции'); ?>
<div class="rules">
  <ul>
    <li><span class="badge ok">Замечание</span> — устное предупреждение, подсказка по оформлению.</li>
    <li><span class="badge warn">Варн (1–7 дней)</span> — за повторные нарушения, токсичность, оффтоп, спам.</li>
    <li><span class="badge warn">Ограничение</span> — чтение без возможности скачивания при критичном ratio.</li>
    <li><span class="badge bad">Бан</span> — за грубые нарушения (мошенничество, вредоносный софт, слив персональных данных и т.п.).</li>
  </ul>
  <div class="small">Санкции назначаются с учётом истории нарушений и контекста. Обжалование — см. ниже.</div>
</div>
<?php end_frame(); ?>

<?php begin_frame('<span id="report"></span>Жалобы, апелляции и связь с командой'); ?>
<div class="rules">
  <ul>
    <li>Используйте кнопку «Пожаловаться» на раздаче/комментарии или пишите в ЛС модераторам/админам.</li>
    <li>Апелляции на санкции подаются через личные сообщения со скриншотами/доказательствами.</li>
    <li>Технические баги — оформляйте по форме: шаги воспроизведения, ожидание/факт, окружение.</li>
  </ul>
</div>
<?php end_frame(); ?>

<?php begin_frame('<span id="terms"></span>Термины'); ?>
<div class="rules">
  <ul>
    <li><span class="kbd">Ratio</span> — соотношение отданного к скачанному.</li>
    <li><span class="kbd">Сид</span>/<span class="kbd">Лич</span> — участник, который раздаёт/качаёт соответственно.</li>
    <li><span class="kbd">Мёртвая раздача</span> — без сидов в течение длительного периода <span class="def">(обычно 14+ дней)</span>.</li>
    <li><span class="kbd">Дубль</span> — раздача, совпадающая по содержанию и качеству с уже существующей.</li>
  </ul>
</div>
<?php end_frame(); ?>

<?php
end_main_frame();

?>
<div class="rules-wrap small">Последнее обновление правил: <?= htmlspecialchars(date('d.m.Y')) ?> • По всем вопросам — свяжитесь с модератором вашего раздела.</div>
<?php

stdfoot();
