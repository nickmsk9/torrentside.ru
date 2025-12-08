/* ===================== глобальные состояния ===================== */
window.marked_row = window.marked_row || {}; // совместимость с твоей логикой

/* ===================== setPointer: подсветка строк ===================== */
/**
 * @param {HTMLTableRowElement} theRow
 * @param {number|string} theRowNum
 * @param {'over'|'out'|'click'} theAction
 * @param {string} theDefaultColor
 * @param {string} thePointerColor
 * @param {string} theMarkColor
 */
function setPointer(theRow, theRowNum, theAction, theDefaultColor, thePointerColor, theMarkColor) {
  if ((!thePointerColor && !theMarkColor) || !theRow || !theRow.style) return false;

  // получаем ячейки ряда
  var cells = theRow.getElementsByTagName ? theRow.getElementsByTagName('td') :
             (theRow.cells ? theRow.cells : null);
  if (!cells || !cells.length) return false;

  // внутреннее состояние (чтобы не сравнивать цвета разных форматов)
  var state = theRow.dataset.state || 'default';
  var isMarked = !!window.marked_row[theRowNum];

  // запомним дефолтный цвет для возврата
  if (!theRow.dataset.defBg) theRow.dataset.defBg = theDefaultColor || '';

  function paint(color) {
    for (var i = 0; i < cells.length; i++) {
      cells[i].style.backgroundColor = color || '';
    }
  }

  // переходы состояний (совместимо по поведению с прежним кодом)
  if (state === 'default') {
    if (theAction === 'over' && thePointerColor) {
      state = 'pointer'; paint(thePointerColor);
    } else if (theAction === 'click' && theMarkColor) {
      state = 'mark'; isMarked = true; paint(theMarkColor);
    }
  } else if (state === 'pointer' && !isMarked) {
    if (theAction === 'out') {
      state = 'default'; paint(theRow.dataset.defBg);
    } else if (theAction === 'click' && theMarkColor) {
      state = 'mark'; isMarked = true; paint(theMarkColor);
    }
  } else if (state === 'mark') {
    if (theAction === 'click') {
      // клик по отмеченному: снять отметку и уйти в pointer|default
      isMarked = !isMarked; // станет false
      if (thePointerColor) {
        state = 'pointer'; paint(thePointerColor);
      } else {
        state = 'default'; paint(theRow.dataset.defBg);
      }
    }
  }

  theRow.dataset.state = state;
  window.marked_row[theRowNum] = isMarked ? true : undefined;
  return true;
}

/* ===================== imgFit: вписывание изображения ===================== */
function imgFit(img, maxImgWidth) {
  if (!img || !maxImgWidth) return;

  // naturalWidth/Height поддерживаются везде; оставим аккуратный полифилл
  if (typeof img.naturalWidth === 'undefined') {
    img.naturalWidth  = img.width;
    img.naturalHeight = img.height;
  }
  if (img.width > maxImgWidth) {
    var ratio = maxImgWidth / img.width;
    img.width  = maxImgWidth;
    img.height = Math.round(img.height * ratio);
    img.title  = 'Нажмите на картинку для увеличения';
    img.style.cursor = 'zoom-in';
  } else if (img.width === maxImgWidth && img.width < img.naturalWidth) {
    img.width  = img.naturalWidth;
    img.height = img.naturalHeight;
    img.title  = 'Нажмите на картинку для помещения в размер окна';
    img.style.cursor = 'zoom-out';
  }
}

/* ===================== трекинг курсора + подсказки ===================== */
var tid = 0, x = 0, y = 0;
var _showTimers = Object.create(null);

document.addEventListener('mousemove', function track(e) {
  // pageX/Y уже учитывают прокрутку
  x = e.pageX || 0;
  y = e.pageY || 0;
}, { passive: true });

