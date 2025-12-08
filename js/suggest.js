// ====== отображение торрентов списком или картинками ======
(() => {
  "use strict";

  // ---------- helpers ----------
  const $ = (e) => (typeof e === "string" ? document.getElementById(e) : e);

  // безопасный encode + простой esc для вставки в HTML (если вдруг понадобится)
  const esc = (s) =>
    String(s)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");

  // лёгкий дебаунсер, чтобы не спамить сервер при наборе
  const debounce = (fn, ms) => {
    let t;
    return function (...args) {
      clearTimeout(t);
      t = setTimeout(() => fn.apply(this, args), ms);
    };
  };

  // Кэш подсказок в памяти, чтобы не дёргать сервер повторно
  const cache = new Map();

  // Текущее состояние виджета
  let pos = 0;              // выделенный индекс (1..count)
  let count = 0;            // кол-во отображаемых подсказок
  let lastQuery = "";       // чтобы не перерисовывать то же самое
  let aborter = null;       // AbortController для отмены предыдущего запроса

  // Узлы
  const input = $("searchinput");         // поле ввода
  const suggCont = $("suggcontainer");    // контейнер
  const suggDiv = $("suggestions");       // внутренняя область с элементами

  // Если на странице нет нужных узлов — тихо выходим
  if (!input || !suggCont || !suggDiv) return;

  // Небольшой стиль через JS (чтобы не плодить CSS-файл)
  suggDiv.style.position = "absolute";
  suggDiv.style.backgroundColor = "#fff";
  suggDiv.style.border = "1px solid #777";
  suggDiv.style.maxHeight = "260px";
  suggDiv.style.overflowY = "auto";
  suggDiv.style.lineHeight = "1.2";

  // ---------- AJAX layer (совместимость с твоим api) ----------
  const ajax = {
    async get(url, func) {
      // отменить предыдущий fetch, если пользователь печатает быстро
      if (aborter) aborter.abort();
      aborter = new AbortController();

      // кэш
      if (cache.has(url)) {
        func(cache.get(url));
        return;
      }

      // no-store чтобы не засиживался в HTTP-кэше
      const res = await fetch(url, {
        method: "GET",
        cache: "no-store",
        signal: aborter.signal,
        headers: { "X-Requested-With": "XMLHttpRequest" }
      }).catch(() => null);

      if (!res || !res.ok) {
        func("");  // скрыть при ошибке
        return;
      }

      const text = await res.text();
      cache.set(url, text);
      func(text);
    }
  };

  // ---------- отрисовка списка ----------
  function renderList(lines) {
    // ограничим до 10 пунктов
    count = Math.min(lines.length, 10);
    pos = 0;

    if (count === 0) {
      suggCont.style.display = "none";
      suggDiv.innerHTML = "";
      return;
    }

    // перерисовка быстрая: используем DocumentFragment
    suggDiv.innerHTML = "";
    const frag = document.createDocumentFragment();

    for (let i = 0; i < count; i++) {
      const el = document.createElement("div");
      el.id = String(i + 1);                // 1..count
      el.textContent = lines[i];            // безопасно: как текст, не HTML
      el.style.padding = "6px 8px";
      el.style.cursor = "pointer";
      el.style.whiteSpace = "nowrap";
      el.style.overflow = "hidden";
      el.style.textOverflow = "ellipsis";
      el.addEventListener("mouseover", () => select(el, true));
      el.addEventListener("mouseout", () => unselect(el, true));
      el.addEventListener("mousedown", (ev) => {
        // чтобы клик не уводил фокус с input до choiceclick
        ev.preventDefault();
      });
      el.addEventListener("click", () => choiceclick(el));
      frag.appendChild(el);
    }

    suggDiv.appendChild(frag);
    suggCont.style.display = "block";
  }

  function select(obj, byMouse) {
    obj.style.backgroundColor = "#3399ff";
    obj.style.color = "#fff";
    if (byMouse) {
      pos = Number(obj.id) || 0;
      unselectAllOther(pos);
    }
  }

  function unselect(obj, byMouse) {
    obj.style.backgroundColor = "#fff";
    obj.style.color = "#000";
    if (byMouse) {
      pos = 0;
    }
  }

  function unselectAllOther(id) {
    for (let i = 1; i <= count; i++) {
      if (i === id) continue;
      const el = $(String(i));
      if (!el) continue;
      el.style.backgroundColor = "#fff";
      el.style.color = "#000";
    }
  }

  function goNext() {
    if (count === 0) return;
    if ($(String(pos))) unselect($(String(pos)));
    pos++;
    if (!$(String(pos))) pos = 0;
    if ($(String(pos))) select($(String(pos)));
  }

  function goPrev() {
    if (count === 0) return;
    if ($(String(pos))) {
      unselect($(String(pos)));
      pos--;
      if ($(String(pos))) {
        select($(String(pos)));
      } else {
        pos = 0;
      }
    } else {
      pos = count;
      if ($(String(count))) select($(String(count)));
    }
  }

  function choiceclick(obj) {
    input.value = obj.textContent || "";
    count = 0;
    pos = 0;
    suggCont.style.display = "none";
    input.focus();
  }

  function closechoices() {
    if (suggCont.style.display === "block") {
      count = 0;
      pos = 0;
      suggCont.style.display = "none";
    }
  }

  // ---------- совместимые функции, которые ты уже зовёшь из PHP/HTML ----------
  // блокируем Enter, если открыты подсказки
  window.noenter = function (key) {
    if (suggCont.style.display === "block") {
      if (key === 13) {
        const el = $(String(pos));
        if (el) choiceclick(el);
        return false;
      }
      return true;
    }
    return true;
  };

  // основная функция вызова из onkeyup/oninput
  const _suggestCore = (key, query) => {
    if (key === 38) return void goPrev();
    if (key === 40) return void goNext();
    if (key === 13) return; // Enter обрабатывается в noenter

    if (!query || query.length <= 3) {
      lastQuery = "";
      closechoices();
      return;
    }

    if (query === lastQuery) return; // не запрашиваем то же самое
    lastQuery = query;

    // запрос на сервер (формат ответа как раньше: строки через \r\n)
    const url = `suggest.php?q=${encodeURIComponent(query)}&t=${Date.now()}`;

    ajax.get(url, (result) => {
      // ожидался текстовый ответ; нормализуем переносы строк
      const text = (result || "").replace(/\r\n/g, "\n").trim();
      if (!text) {
        closechoices();
        return;
      }
      const lines = text.split("\n").filter(Boolean);
      renderList(lines);
    });
  };

  // публично совместимая функция
  window.suggest = debounce(_suggestCore, 120);

  // клик вне подсказок — закрываем
  document.addEventListener("click", (ev) => {
    const inside =
      ev.target === suggDiv ||
      ev.target === input ||
      suggDiv.contains(ev.target);
    if (!inside) closechoices();
  });

  // поддержка Esc / стрелок даже без onkeyup, если понадобится
  input.addEventListener("keydown", (e) => {
    if (suggCont.style.display !== "block") return;
    if (e.key === "Escape") {
      closechoices();
      e.preventDefault();
    } else if (e.key === "ArrowDown") {
      goNext();
      e.preventDefault();
    } else if (e.key === "ArrowUp") {
      goPrev();
      e.preventDefault();
    } else if (e.key === "Enter") {
      const el = $(String(pos));
      if (el) {
        choiceclick(el);
        e.preventDefault();
      }
    }
  });
})();
