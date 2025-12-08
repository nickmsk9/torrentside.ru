/* TBDev: Scroll Dock (Вверх/Вниз) */
(function () {
  "use strict";

  function init() {
    /** ================= ПАРАМЕТРЫ ================= */
    var CFG = {
      topOffsetPx: 16,
      leftOffsetPx: 16,
      showFromPx: 0,
      zIndex: 2147483000,
      hideOnIdleMs: 2500,
      rounded: 14,
      btnSize: 36,
      gap: 6,
    };

    // Если уже добавлено — выходим (защита от двойного подключения)
    if (document.getElementById("tb-scroll-dock")) return;

    // Вставляем стили
    var css = `
    #tb-scroll-dock{
      position:fixed; top:${CFG.topOffsetPx}px; left:${CFG.leftOffsetPx}px;
      display:flex; flex-direction:column; gap:${CFG.gap}px;
      z-index:${CFG.zIndex}; pointer-events:auto; user-select:none;
    }
    #tb-scroll-dock .tb-btn{
      width:${CFG.btnSize}px; height:${CFG.btnSize}px; padding:0; margin:0;
      display:flex; align-items:center; justify-content:center;
      border:1px solid rgba(0,0,0,.15);
      background:linear-gradient(#ffffff,#f6f6f6);
      box-shadow:0 1px 2px rgba(0,0,0,.08);
      border-radius:${CFG.rounded}px; cursor:pointer;
      transition:transform .12s ease, box-shadow .12s ease, border-color .12s ease;
      outline:none;
    }
    #tb-scroll-dock .tb-btn:hover{ box-shadow:0 2px 6px rgba(0,0,0,.14) }
    #tb-scroll-dock .tb-btn:active{ transform:translateY(1px) }
    #tb-scroll-dock .tb-btn[aria-disabled="true"]{ opacity:.5; cursor:default; pointer-events:none; }
    #tb-scroll-dock .tb-icon{ width:18px; height:18px; display:block; }
    #tb-scroll-dock.tb-hide{ opacity:0; visibility:hidden; transition:opacity .18s ease, visibility .18s step-end }
    #tb-scroll-dock.tb-show{ opacity:1; visibility:visible; transition:opacity .18s ease }
    @media (max-width: 420px){
      #tb-scroll-dock{ top:${Math.max(8, CFG.topOffsetPx)}px; left:${Math.max(8, CFG.leftOffsetPx)}px }
      #tb-scroll-dock .tb-btn{ width:${CFG.btnSize-4}px; height:${CFG.btnSize-4}px }
    }`;
    var style = document.createElement("style");
    style.id = "tb-scroll-dock-style";
    style.type = "text/css";
    style.appendChild(document.createTextNode(css));
    document.head.appendChild(style);

    // Контейнер
    var dock = document.createElement("div");
    dock.id = "tb-scroll-dock";
    dock.className = "tb-show";

    // Кнопка ВВЕРХ
    var upBtn = document.createElement("button");
    upBtn.type = "button";
    upBtn.className = "tb-btn";
    upBtn.title = "Вверх";
    upBtn.setAttribute("aria-label", "Прокрутить вверх");
    upBtn.innerHTML = '<svg class="tb-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M6.7 14.3a1 1 0 0 0 1.4 0L12 10.4l3.9 3.9a1 1 0 0 0 1.4-1.4l-4.6-4.6a1 1 0 0 0-1.4 0L6.7 12.9a1 1 0 0 0 0 1.4z" fill="currentColor"/></svg>';

    // Кнопка ВНИЗ
    var downBtn = document.createElement("button");
    downBtn.type = "button";
    downBtn.className = "tb-btn";
    downBtn.title = "Вниз";
    downBtn.setAttribute("aria-label", "Прокрутить вниз");
    downBtn.innerHTML = '<svg class="tb-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M17.3 9.7a1 1 0 0 0-1.4 0L12 13.6 8.1 9.7a1 1 0 1 0-1.4 1.4l4.6 4.6a1 1 0 0 0 1.4 0l4.6-4.6a1 1 0 0 0 0-1.4z" fill="currentColor"/></svg>';

    dock.appendChild(upBtn);
    dock.appendChild(downBtn);
    document.body.appendChild(dock);

    // Предпочтение «уменьшение анимации»
    var prefersReduced = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    function smoothScrollTo(y) {
      var behavior = prefersReduced ? "auto" : "smooth";
      try { window.scrollTo({ top: y, behavior: behavior }); }
      catch (e) { window.scrollTo(0, y); }
    }
    function scrollTop()   { smoothScrollTo(0); }
    function scrollBottom(){ smoothScrollTo(Math.max(0, document.documentElement.scrollHeight - window.innerHeight)); }

    upBtn.addEventListener("click", scrollTop);
    downBtn.addEventListener("click", scrollBottom);

    // Троттлинг
    function throttle(fn, wait) {
      var t = 0, saved;
      return function () {
        var now = Date.now();
        if (now - t >= wait) { t = now; fn.apply(this, arguments); }
        else { clearTimeout(saved); saved = setTimeout(fn.bind(this, arguments), wait - (now - t)); }
      };
    }

    // Управление видимостью/состоянием
    var idleTimer;
    function updateState() {
      var y = window.pageYOffset || document.documentElement.scrollTop || 0;
      var maxY = Math.max(0, (document.documentElement.scrollHeight || 0) - window.innerHeight);
      upBtn.setAttribute("aria-disabled", y <= 0 ? "true" : "false");
      downBtn.setAttribute("aria-disabled", y >= maxY ? "true" : "false");
      if (y > CFG.showFromPx) {
        dock.classList.remove("tb-hide"); dock.classList.add("tb-show");
      } else {
        dock.classList.remove("tb-show"); dock.classList.add("tb-hide");
      }
      if (CFG.hideOnIdleMs > 0) {
        dock.classList.add("tb-show"); dock.classList.remove("tb-hide");
        clearTimeout(idleTimer);
        idleTimer = setTimeout(function(){
          if (!(dock.matches && dock.matches(":hover"))) dock.classList.add("tb-hide");
        }, CFG.hideOnIdleMs);
      }
    }

    var onScroll = throttle(updateState, 80);
    var onResize = throttle(updateState, 200);
    window.addEventListener("scroll", onScroll, { passive: true });
    window.addEventListener("resize", onResize);

    // Горячие клавиши
    document.addEventListener("keydown", function (e) {
      var tag = (e.target && e.target.tagName) ? e.target.tagName.toLowerCase() : "";
      if (tag === "input" || tag === "textarea" || e.defaultPrevented) return;
      if (e.key === "Home" || (e.altKey && e.key === "ArrowUp")) { scrollTop(); e.preventDefault(); }
      if (e.key === "End"  || (e.altKey && e.key === "ArrowDown")) { scrollBottom(); e.preventDefault(); }
    });

    updateState();
  }

  // Запускать ПОСЛЕ построения <body>
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init, { once: true });
  } else {
    init();
  }
})();