function show(id) {
  var el = document.getElementById(id);
  if (!el) return;

  el.style.left = (x - 120) + 'px';
  el.style.top  = (y + 25) + 'px';
  el.style.position = el.style.position || 'absolute';
  el.style.display = 'block';

  // перерисовывать позицию плавно, а не через строковый eval
  if (_showTimers[id]) clearTimeout(_showTimers[id]);
  _showTimers[id] = setTimeout(function () { show(id); }, 16); // ~60fps
}

function hide(id) {
  var el = document.getElementById(id);
  if (!el) return;
  if (_showTimers[id]) {
    clearTimeout(_showTimers[id]);
    delete _showTimers[id];
  }
  el.style.display = 'none';
}

/* ===================== show_hide: раскрывашки с иконкой ===================== */
function show_hide(id) {
  var textEl = document.getElementById('s' + id);
  var imgEl  = document.getElementById('pic' + id);
  if (!textEl || !imgEl) return;

  var hidden = getComputedStyle(textEl).display === 'none';
  if (hidden) {
    textEl.style.display = 'block';
    imgEl.src = 'pic/minus.gif';
    imgEl.title = 'Скрыть';
  } else {
    textEl.style.display = 'none';
    imgEl.src = 'pic/plus.gif';
    imgEl.title = 'Показать';
  }
}

/* ===================== updateText: массовые замены с BBCode ===================== */
(function () {
  // карта замен: "искомое" => "чем заменить"
  var MAP = [
    ['Информация о фильме', '[u]Информация о фильме[/u]'],
    ['Название:', '[b]Название: [/b]'],
    ['Оригинальное название:', '[b]Оригинальное название: [/b]'],
    ['Русское название:', '[b]Русское название: [/b]'],
    ['Год выхода: ', '[b]Год выхода: [/b]'],
    ['Жанр:', '[b]Жанр: [/b]'],
    ['Режиссер:', '[b]Режиссер: [/b]'],
    ['В ролях:', '[b]В ролях: [/b]'],
    ['О фильме:', '[b]О фильме: [/b]'],
    ['Выпущено:', '[b]Выпущено: [/b]'],
    ['Продолжительность:', '[b]Продолжительность: [/b]'],
    ['Перевод:', '[b]Перевод: [/b]'],
    ['Субтитры:', '[b]Субтитры: [/b]'],
    ['Дополнительно:', '[b]Дополнительно: [/b]'],
    ['Файл', '[u]Файл[/u]'],
    ['Формат:', '[b]Формат: [/b]'],
    ['Качество:', '[b]Качество: [/b]'],
    ['Видео:', '[b]Видео: [/b]'],
    ['Звук:', '[b]Звук: [/b]'],
    ['Исполнитель:', '[b]Исполнитель: [/b]'],
    ['Альбом:', '[b]Альбом: [/b]'],
    ['Треклист:', '[b][u]Треклист:[/u][/b]'],
    ['Платформа:', '[b]Платформа: [/b]'],
    ['Язык интерфейса:', '[b]Язык интерфейса: [/b]'],
    ['Лекарство:', '[b]Лекарство: [/b]'],
    ['Описание:', '[b]Описание: [/b]'],
    ['Доп. информация:', '[b]Доп. информация: [/b]'],
    ['Издательство:', '[b]Издательство: [/b]'],
    ['Страниц:', '[b]Страниц: [/b]'],
    ['Серия или Выпуск:', '[b]Серия или Выпуск: [/b]'],
    ['Язык:', '[b]Язык: [/b]'],
    ['О книге:', '[b][u]О книге:[/u][/b]'],
    ['Об игре:', '[b]Об игре: [/b]'],
    ['Особенности игры:', '[b]Особенности игры: [/b]'],
    ['Системные требования:', '[b]Системные требования: [/b]'],
    ['Тематика:', '[b]Тематика: [/b]'],
    ['Формат(ы):', '[b]Формат(ы): [/b]'],
    ['Количество:', '[b]Количество: [/b]'],
    ['Минимальное разрешение:', '[b]Минимальное разрешение: [/b]'],
    ['Максимальное разрешение:', '[b]Максимальное разрешение: [/b]'],
    ['Продюсер:', '[b]Продюсер: [/b]'],
    ['От издателя ', '[b]От издателя: [/b]'],
    ['Звуковые дорожки:', '[b]Звуковые дорожки: [/b]'],
    ['Дистрибьютор:', '[b]Дистрибьютор: [/b]'],
    ['Региональный код:', '[b]Региональный код: [/b]'],
    ['Размер:', '[b]Размер: [/b]'],
    ['Страна:', '[b]Страна: [/b]'],
    ['Год выпуска:', '[b]Год выпуска: [/b]'],
    ['Трэклист:', '[u]Трэклист: [/u]'],
    ['Видео кодек:', '[b]Видео кодек: [/b]'],
    ['Аудио кодек:', '[b]Аудио кодек: [/b]'],
    ['Аудио:', '[b]Аудио: [/b]'],
    ['Автор:', '[b]Автор: [/b]'],
    ['Видеокодек:', '[b]Видеокодек: [/b]'],
    ['Битрейт видео:', '[b]Битрейт видео: [/b]'],
    ['Размер кадра:', '[b]Размер кадра: [/b]'],
    ['Качество видео: ', '[b]Качество видео:  [/b]'],
    ['Аудиокодек:', '[b]Аудиокодек: [/b]'],
    ['Битрейт аудио:', '[b]Битрейт аудио: [/b]'],
    ['Длина видео:', '[b]Длина видео: [/b]'],
    ['Описание фильма:', '[b]Описание фильма: [/b]'],
    ['IMDB', '[b][url=http://www.imdb.com]IMDB[/url][/b]']
  ];

  function escapeReg(s) { return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); }

  window.updateText = function (id) {
    var el = document.getElementById(id);
    if (!el) return;
    var txt = String(el.value || '');

    for (var i = 0; i < MAP.length; i++) {
      var from = MAP[i][0], to = MAP[i][1];
      // глобальная замена (везде, а не только первое вхождение)
      txt = txt.replace(new RegExp(escapeReg(from), 'g'), to);
    }
    el.value = txt;
  };
})();

