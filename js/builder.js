/*!
 * Minimal Builder v1 (drop-in for script.aculo.us builder.js)
 * Совместимо по API, но без Prototype; безопасное создание узлов.
 */
(function (global) {
  "use strict";

  // ===== helpers (внутренние) =====
  var toStr = Object.prototype.toString;
  var slice = Function.prototype.call.bind(Array.prototype.slice);

  function isNode(x) {
    return !!x && typeof x === "object" && typeof x.nodeType === "number";
  }
  function isArray(x) {
    return Array.isArray(x);
  }
  function isStringOrNumber(x) {
    var t = typeof x;
    return t === "string" || t === "number";
  }
  function flatten(arr, out) {
    out = out || [];
    for (var i = 0; i < arr.length; i++) {
      var v = arr[i];
      if (!v) continue;
      if (isArray(v)) flatten(v, out);
      // поддержим NodeList/HTMLCollection
      else if (toStr.call(v).indexOf("NodeList") !== -1 || toStr.call(v).indexOf("HTMLCollection") !== -1) {
        flatten(slice(v), out);
      } else out.push(v);
    }
    return out;
  }

  // ===== основной объект =====
  var Builder = {
    // историческое: тег-контейнер для innerHTML-подхода (оставлено для совместимости)
    NODEMAP: {
      AREA: "map",
      CAPTION: "table",
      COL: "table",
      COLGROUP: "table",
      LEGEND: "fieldset",
      OPTGROUP: "select",
      OPTION: "select",
      PARAM: "object",
      TBODY: "table",
      TD: "table",
      TFOOT: "table",
      TH: "table",
      THEAD: "table",
      TR: "table"
    },

    ATTR_MAP: { className: "class", htmlFor: "for" },

    _isStringOrNumber: isStringOrNumber,

    _text: function (text) {
      return document.createTextNode(text);
    },

    _setAttributes: function (el, attrs) {
      for (var k in attrs) {
        if (!Object.prototype.hasOwnProperty.call(attrs, k)) continue;
        var v = attrs[k];
        if (v == null) continue;

        // преобразование style из объекта
        if (k === "style" && typeof v === "object") {
          for (var sk in v) {
            if (Object.prototype.hasOwnProperty.call(v, sk)) {
              el.style[sk] = v[sk];
            }
          }
          continue;
        }

        // поддержка events: onClick, oninput и т.д. как функции
        if (/^on[A-Z]/.test(k) && typeof v === "function") {
          el.addEventListener(k.slice(2).toLowerCase(), v);
          continue;
        }

        var attrName = this.ATTR_MAP[k] || k;

        // boolean-атрибуты (disabled, checked и т.п.)
        if (typeof v === "boolean") {
          if (v) el.setAttribute(attrName, attrName);
          else el.removeAttribute(attrName);
          continue;
        }

        // className → class
        if (attrName === "class" && isArray(v)) {
          v = v.filter(Boolean).join(" ");
        }

        try {
          el.setAttribute(attrName, String(v));
        } catch (e) {
          // на крайний случай, если что-то экзотическое
          el[attrName] = v;
        }
      }
    },

    _children: function (element, children) {
      if (!children && children !== 0) return;

      // узел
      if (isNode(children)) {
        element.appendChild(children);
        return;
      }

      // массив(ы) узлов/строк
      if (typeof children === "object") {
        var list = flatten(isArray(children) ? children : [children]);
        for (var i = 0; i < list.length; i++) {
          var e = list[i];
          if (isNode(e)) element.appendChild(e);
          else if (isStringOrNumber(e)) element.appendChild(this._text(e));
        }
        return;
      }

      // строка/число
      if (isStringOrNumber(children)) {
        element.appendChild(this._text(children));
      }
    },

    // === главный фабричный метод ===
    // Builder.node('div', {className:'x'}, ['text', child, ...])
    // Builder.node('div', 'text') — совместимая краткая форма
    node: function (elementName /* , attrsOrChildren?, children? */) {
      var tag = String(elementName || "").toUpperCase();
      if (!tag) return;

      // создаём элемент напрямую, без innerHTML-трюков, где возможно
      var el = document.createElement(tag);

      // аргументы
      var a1 = arguments[1];
      var a2 = arguments[2];

      // если второй аргумент — не attrs, а сразу контент (строка/число/массив/узел)
      var hasAttrs =
        a1 &&
        typeof a1 === "object" &&
        !isArray(a1) &&
        !isNode(a1) &&
        !isStringOrNumber(a1);

      if (hasAttrs) {
        this._setAttributes(el, a1);
        if (arguments.length > 2) this._children(el, a2);
      } else {
        if (arguments.length > 1) this._children(el, a1);
      }

      // спец-случаи таблицных элементов в старых браузерах уже не требуются,
      // но оставим soft-fallback на NODEMAP в редких embed-кейcах:
      if (this.NODEMAP[tag]) {
        // если браузер не позволил напрямую (практически не встречается сегодня),
        // можно пересобрать через контейнер:
        if (el.outerHTML && el.outerHTML.toUpperCase().indexOf("<" + tag) !== 0) {
          var parent = document.createElement(this.NODEMAP[tag]);
          parent.appendChild(el);
          el = parent.querySelector(tag) || el;
        }
      }

      return el;
    },

    // безопасный HTML → Node (первый корневой элемент)
    build: function (html) {
      var tpl = document.createElement("template");
      tpl.innerHTML = String(html || "").trim();
      // вернём первый элемент (как old .down())
      return tpl.content.firstElementChild || document.createElement("div");
    },

    // как и раньше — создаёт глобальные функции-тег-шорткаты: DIV(), SPAN(), TABLE()...
    dump: function (scope) {
      if (typeof scope !== "object" && typeof scope !== "function") scope = global;
      var tags = (
        "A ABBR ACRONYM ADDRESS APPLET AREA B BASE BASEFONT BDO BIG BLOCKQUOTE BODY " +
        "BR BUTTON CAPTION CENTER CITE CODE COL COLGROUP DD DEL DFN DIR DIV DL DT EM FIELDSET " +
        "FONT FORM FRAME FRAMESET H1 H2 H3 H4 H5 H6 HEAD HR HTML I IFRAME IMG INPUT INS ISINDEX " +
        "KBD LABEL LEGEND LI LINK MAP MENU META NOFRAMES NOSCRIPT OBJECT OL OPTGROUP OPTION P " +
        "PARAM PRE Q S SAMP SCRIPT SELECT SMALL SPAN STRIKE STRONG STYLE SUB SUP TABLE TBODY TD " +
        "TEXTAREA TFOOT TH THEAD TITLE TR TT U UL VAR"
      ).split(/\s+/);

      for (var i = 0; i < tags.length; i++) {
        (function (tag) {
          scope[tag] = function () {
            var args = slice(arguments);
            // вызываем Builder.node(tag, ...args)
            return Builder.node.apply(Builder, [tag].concat(args));
          };
        })(tags[i]);
      }
    }
  };

  // экспорт
  global.Builder = Builder;

})(typeof window !== "undefined" ? window : this);
