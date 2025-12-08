/* bbcode2.js — modernized (compatible with PHP 8.1 front-end)
 * Исходник вдохновлён bbcode.js (forum.dklab.ru), правки: @2025
 */
(function () {
  "use strict";

  /* ---------- helpers ---------- */

  function addEvent(el, ev, fn) {
    if (!el) return;
    if (el.addEventListener) el.addEventListener(ev, fn, false);
    else if (el.attachEvent) el.attachEvent("on" + ev, fn);
    else el["on" + ev] = fn;
  }

  function stopEvent(e) {
    if (!e) return false;
    if (e.preventDefault) e.preventDefault();
    if (e.stopPropagation) e.stopPropagation();
    e.returnValue = false;
    return false;
  }

  function $(sel, ctx) {
    return (ctx || document).querySelector(sel);
  }

  /* ---------- BBCode "class" ---------- */

  function BBCode(textarea) {
    this.construct(textarea);
  }

  BBCode.prototype = {
    VK_TAB: 9,
    VK_ENTER: 13,
    VK_PAGE_UP: 33,
    BRK_OP: "[",
    BRK_CL: "]",
    textarea: null,
    stext: "",
    quoter: null,
    quoterText: "",
    collapseAfterInsert: false,
    replaceOnInsert: false,
    tags: null,

    // Create new BBCode control.
    construct: function (textarea) {
      this.textarea = textarea;
      this.tags = {};

      var th = this; // ВАЖНО: объявляем ДО использования в addTag

      // Tag for quoting.
      this.addTag(
        "_quoter",
        function () {
          return '[quote="' + th.quoter + '"]';
        },
        "[/quote]\n",
        null,
        null,
        function () {
          th.collapseAfterInsert = true;
          return th._prepareMultiline(th.quoterText);
        }
      );

      // Init events.
      addEvent(textarea, "keydown", function (e) {
        return th.onKeyPress(e || window.event, "down");
      });
      addEvent(textarea, "keypress", function (e) {
        return th.onKeyPress(e || window.event, "press");
      });
    },

    // Insert poster name or poster quotes to the text.
    onclickPoster: function (name) {
      var sel = this.getSelection()[0];
      if (sel) {
        this.quoter = name;
        this.quoterText = sel;
        this.insertTag("_quoter");
      } else {
        this.insertAtCursor("[b]" + name + "[/b]\n");
      }
      return false;
    },

    // Quote selected text from the page (outside textarea)
    onclickQuoteSel: function () {
      var sel = this.getSelection()[0];
      if (sel) {
        this.insertAtCursor("[quote]" + sel + "[/quote]\n");
      } else {
        alert("Пожалуйста, выделите текст для цитирования");
      }
      return false;
    },

    // Insert emoticon
    emoticon: function (em) {
      if (em) this.insertAtCursor(" " + em + " ");
      return false;
    },

    refreshSelection: function (get) {
      if (get) this.stext = this.getSelection()[0] || "";
      else this.stext = "";
    },

    // Получить выделение ИЗ СТРАНИЦЫ (для цитирования чужого фрагмента)
    getSelection: function () {
      var w = window;
      var text = "";
      var range = null;

      if (w.getSelection) {
        var sel = w.getSelection();
        text = sel ? (typeof sel.toString === "function" ? sel.toString() : "" + sel) : "";
      } else if (w.document && w.document.getSelection) {
        var sel2 = w.document.getSelection();
        text = sel2 ? (typeof sel2.toString === "function" ? sel2.toString() : "" + sel2) : "";
      } else if (w.document && w.document.selection && w.document.selection.createRange) {
        range = w.document.selection.createRange();
        text = range ? range.text : "";
      } else {
        return [null, null];
      }

      if (text === "") text = this.stext || "";
      text = ("" + text).replace(/^\s+|\s+$/g, "");
      return [text, range];
    },

    // Вставить текст в курсор textarea
    insertAtCursor: function (text) {
      var t = this.textarea;
      if (!t) return;
      t.focus();

      // IE <= 8 fallback (оставлено для совместимости)
      if (document.selection && t.createTextRange) {
        var r = document.selection.createRange();
        if (!this.replaceOnInsert) r.collapse();
        r.text = text;
      } else if (typeof t.selectionStart === "number" && typeof t.selectionEnd === "number") {
        var start = this.replaceOnInsert ? t.selectionStart : t.selectionEnd;
        var end = t.selectionEnd;
        var sel1 = t.value.substring(0, start);
        var sel2 = t.value.substring(end);
        t.value = sel1 + text + sel2;
        var pos = start + text.length;
        t.setSelectionRange(pos, pos);
      } else {
        // самый простой фолбэк
        t.value += text;
      }

      setTimeout(function () {
        t.focus();
      }, 0);
    },

    surround: function (open, close, fTrans) {
      var t = this.textarea;
      if (!t) return false;
      t.focus();
      if (!fTrans) fTrans = function (x) { return x; };

      var notEmpty = false;

      // Современные браузеры
      if (typeof t.selectionStart === "number" && typeof t.selectionEnd === "number") {
        var start = t.selectionStart;
        var end = t.selectionEnd;
        var top = t.scrollTop;
        var sel1 = t.value.substring(0, start);
        var sel2 = t.value.substring(end);
        var sel = fTrans(t.value.substring(start, end));
        var inner = open + sel + (close || "");
        t.value = sel1 + inner + sel2;

        if (sel !== "") {
          t.setSelectionRange(start, start + inner.length);
          notEmpty = true;
        } else {
          var caret = start + open.length;
          t.setSelectionRange(caret, caret);
          notEmpty = false;
        }
        t.scrollTop = top;
        if (this.collapseAfterInsert) {
          var pos = start + inner.length;
          t.setSelectionRange(pos, pos);
        }
      } else if (document.selection && t.createTextRange) {
        // Старый IE
        var r = document.selection.createRange();
        var text = r ? r.text : "";
        var newText = open + fTrans(text) + (close || "");
        if (r) {
          r.text = newText;
          r.collapse(false);
          if (!this.collapseAfterInsert) r.select();
        } else {
          t.value += newText;
        }
        notEmpty = !!text;
      } else {
        // Фолбэк без выделений
        t.value += open + (close || "");
      }

      this.collapseAfterInsert = false;
      return notEmpty;
    },

    // Обработка хоткеев и кнопок
    onKeyPress: function (e, type) {
      e = e || window.event;

      // Горячие клавиши для зарегистрированных тегов
      var keyChar = "";
      if (typeof e.key === "string" && e.key.length === 1) keyChar = e.key;
      else if (e.keyCode) keyChar = String.fromCharCode(e.keyCode);
      else if (e.which) keyChar = String.fromCharCode(e.which);

      for (var id in this.tags) {
        if (!Object.prototype.hasOwnProperty.call(this.tags, id)) continue;
        var tag = this.tags[id];
        if (!tag) continue;

        var needCtrl = tag.ctrlKey ? tag.ctrlKey.toLowerCase() : "ctrl";
        var okCtrl =
          needCtrl === "ctrl" ? e.ctrlKey :
          needCtrl === "alt" ? e.altKey :
          needCtrl === "shift" ? e.shiftKey : false;

        if (tag.key && okCtrl) {
          if (keyChar.toUpperCase() === String(tag.key).toUpperCase()) {
            if (e.type === "keydown") this.insertTag(id);
            return stopEvent(e);
          }
        }
      }

      // Tab > вставляем [tab]
      var code = e.keyCode || e.which || 0;
      if (type === "press" && code === this.VK_TAB && !e.shiftKey && !e.ctrlKey && !e.altKey) {
        this.insertAtCursor("[tab]");
        return stopEvent(e);
      }

      // Ctrl+Tab > фокус на следующем элементе формы (кнопка submit)
      if (code === this.VK_TAB && !e.shiftKey && e.ctrlKey && !e.altKey) {
        var submitter =
          (this.textarea.form && $("button[type=submit],input[type=submit]", this.textarea.form)) ||
          null;
        if (submitter) submitter.focus();
        return stopEvent(e);
      }

      // Комбо-шорткаты на кнопки формы (если есть)
      var form = this.textarea.form;
      if (form) {
        var submitterBtn = null;
        if (code === this.VK_PAGE_UP && e.shiftKey && !e.ctrlKey && e.altKey) {
          submitterBtn = form.add_attachment_box || $("[name=add_attachment_box]", form);
        }
        if (code === this.VK_ENTER && !e.shiftKey && !e.ctrlKey && e.altKey) {
          submitterBtn = form.preview || $("[name=preview]", form);
        }
        if (code === this.VK_ENTER && !e.shiftKey && e.ctrlKey && !e.altKey) {
          submitterBtn = form.post || $("[name=post]", form) || $("button[type=submit],input[type=submit]", form);
        }
        if (submitterBtn && typeof submitterBtn.click === "function") {
          submitterBtn.click();
          return stopEvent(e);
        }
      }

      return true;
    },

    // Регистрация кнопки/тега
    addTag: function (id, open, close, key, ctrlKey, multiline) {
      if (!ctrlKey) ctrlKey = "ctrl";
      var tag = {
        id: id,
        open: open,
        close: close,
        key: key,
        ctrlKey: ctrlKey,
        multiline: multiline,
        elt: this.textarea && this.textarea.form ? this.textarea.form[id] : null
      };
      this.tags[id] = tag;

      // навешиваем обработчики на элементы формы (кнопки/селекты)
      var elt = tag.elt;
      if (elt) {
        var th = this;
        if (elt.type && String(elt.type).toUpperCase() === "BUTTON") {
          addEvent(elt, "click", function () {
            th.insertTag(id);
            return false;
          });
        }
        if (elt.tagName && String(elt.tagName).toUpperCase() === "SELECT") {
          addEvent(elt, "change", function () {
            th.insertTag(id);
            return false;
          });
        }
      } else {
        if (id && id.indexOf("_") !== 0) {
          // не шумим alert’ом — просто молча пропускаем (могут быть опциональные элементы)
          // console.warn("addTag('" + id + "'): элемент не найден в форме");
        }
      }
    },

    // Вставка зарегистрированного тега
    insertTag: function (id) {
      var tag = this.tags[id];
      if (!tag) {
        alert("Unknown tag ID: " + id);
        return;
      }
      var op = tag.open;
      if (typeof op === "function") op = op(tag.elt);

      var cl = tag.close != null ? tag.close : "/" + op;

      // Оборачиваем в скобки при необходимости
      if (op.charAt(0) !== this.BRK_OP) op = this.BRK_OP + op + this.BRK_CL;
      if (cl && cl.charAt(0) !== this.BRK_OP) cl = this.BRK_OP + cl + this.BRK_CL;

      var transformer = null;
      if (tag.multiline) {
        transformer = tag.multiline === true ? this._prepareMultiline : tag.multiline;
      }
      this.surround(op, cl, transformer);
    },

    _prepareMultiline: function (text) {
      text = (text || "").replace(/\s+$/, "").replace(/^([ \t]*\r?\n)+/, "");
      if (text.indexOf("\n") >= 0) text = "\n" + text + "\n";
      return text;
    }
  };

  /* ---------- globals kept for backward compatibility ---------- */

  // Простой валидатор формы (оставлено как было)
  window.checkForm = function (form) {
    var formErrors = false;
    if (!form || !form.message || form.message.value.length < 2) {
      formErrors = "Please enter the message.";
    }
    if (formErrors) {
      setTimeout(function () { alert(formErrors); }, 100);
      return false;
    }
    return true;
  };

  // Старые утилиты AddSelectedText/InsertBBCode — перепривязаны к активной textarea (#area) или фокусу
  function getActiveTextarea() {
    var el = document.activeElement;
    if (el && el.tagName && el.tagName.toLowerCase() === "textarea") return el;
    return $("#area") || $("textarea");
  }

  window.AddSelectedText = function (BBOpen, BBClose) {
    var t = getActiveTextarea();
    if (!t) return;
    if (typeof t.selectionStart === "number" && typeof t.selectionEnd === "number") {
      var start = t.selectionStart, end = t.selectionEnd;
      var sel1 = t.value.substring(0, start);
      var sel  = t.value.substring(start, end);
      var sel2 = t.value.substring(end);
      t.value = sel1 + BBOpen + sel + BBClose + sel2;
      var pos = start + (BBOpen + sel + BBClose).length;
      t.setSelectionRange(pos, pos);
      t.focus();
    } else if (document.selection && t.createTextRange) {
      var r = document.selection.createRange();
      r.text = BBOpen + r.text + BBClose;
      t.focus();
    } else {
      t.value += BBOpen + BBClose;
      t.focus();
    }
  };

  window.InsertBBCode = function (BBcode) {
    window.AddSelectedText("[" + BBcode + "]", "[/" + BBcode + "]");
  };

  // IE caret fallback (оставлено без изменений, но не используется современными браузерами)
  window.storeCaret = function (textEl) {
    if (textEl && textEl.createTextRange && document.selection) {
      textEl.caretPos = document.selection.createRange().duplicate();
    }
  };

  // Паблик-класс
  window.BBCode = BBCode;
})();