/* ===================== changeText: простая подмена значения ===================== */
function changeText(text, id) {
  var el = document.getElementById(id);
  if (el) el.value = text;
}

/* ===================== карты символов (оставляем один раз, без дублей) ===================== */
var azWin = '     Ё               ё       АБВГДЕЖЗИЙКЛМНОПРСТУФХЦЧШЩЪЫЬЭЮЯабвгдежзийклмнопрстуфхцчшщъыьэюя';
var azKoi = 'ё                Ё           юабцдефгхийклмнопярстужвьызшэщчъЮАБЦДЕФГХИЙКЛМНОПЯРСТУЖВЬЫЗШЭЩЧЪ';
var AZ = azWin;
var azURL = '0123456789ABCDEF';
var b64s  = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/';
var b64a  = b64s.split('');

/* ===================== placeholder polyfill (легкий) ===================== */
function placeholderSetup(id) {
  var el = document.getElementById(id);
  if (!el || el.tagName !== 'INPUT') return;
  if ('placeholder' in document.createElement('input')) return; // нативная поддержка — выходим

  var ph = el.getAttribute('placeholder');
  if (!ph) return;

  if (!el.value) {
    el.value = ph;
    el.style.color = '#777';
    el.is_focused = 0;
  }
  el.addEventListener('focus', placeholderFocus);
  el.addEventListener('blur',  placeholderBlur);
}

function placeholderFocus() {
  if (!this.is_focused && this.value === this.getAttribute('placeholder')) {
    this.is_focused = 1;
    this.value = '';
    this.style.color = '#000';

    var rs = this.getAttribute('radioselect');
    if (rs) {
      var re = document.getElementById(rs);
      if (re && re.type === 'radio') re.checked = true;
    }
  }
}

