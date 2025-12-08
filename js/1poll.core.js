// Используем $jq как у тебя
(function ($) {
  "use strict";

  // Универсальная функция извлечения процента
  function getPct(el) {
    const $el = $(el);
    let pct = $el.data("pct");                 // приоритет: data-pct="63.5"
    if (pct == null) pct = $el.attr("name");   // старый способ: name="63.5"
    if (pct == null) {
      const m = String($el.attr("style") || "").match(/width\s*:\s*([\d.]+)%/i);
      if (m) pct = m[1];
    }
    pct = parseFloat(pct);
    if (!isFinite(pct)) pct = 0;
    return Math.max(0, Math.min(100, pct));
  }

  // Анимация полос (без очередей и миганий)
  function animateBars(speed = 900) {
    const $bars = $("#results .bar, #results .barmax");
    if (!$bars.length) return;

    $bars.stop(true, true).each(function () {
      const pct = getPct(this);
      // начальная ширина 0, затем анимация до pct%
      $(this).css({ width: 0 }).animate({ width: pct + "%" }, speed);
    });
  }

  // Показ/скрытие загрузки
  function setLoading(text) {
    $("#loading_poll").text(text || "Загрузка...").attr("aria-live", "polite").show();
  }
  function clearLoading() {
    $("#loading_poll").fadeOut(120);
  }

  // === PUBLIC ===
  window.loadpoll = function () {
    setLoading("Загрузка... Ждите...");
    // не показываем контейнер заранее, чтобы не мигал
    $.ajax({
      url: "poll.core.php",
      method: "POST",
      data: { action: "load" },
      dataType: "html",
      timeout: 10000
    })
      .done(function (html) {
        $("#poll_container").html(html).fadeIn(120, function () {
          animateBars(900);
        });
      })
      .fail(function () {
        $("#poll_container").html('<div class="error">Ошибка загрузки опроса.</div>');
      })
      .always(clearLoading);
  };

  let voting = false;
  window.vote = function () {
    if (voting) return;
    const pollId = $("#pollId").val();
    const choice = $("#choice").val();

    $("#vote_b").prop("disabled", true);
    $("#poll_container")
      .empty()
      .append('<div id="loading_poll">Ждите... Голос отправляется...</div>');

    voting = true;
    $.ajax({
      url: "poll.core.php",
      method: "POST",
      data: { action: "vote", pollId: pollId, choice: choice },
      dataType: "json",
      timeout: 10000
    })
      .done(function (response) {
        if (response && response.status === 1) {
          loadpoll(); // перерисуем и анимируем
        } else {
          $("#loading_poll").hide().text(response?.msg || "Ошибка голосования.").fadeIn(120);
          $("#vote_b").prop("disabled", false);
          voting = false;
        }
      })
      .fail(function () {
        $("#loading_poll").hide().text("Сеть недоступна. Попробуйте ещё раз.").fadeIn(120);
        $("#vote_b").prop("disabled", false);
        voting = false;
      });
  };

  window.addvote = function (val) {
    $("#choice").val(val);
    $("#vote_b").show().prop("disabled", false);
  };

})($jq);
