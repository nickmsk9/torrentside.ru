var loading = '<img src="pic/upload.gif" alt="Загрузка.." />';

jQuery(function() {
    jQuery(".tab").click(function() {
        if (jQuery(this).hasClass("active")) return;

        jQuery("#loading").html(loading);
        var user = jQuery("#body").attr("user");
        var act = jQuery(this).attr("id");
        jQuery(this).addClass("active").siblings("span").removeClass("active");

        jQuery.post("user.php", {user: user, act: act}, function(response) {
            jQuery("#body").empty().append(response);
            jQuery("#loading").empty();
        });
    });

    jQuery('.zebra:even').css({backgroundColor: '#EEEEEE'});
});

// Функция переключения иконки + / -
function togglepic(baseUrl, picid, formid) {
    var pic = document.getElementById(picid);
    var form = document.getElementById(formid);

    if (!pic || !form) return;

    var plusSrc = baseUrl + "/pic/plus.gif";
    var minusSrc = baseUrl + "/pic/minus.gif";

    if (pic.src.endsWith("plus.gif")) {
        pic.src = minusSrc;
        form.value = "minus";
    } else {
        pic.src = plusSrc;
        form.value = "plus";
    }
}

// Кодировка BASE64 с поддержкой кириллицы
var azWin = '     Ё               ё       АБВГДЕЖЗИЙКЛМНОПРСТУФХЦЧШЩЪЫЬЭЮЯабвгдежзийклмнопрстуфхцчшщъыьэюя';
var AZ = azWin;
var b64s = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/';
var b64a = b64s.split('');

function enBASE64(str) {
    var utf8 = unescape(encodeURIComponent(str)); // превращаем в UTF-8 байты
    var bin = "";
    for (var i = 0; i < utf8.length; i++) {
        bin += String.fromCharCode(utf8.charCodeAt(i));
    }
    return btoa(bin); // теперь можно безопасно кодировать
}


// Отправка сообщения на стену
function wall_send(to) {
    // Получаем textarea и sceditor instance
    var textarea = document.getElementById('text');
    if (!textarea) {
        alert('Ошибка: поле для ввода не найдено.');
        return;
    }

    var editor = typeof sceditor !== 'undefined' ? sceditor.instance(textarea) : null;
    var msg = editor ? editor.val() : textarea.value;

    if (!msg || msg.trim().length === 0) {
        alert('Ошибка. Пустое сообщение.');
        return;
    }

    // Кодируем в base64
    var text;
    try {
        text = btoa(unescape(encodeURIComponent(msg)));
    } catch (e) {
        alert('Ошибка кодирования: ' + e.message);
        return;
    }

    // Отправка
    jQuery.post("wall.php", {owner: to, text: text, act: "send"}, function(response) {
        jQuery("#wall").empty().append(response);
    });

    // Очистка поля
    if (editor) {
        editor.val('');
    } else {
        textarea.value = '';
    }
}




// Удаление сообщения со стены
function wall_del(id, owner) {
    jQuery.post("wall.php", {post: id, owner: owner, act: "delete"}, function(response) {
        jQuery("#wall").empty().append(response);
    });
}



// Подарок бонусов
function present(from, to, amount) {
    jQuery.post("present.php", {from: from, to: to, amount: amount}, function(response) {
       alert(response);
    });
}

// Открыть ЛС
function ls(user) {
    jQuery.post("user.php", {user: user, act: "pm"}, function(response) {
        jQuery("#actions").empty().append(response);
    });
}

// Показ статистики пользователя
function stat(user) {
    jQuery.post("user.php", {user: user, act: "statistics"}, function(response) {
        jQuery("#actions").empty().append(response);
    });
}

// Открыть модерацию пользователя
function moderate(user) {
    jQuery.post("user.php", {user: user, act: "moderate"}, function(response) {
        jQuery("#actions").empty().append(response);
    });
}

// Добавить/удалить из друзей
function addtofriends(user, type) {
    jQuery.post("user.php", {user: user, type: type, act: "addtofriends"}, function(response) {
        jQuery("#actions").empty().append(response);
    });
}