function placeholderBlur() {
  var ph = this.getAttribute('placeholder');
  if (this.is_focused && ph && this.value === '') {
    this.is_focused = 0;
    this.value = ph;
    this.style.color = '#777';
  }
}

/* ===================== ajaxpreview: оставляем совместимость с tbdev_ajax ===================== */
function ajaxpreview(objname) {
  var ajax = new tbdev_ajax();
  ajax.onShow('');

  var raw = (document.getElementById(objname) || {}).value || '';
  var txt;
  try {
    // тот же base64, что и у тебя (UTF-8)
    txt = btoa(unescape(encodeURIComponent(raw)));
  } catch (e) {
    alert('Ошибка кодирования превью: ' + e.message);
    return;
  }

  ajax.requestFile = "preview.php?ajax";
  ajax.setVar("msg", txt);
  ajax.method = 'POST';
  ajax.element = 'preview';
  ajax.sendAJAX("");
}
function getMousePosition(e) {
	if (e.pageX || e.pageY){
		var posX = e.pageX;
		var posY = e.pageY;
	}else if (e.clientX || e.clientY) 	{
		var posX = e.clientX + document.body.scrollLeft + document.documentElement.scrollLeft;
		var posY = e.clientY + document.body.scrollTop + document.documentElement.scrollTop;
	}
	return {x:posX, y:posY}	
}
var scrolltotop={
	setting: {startline:600, scrollduration:500, fadeduration:[500, 100]},
	controlHTML: '<img src="./pic/up.png" style="width:35px; height:35px" />',
	controlattrs: {offsetx:15, offsety:50},
	anchorkeyword: '#atop',
	state: {isvisible:false, shouldvisible:false},
	scrollup:function(){
		if (!this.cssfixedsupport)
			this.$control.css({opacity:0})
		this.$body.animate({scrollTop: 0}, this.setting.scrollduration);
	},
	keepfixed:function(){
		var $window=jQuery(window)
		var controlx=$window.scrollLeft() + $window.width() - this.$control.width() - this.controlattrs.offsetx
		var controly=$window.scrollTop() + $window.height() - this.$control.height() - this.controlattrs.offsety
		this.$control.css({left:controlx+'px', top:controly+'px'})
	},
	togglecontrol:function(){
		var scrolltop=jQuery(window).scrollTop()
		if (!this.cssfixedsupport) this.keepfixed()
		this.state.shouldvisible=(scrolltop>=this.setting.startline)? true : false
		if (this.state.shouldvisible && !this.state.isvisible){
			this.$control.stop().animate({opacity:1}, this.setting.fadeduration[0])
			this.state.isvisible=true
		}
		else if (this.state.shouldvisible==false && this.state.isvisible){
			this.$control.stop().animate({opacity:0}, this.setting.fadeduration[1])
			this.state.isvisible=false
		}
	},
	init:function(){
		jQuery(document).ready(function($){
			var mainobj=scrolltotop
			var iebrws=document.all
			mainobj.cssfixedsupport=!iebrws || iebrws && document.compatMode=="CSS1Compat" && window.XMLHttpRequest
			mainobj.$body=$('html,body')
			mainobj.$control=$('<div id="topcontrol">'+mainobj.controlHTML+'</div>')
				.css({position:mainobj.cssfixedsupport? 'fixed' : 'absolute', bottom:mainobj.controlattrs.offsety, right:mainobj.controlattrs.offsetx, opacity:0, cursor:'pointer'})
			///	.attr({title:'Наверх странички!'})
				.click(function(){mainobj.scrollup(); return false})
				.appendTo('body')
			if (document.all && !window.XMLHttpRequest && mainobj.$control.text()!='')
				mainobj.$control.css({width:mainobj.$control.width()})
			mainobj.togglecontrol()
			$('a[href="' + mainobj.anchorkeyword +'"]').click(function(){
				mainobj.scrollup()
				return false
			})
			$(window).bind('scroll resize', function(e){
				mainobj.togglecontrol()
			})
		})
	}
}
scrolltotop.init()