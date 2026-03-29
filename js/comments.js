/* ===========================================================================
   SE_Comments JavaScript v0.2 (updated for PHP 8.1 backend)
   Depends: jQuery (aliased как $jq), опционально SCEditor
   =========================================================================== */

var SE_loadingImage = 'ajax-loader-big.gif';
var SE_CommentLoadingBar =
  '<div style="padding:20px;text-align:center;"><img src="/pic/' + SE_loadingImage + '" alt="loading" /></div>';

/* ------------------- Утилиты ------------------- */
(function (w, $) {
  'use strict';

  function safeInt(v) {
    var n = parseInt(v, 10);
    return isNaN(n) ? 0 : n;
  }

  function isBlank(str) {
    return !str || !String(str).trim();
  }

  function getEditorText($ta) {
    if (!$ta || !$ta.length) {
      return '';
    }

    try {
      if ($ta.sceditor) {
        var inst = $ta.sceditor('instance');
        if (inst) {
          if (inst.updateOriginal) inst.updateOriginal();
          return (inst.val ? inst.val() : $ta.val() || '').toString();
        }
      }
    } catch (e) {
      console.warn('SCEditor read error:', e);
    }

    return ($ta.val() || '').toString();
  }

  function showLoader($el) {
    $el.empty().html(SE_CommentLoadingBar);
  }

  // Единая обёртка для AJAX (ожидаем HTML-ответ)
  function ajaxHTML(url, data, $target, onSuccess) {
    if ($target) showLoader($target);

    return $.ajax({
      url: url,
      method: 'POST',
      data: data,
      dataType: 'html',
      cache: false,
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    }).done(function (html, status, xhr) {
      if ($target) $target.empty().html(html);
      if (typeof onSuccess === 'function') onSuccess(html, status, xhr);
    }).fail(function (xhr, status, err) {
      var msg = 'Ошибка ' + (xhr.status || '') + ': ' + (xhr.responseText || status || err || 'unknown');
      if ($target) $target.html('<div class="error" style="color:#b00; padding:10px;">' + msg + '</div>');
      console.error('[SE AJAX] ', msg, { url: url, data: data, status: status, err: err, xhr: xhr });
      alert('Не удалось выполнить запрос. Попробуйте ещё раз.\n\n' + msg);
    });
  }

  /* ------------------- API ------------------- */

  w.SE_EditComment = function (cid, tid) {
    cid = safeInt(cid);
    tid = safeInt(tid);
    var $box = $('#comment_text' + cid);
    $('#comment_edit_panel' + cid).hide();
    ajaxHTML('/addcomment.php', { do: 'edit_comment', cid: cid, tid: tid }, $box);
  };

  w.SE_SaveComment = function (cid, tid) {
    cid = safeInt(cid);
    tid = safeInt(tid);
    var $box = $('#comment_text' + cid);
    var $ta  = $('#edit_post_' + cid);
    var text = getEditorText($ta);

    if (isBlank(text)) {
      alert('Комментарий не может быть пустым!');
      $ta.trigger('focus');
      return false;
    }

    ajaxHTML('/addcomment.php', { do: 'save_comment', cid: cid, tid: tid, text: text }, $box, function () {
      $('#comment_edit_panel' + cid).show();
      SE_BindCommentEvents();
    });
  };

  w.SE_CommentCancel = function (cid, tid) {
    cid = safeInt(cid);
    tid = safeInt(tid);
    var $box = $('#comment_text' + cid);

    ajaxHTML('/addcomment.php', { do: 'save_cancel', cid: cid, tid: tid }, $box, function () {
      $('#comment_edit_panel' + cid).show();
      SE_BindCommentEvents();
    });
  };

w.SE_CommentQuote = function (cid, tid) {
  cid = safeInt(cid);
  tid = safeInt(tid);

  var $ta = $('#text');

  // 1) Синхронизируем SCEditor → textarea
  try {
    if ($ta.sceditor) {
      var instSync = $ta.sceditor('instance');
      if (instSync && instSync.updateOriginal) instSync.updateOriginal();
    }
  } catch (e) {
    console.warn('SCEditor updateOriginal ошибка:', e);
  }

  var current = getEditorText($ta);

  ajaxHTML('/addcomment.php', { do: 'comment_quote', cid: cid, tid: tid, text: current }, null, function (resp) {
    // resp — уже готовый BBCode с [quote=...][/quote] от бэкенда

    try {
      if ($ta.sceditor) {
        var inst = $ta.sceditor('instance');
        if (inst && inst.insertText) {
          // вставим именно в позицию курсора редактора
          inst.insertText(resp);
          if (inst.focus) inst.focus();
          return;
        }
      }
    } catch (e) {
      console.warn('SCEditor insertText ошибка:', e);
    }

    // Фолбэк без SCEditor: добавим в конец и вернём курсор
    var sep = current && !/\s$/.test(current) ? '\n\n' : '';
    var next = current + sep + resp;
    $ta.val(next).trigger('input').focus();
  });
};


  w.SE_SendComment = function (tid) {
    tid = safeInt(tid);
    var $ta  = $('#text');
    var text = getEditorText($ta);

    if (isBlank(text)) {
      alert('Комментарий не может быть пустым!');
      $ta.trigger('focus');
      return false;
    }

    ajaxHTML('/addcomment.php', { do: 'add_comment', tid: tid, text: text }, $('#comments_list'), function () {
      SE_BindCommentEvents();
      try {
        location.hash = 'startcomments';
      } catch (_) {}
    });
  };

  w.SE_OpenReply = function (cid, tid) {
    cid = safeInt(cid);
    tid = safeInt(tid);
    var $box = $('#comment_reply_box' + cid);
    ajaxHTML('/addcomment.php', { do: 'reply_form', cid: cid, tid: tid }, $box, function () {
      SE_BindCommentEvents();
    });
  };

  w.SE_ReplyCancel = function (cid) {
    cid = safeInt(cid);
    $('#comment_reply_box' + cid).empty();
  };

  w.SE_SendReply = function (cid, tid) {
    cid = safeInt(cid);
    tid = safeInt(tid);
    var $ta = $('#reply_post_' + cid);
    var text = getEditorText($ta);

    if (isBlank(text)) {
      alert('Ответ не может быть пустым!');
      $ta.trigger('focus');
      return false;
    }

    ajaxHTML('/addcomment.php', { do: 'add_comment', tid: tid, parent_id: cid, text: text }, $('#comments_list'), function () {
      SE_BindCommentEvents();
      try {
        location.hash = 'comm' + cid;
      } catch (_) {}
    });
  };

  w.SE_DeleteComment = function (cid, tid) {
    cid = safeInt(cid);
    tid = safeInt(tid);
    var $list = $('#comments_list');

    if (!confirm('Вы действительно хотите удалить этот комментарий?')) {
      return false;
    }

    ajaxHTML('/addcomment.php', { do: 'delete_comment', cid: cid, tid: tid }, $list, function () {
      // В ответ может прийти обновлённый список + формы
      SE_BindCommentEvents();
    });
  };

  w.SE_ViewOriginal = function (cid, tid) {
    cid = safeInt(cid);
    tid = safeInt(tid);
    var $box = $('#comment_text' + cid);

    ajaxHTML('/addcomment.php', { do: 'view_original', cid: cid, tid: tid }, $box, function () {
      SE_BindCommentEvents();
    });
  };

  w.SE_RecoverOriginal = function (cid, tid) {
    cid = safeInt(cid);
    tid = safeInt(tid);
    var $box = $('#comment_text' + cid);

    ajaxHTML('/addcomment.php', { do: 'recover_original', cid: cid, tid: tid }, $box, function () {
      SE_BindCommentEvents();
    });
  };

  // Карма (оставил jQuery без алиаса, как у тебя в исходнике)
  w.karma = function (id, type, act) {
    id = safeInt(id);
    return jQuery.post('karma.php', { id: id, act: act, type: type }, function (response) {
      jQuery('#karma' + id).empty().append(response);
    });
  };

  w.SE_BindCommentEvents = function () {
    var $doc = $jq(document);

    $doc.off('click.seComments', '.karma-btn').on('click.seComments', '.karma-btn', function () {
      var id   = safeInt($jq(this).data('id'));
      var type = $jq(this).data('type');
      var act  = $jq(this).data('act');
      karma(id, type, act);
    });

    $doc.off('click.seComments', '.comment-quote').on('click.seComments', '.comment-quote', function () {
      var id  = safeInt($jq(this).data('id'));
      var tid = safeInt($jq(this).data('tid'));
      SE_CommentQuote(id, tid);
    });

    $doc.off('click.seComments', '.comment-edit').on('click.seComments', '.comment-edit', function () {
      var id  = safeInt($jq(this).data('id'));
      var tid = safeInt($jq(this).data('tid'));
      SE_EditComment(id, tid);
    });

    $doc.off('click.seComments', '.comment-reply').on('click.seComments', '.comment-reply', function () {
      var id  = safeInt($jq(this).data('id'));
      var tid = safeInt($jq(this).data('tid'));
      SE_OpenReply(id, tid);
    });

    $doc.off('click.seComments', '.comment-original').on('click.seComments', '.comment-original', function () {
      var id  = safeInt($jq(this).data('id'));
      var tid = safeInt($jq(this).data('tid'));
      SE_ViewOriginal(id, tid);
    });

    $doc.off('click.seComments', '.comment-delete').on('click.seComments', '.comment-delete', function () {
      var id  = safeInt($jq(this).data('id'));
      var tid = safeInt($jq(this).data('tid'));
      SE_DeleteComment(id, tid);
    });
  };

  // Автоинициализация после загрузки DOM
  $jq(function () { SE_BindCommentEvents(); });

})(window, $jq);
