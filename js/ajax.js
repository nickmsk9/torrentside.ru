<script>
/* ====================== utils ====================== */
(function () {
  "use strict";

  /* быстрый offsetTop без аллокаций */
  window._get_obj_toppos = function (el) {
    let top = 0;
    for (let n = el; n; n = n.offsetParent) top += n.offsetTop || 0;
    return top;
  };

  /* ====================== центрирование оверлея ====================== */
  function center_div() {
    this.divname = "";
    this.divobj = null;
  }
  center_div.prototype._ensure = function () {
    if (!this.divobj && this.divname) this.divobj = document.getElementById(this.divname);
    return !!this.divobj;
  };
  center_div.prototype.clear_div = function () {
    if (this._ensure()) this.divobj.style.display = "none";
  };
  center_div.prototype.Ywindow = function () {
    // самый дешёвый способ получить прокрутку
    return window.pageYOffset || document.documentElement.scrollTop || document.body.scrollTop || 0;
  };
  center_div.prototype.move_div = function () {
    if (!this._ensure()) return;

    // размеры вьюпорта
    const vw = window.innerWidth  || document.documentElement.clientWidth  || document.body.clientWidth  || 0;
    const vh = window.innerHeight || document.documentElement.clientHeight || document.body.clientHeight || 0;

    // реальные габариты блока (а не style.Width/Height)
    // если display:none — временно показать, чтобы измерить
    const wasHidden = getComputedStyle(this.divobj).display === "none";
    if (wasHidden) this.divobj.style.display = "block";
    const rect = this.divobj.getBoundingClientRect();
    const divW = Math.max( Math.round(rect.width  || 200), 10);
    const divH = Math.max( Math.round(rect.height || 50 ), 10);
    if (wasHidden) this.divobj.style.display = "none";

    const scrolly = this.Ywindow();
    const left = Math.max( (vw - divW) / 2, 0 );
    const top  = Math.max( (vh - divH) / 2 + scrolly, 0 );

    const s = this.divobj.style;
    s.position = "absolute";
    s.display  = "block";
    s.zIndex   = "99";
    s.left     = left + "px";
    s.top      = top  + "px";
  };

  window.center_div = center_div;

  /* ====================== ajax (совместимый API) ====================== */
  function TbdevAjax(file) {
    this.AjaxFailedAlert = "Не удалось выполнить запрос.\nПроверьте соединение или повторите позже.";
    this.requestFile = file || "";
    this.method = "POST";
    this.URLString = "";
    this.encodeURIString = true;
    this.execute = false; // оставляем поле для совместимости, но eval больше не вызываем
    this.loading_fired = 0;
    this.centerdiv = null;

    // колбэки
    this.onLoading = function () {};
    this.onLoaded = function () {};
    this.onInteractive = function () {};
    this.onCompletion = function () {};
    this.onShow = (message) => {
      if (this.loading_fired) return;
      this.loading_fired = 1;
      if (message) {
        const t = document.getElementById("loading-layer-text");
        if (t) t.textContent = message;
      }
      this.centerdiv = new center_div();
      this.centerdiv.divname = "loading-layer";
      this.centerdiv.move_div();
    };
    this.onHide = () => {
      if (this.centerdiv && this.centerdiv.divobj) this.centerdiv.clear_div();
      this.loading_fired = 0;
    };

    // внутренний XHR
    this.xmlhttp = new XMLHttpRequest();
    this.failed = !this.xmlhttp;

    // куда выводить HTML-ответ
    this.element = null;
    this.elementObj = null;
  }

  // аккуратная сборка параметров
  TbdevAjax.prototype.setVar = function (name, value) {
    if (!this._params) this._params = new URLSearchParams();
    // value может быть base64 и т.п., не трогаем
    this._params.append(name, value);
    this.URLString = this._params.toString();
  };

  // совместимость со старым кодом
  TbdevAjax.prototype.encVar = function (name, value) {
    return encodeURIComponent(name) + "=" + encodeURIComponent(value);
  };
  TbdevAjax.prototype.encodeURLString = function (s) {
    // нормализуем возможные "amp;"
    return String(s)
      .split("&")
      .map(pair => {
        const [k, v=""] = pair.replace(/amp;/g,"").split("=");
        return this.encVar(k, v);
      })
      .join("&");
  };

  // БЕЗ eval — поле execute остаётся, но игнорируется.
  TbdevAjax.prototype.runResponse = function () {/* noop for safety */};

  TbdevAjax.prototype.sendAJAX = function (extra) {
    this.responseStatus = [0, ""];
    if (this.failed) {
      if (this.AjaxFailedAlert) alert(this.AjaxFailedAlert);
      return;
    }

    // собрать итоговую строку параметров
    if (extra) {
      if (!this.URLString) this.URLString = String(extra);
      else this.URLString += "&" + String(extra);
    }
    if (this.encodeURIString && this.URLString) {
      this.URLString = this.encodeURLString(this.URLString);
    }

    if (this.element) this.elementObj = document.getElementById(this.element);

    const xhr = this.xmlhttp;
    const self = this;

    // подготовить запрос
    if ((this.method || "POST").toUpperCase() === "GET") {
      const q = this.URLString ? (this.requestFile.indexOf("?") === -1 ? "?" : "&") + this.URLString : "";
      xhr.open("GET", this.requestFile + q, true);
    } else {
      xhr.open("POST", this.requestFile, true);
      try { xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded; charset=UTF-8"); } catch(e){}
    }

    xhr.onreadystatechange = function () {
      switch (xhr.readyState) {
        case 1: self.onLoading(); break;
        case 2: self.onLoaded(); break;
        case 3: self.onInteractive(); break;
        case 4:
          self.response = xhr.responseText;
          self.responseXML = xhr.responseXML;
          self.responseStatus[0] = xhr.status;
          self.responseStatus[1] = xhr.statusText;

          // обновление элемента, как раньше
          if (self.elementObj) {
            self.onHide();
            const tag = self.elementObj.nodeName.toLowerCase();
            if (self.response === "error") {
              alert("Произошла ошибка");
            } else if (tag === "input" || tag === "select" || tag === "option" || tag === "textarea") {
              self.elementObj.value = self.response;
            } else {
              self.elementObj.innerHTML = self.response;
            }
          }

          self.onCompletion();
          // очистка собранных параметров
          self._params = null;
          self.URLString = "";
          break;
      }
    };

    // показать лоадер до отправки
    this.onShow("");

    xhr.send((this.method || "POST").toUpperCase() === "GET" ? null : (this.URLString || ""));
  };

  // экспорт совместимого конструктора в глобал
  window.tbdev_ajax = TbdevAjax;

  /* ====================== хелперы из проекта ====================== */
  function _focusAppend(formIndex, field) {
    const f = document.forms[formIndex || 0];
    if (!f || !f[field]) return;
    const el = f[field];
    el.value += "";
    el.focus();
  }

  // API, как есть, но без лишних перерисовок
  window.AddText = function (text) {
    const f = document.forms[0];
    if (!f || !f.msg) return;
    f.msg.value += text;
    f.msg.focus();
  };
  window.emo = function (emo) {
    const f = document.forms[0];
    if (!f || !f.msg) return;
    f.msg.value += emo;
    f.msg.focus();
  };

  /* ====================== конкретные вызовы (без изменений API) ====================== */
  window.check = function (torrent) {
    const ajax = new tbdev_ajax("checker.php");
    ajax.method = "POST";
    ajax.element = "moderated";
    ajax.setVar("torrent", torrent);
    ajax.sendAJAX("");
  };

  window.show_tags = function (category) {
    const ajax = new tbdev_ajax("showtags.php");
    ajax.method = "POST";
    ajax.element = "tags";
    ajax.setVar("cat", category);
    ajax.sendAJAX("");
  };

  window.send = function () {
    const ajax = new tbdev_ajax("chat.php?table");
    ajax.method = "POST";
    ajax.element = "ajax";
    ajax.sendAJAX("");
  };

  window.showonline = function () {
    const ajax = new tbdev_ajax("chat.php?online");
    ajax.method = "POST";
    ajax.element = "online";
    ajax.sendAJAX("");
  };

  window.sendmsg = function () {
    const f = document.forms[0];
    const val = (f && f.msg) ? f.msg.value : "";
    const ajax = new tbdev_ajax("chat.php?new&table");
    ajax.method = "POST";
    ajax.element = "ajax";
    ajax.setVar("msg", val);
    ajax.sendAJAX("");
    if (f && f.msg) f.msg.value = "";
  };

  window.showsubcat = function () {
    const f = document.forms[0];
    const typeVal = (f && f.type) ? f.type.value : "";
    const ajax = new tbdev_ajax("subcategory.php");
    ajax.method = "POST";
    ajax.element = "subcategory";
    ajax.setVar("uplid", typeVal);
    ajax.sendAJAX("");
  };

  window.ajaxpreview = function (objname) {
    const el = document.getElementById(objname);
    if (!el) return;
    const txt = (typeof enBASE64 === "function") ? enBASE64(el.value) : btoa(unescape(encodeURIComponent(el.value)));
    const ajax = new tbdev_ajax("preview.php?ajax");
    ajax.method = "POST";
    ajax.element = "preview";
    ajax.setVar("msg", txt);
    ajax.sendAJAX("");
  };

  /* ре-центрирование лоадера при ресайзе/скролле — без лишних вызовов */
  (function () {
    let rafId = 0;
    function recenter() {
      rafId = 0;
      const layer = document.getElementById("loading-layer");
      if (!layer || getComputedStyle(layer).display === "none") return;
      const c = new center_div();
      c.divname = "loading-layer";
      c.move_div();
    }
    function schedule() {
      if (!rafId) rafId = requestAnimationFrame(recenter);
    }
    window.addEventListener("resize", schedule, { passive: true });
    window.addEventListener("scroll", schedule, { passive: true });
  })();

})();
</script>
