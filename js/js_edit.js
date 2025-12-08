/* dle_compat.js — быстрый шым вместо eval-блоба. Совместимые глобальные имена. */
(function (w, d) {
  "use strict";

  /* ===== базовые переменные (читаем существующие, иначе дефолты) ===== */
  const dle_root = w.dle_root || "";        // например: "/"
  const dle_skin = w.dle_skin || "default"; // если где-то нужно
  const CSRF     = w.dle_csrf || null;      // если у тебя есть токен

  /* ===== утилиты ===== */
  const $id = (s)=>d.getElementById(s);
  const qs  = (s,root=d)=>root.querySelector(s);

  function formEnc(dataObj) {
    const sp = new URLSearchParams();
    for (const [k,v] of Object.entries(dataObj)) {
      if (v !== undefined && v !== null) sp.append(k, v);
    }
    if (CSRF) sp.append('csrf_token', CSRF);
    return sp;
  }

  function post(url, data, {updateId, method='POST'}={}) {
    return fetch(url, {
      method,
      headers: {'X-Requested-With':'XMLHttpRequest','Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},
      body: data instanceof URLSearchParams ? data : formEnc(data)
    }).then(async r=>{
      const t = await r.text();
      if (updateId && $id(updateId)) $id(updateId).innerHTML = t;
      return {ok:r.ok, status:r.status, text:t};
    }).catch(err=>{
      console.warn('AJAX error:', err);
      if (updateId && $id(updateId)) $id(updateId).innerHTML = '<span style="color:red">Ошибка запроса</span>';
      return {ok:false, error:err};
    });
  }

  /* ====== Меню вокруг новости / профиля / IP ====== */
  // Возвращают массив HTML-ссылок как раньше.
  w.MenuNewsBuild = function(newsId) {
    const arr = [];
    arr[0] = `<a href="${dle_root}index.php?do=editnews&action=edit&news_id=${newsId}" target="_blank">Редактировать</a>`;
    arr[1] = `<a href="${dle_root}index.php?do=editnews&action=yes&news_id=${newsId}" target="_blank">Быстрая правка</a>`;
    return arr;
  };

  w.MenuCommBuild = function(commId) {
    const arr = [];
    arr[0] = `<a href="#" onclick="ajax_comm_edit('${commId}'); return false;">Править</a>`;
    arr[1] = `<a href="${dle_root}?do=comments&action=edit&comm_id=${commId}">Полная правка</a>`;
    return arr;
  };

  w.UserMenu = function(userId) {
    const arr = [];
    arr[0] = `<a href="${dle_root}index.php?do=pm&doaction=newpm&user=${userId}">ЛС</a>`;
    arr[1] = `<a href="${dle_root}index.php?subaction=allnews&user=${userId}">Все новости</a>`;
    arr[2] = `<a href="${dle_root}index.php?do=lastcomments&userid=${userId}">Комментарии</a>`;
    return arr;
  };

  w.IPMenu = function(ip) {
    // whois и блокировка (пример)
    const arr = [];
    arr[0] = `<a href="https://nic.ru/whois/?searchWord=${encodeURIComponent(ip)}" target="_blank">WHOIS</a>`;
    arr[1] = `<a href="${dle_root}index.php?do=editusers&action=blockip&ip=${encodeURIComponent(ip)}" target="_blank">Заблокировать IP</a>`;
    return arr;
  };

  /* ====== Рейтинг / избранное ====== */
  w.doFavorites = function(fav_id, mode='short') {
    // mode: 'short'|'full' — куда обновлять
    const target = mode === 'full' ? 'favorites-full' : 'favorites';
    return post(`${dle_root}engine/ajax/favorites.php`, {fav_id})
      .then(res => {
        if (!res.ok) return;
        if ($id(`fav-${fav_id}`)) $id(`fav-${fav_id}`).classList.toggle('is-fav');
      });
  };

  w.doRate = function(news_id, rating, mode='short') {
    const box = mode === 'short' ? `ratig-${news_id}` : `rating-${news_id}`;
    return post(`${dle_root}engine/ajax/rating.php`, {news_id, rating}, {updateId: box});
  };

  /* ====== Добавление комментариев ====== */
  w.doAddComments = function(formId='comment-form') {
    const f = $id(formId) || qs('form#comment-form');
    if (!f) { alert('Форма не найдена'); return; }

    const required = ['name','comments']; // под себя настроить
    for (const r of required) {
      if (f[r] && !f[r].value.trim()) { alert('Заполните обязательные поля'); return; }
    }

    const data = new FormData(f);
    const sp = new URLSearchParams();
    data.forEach((v,k)=>sp.append(k,v));
    if (CSRF) sp.append('csrf_token', CSRF);

    return fetch(`${dle_root}engine/ajax/addcomments.php`, {
      method:'POST',
      headers:{'X-Requested-With':'XMLHttpRequest'},
      body: sp
    }).then(r=>r.text()).then(html=>{
      const box = $id('comments-box');
      if (box) box.insertAdjacentHTML('beforeend', html);
      if (f.reset) f.reset();
    }).catch(err=>{
      console.warn(err); alert('Не удалось отправить комментарий');
    });
  };

  /* ====== Быстрая правка комментария ====== */
  w.ajax_comm_edit = function (comm_id) {
    return post(`${dle_root}engine/ajax/dleeditcomments.php`, {comm_id, action:'edit'}, {updateId:`comm-${comm_id}`});
  };
  w.ajax_save_comm_edit = function (comm_id) {
    const area = $id(`comm_txt${comm_id}`) || $id(`comm_txt-${comm_id}`);
    if (!area) return;
    return post(`${dle_root}engine/ajax/dleeditcomments.php`, {comm_id, action:'save', comments:area.value}, {updateId:`comm-${comm_id}`});
  };
  w.ajax_cancel_comm_edit = function (comm_id) {
    return post(`${dle_root}engine/ajax/dleeditcomments.php`, {comm_id, action:'cancel'}, {updateId:`comm-${comm_id}`});
  };

  /* ====== Правка новости (заглушки под твой backend, если нужно — перепривяжем) ====== */
  w.ajax_prep_for_edit = function (news_id, where='full') {
    return post(`${dle_root}engine/ajax/dleeditnews.php`, {news_id, action:'prep', where}, {updateId:`news-${news_id}`});
  };
  w.ajax_save_for_edit = function (news_id) {
    const ta = $id(`news_txt${news_id}`); if (!ta) return;
    return post(`${dle_root}engine/ajax/dleeditnews.php`, {news_id, action:'save', text:ta.value}, {updateId:`news-${news_id}`});
  };
  w.ajax_cancel_for_edit = function (news_id) {
    return post(`${dle_root}engine/ajax/dleeditnews.php`, {news_id, action:'cancel'}, {updateId:`news-${news_id}`});
  };

  /* ====== «Цитировать» в форму ответа ====== */
  w.dle_copy_quote = function (post_id) {
    let sel = '';
    if (w.getSelection) sel = String(w.getSelection()).trim();
    if (sel) sel = `[quote=${post_id}]${sel}[/quote]\n`;
    const ta = $id('comments') || qs('textarea[name="comments"]');
    if (ta) {
      ta.focus();
      ta.value += sel || `[b]${post_id}[/b],\n`;
    }
  };

  /* ====== Вставка смайла/BB — универсально ====== */
  w.dle_smiley = function (text) {
    if (!text) return;
    const ta = $id('comments') || qs('textarea[name="comments"]');
    if (!ta) return;
    ta.focus();
    // если есть WYSIWYG (CKE/TinyMCE) — можно расширить через editor API
    ta.value += text;
  };

  /* ====== Массовые чекбоксы ====== */
  w.ckeck_uncheck_all = function () {
    const f = d.forms[0]; if (!f) return;
    const boxes = f.elements;
    const master = qs('input[type=checkbox].js-master') || boxes['master_box'];
    for (let i=0;i<boxes.length;i++){
      const b = boxes[i];
      if (b.type === 'checkbox' && b !== master) b.checked = master ? master.checked : !b.checked;
    }
    if (master) master.checked = !!master.checked;
  };

  /* ====== Календарь / превью изображения — заглушки (легко заменить) ====== */
  w.doCalendar = function(year, month) {
    return post(`${dle_root}engine/ajax/calendar.php`, {year, month}, {updateId:'ajax-calendar'});
  };

  w.ShowBild = function (file) {
    const wopts = 'resizable=1,scrollbars=1,width=540,height=500,top=0,left=0';
    w.open(`${dle_root}engine/modules/imagepreview.php?image=${encodeURIComponent(file)}`, '_blank', wopts);
  };

  /* ====== Логирование неизвестных вызовов (чтоб ничего не падало) ====== */
  function stub(name) {
    return function () {
      console.warn(`[dle_compat] вызов ${name}() не используется/не реализован`, arguments);
      return false;
    };
  }

  // Если где-то зовут эти имена — не упадём.
  [
    'whenCompletedSave','whenCompleted','whenCompletedCommentsEdit',
    'ajax_prep_for_edit_full','MenuProfile','MenuCommBuildFull',
    'MenuNewsBuildFull','UserNewsMenu'
  ].forEach(n => { if (!(n in w)) w[n] = stub(n); });

})(window, document);
