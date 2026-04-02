const loading = '<img src="pic/upload.gif" alt="Загрузка...">';

$jq(function () {
  const $doc = $jq(document);
  const $body = $jq("#body");
  const $loading = $jq("#loading");
  const $tabsRoot = $jq("#tabs").length ? $jq("#tabs") : $doc; // fallback, если контейнера нет

  let pendingXHR = null;

  $doc.off("click.torrentKarma", ".torrent-karma-btn").on("click.torrentKarma", ".torrent-karma-btn", function (e) {
    e.preventDefault();

    const $btn = $jq(this);
    const id = parseInt($btn.data("id"), 10);
    const act = String($btn.data("act") || "");
    const type = String($btn.data("type") || "torrent");

    if (!Number.isFinite(id) || !act) {
      return;
    }

    $jq.post("karma.php", { id, act, type }, function (response) {
      $jq("#torrent-karma-" + id).html(response);
    });
  });

  // делегирование: живёт и после перерисовки вкладок
  $tabsRoot.on("click", ".tab", function (e) {
    e.preventDefault();

    const $tab = $jq(this);
    if ($tab.hasClass("active") || $tab.data("loading")) return;

    // данные
    const torrent = $body.data("torrent") ?? $body.attr("torrent");
    const act = $tab.attr("id");

    if (!torrent || !act) return;

    // отменяем предыдущий незавершённый запрос, чтобы не было гонок
    if (pendingXHR && pendingXHR.readyState !== 4) {
      try { pendingXHR.abort(); } catch (_) {}
    }

    // UI: проставим состояния одним батчем
    $tab.data("loading", true);
    $loading.html(loading);
    $tab
      .addClass("active")
      .attr("aria-selected", "true")
      .siblings(".tab, span")
      .removeClass("active")
      .attr("aria-selected", "false");

    // ajax
    pendingXHR = $jq.ajax({
      url: "torrent.php",
      type: "POST",
      data: { torrent, act },
      headers: { "X-Requested-With": "XMLHttpRequest" },
      timeout: 20000
    })
    .done(function (html) {
      // вставка контента
      $body.html(html);
      if (typeof window.tsRefreshTorrentGallery === "function") {
        window.tsRefreshTorrentGallery();
      }
    })
    .fail(function (xhr, _status) {
      if (_status !== "abort") {
        $loading.html('<span style="color:red;">Ошибка загрузки</span>');
      }
    })
    .always(function () {
      $tab.data("loading", false);
      // очищаем индикатор, если это последний активный запрос
      if (pendingXHR && pendingXHR.readyState === 4) {
        $loading.empty();
      }
    });
  });
});
