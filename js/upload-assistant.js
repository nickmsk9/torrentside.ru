(function (win, doc) {
  'use strict';

  function cssEscape(value) {
    if (win.CSS && typeof win.CSS.escape === 'function') {
      return win.CSS.escape(value);
    }
    return String(value).replace(/[^A-Za-z0-9_\-:.]/g, '\\$&');
  }

  function escapeHtml(value) {
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function normalizeComparable(value) {
    return String(value == null ? '' : value)
      .toLowerCase()
      .replace(/ё/g, 'е')
      .replace(/дубляж/g, 'дублированный')
      .replace(/web[\s-]?dl/g, 'webdl')
      .replace(/web[\s-]?rip/g, 'webrip')
      .replace(/[^a-z0-9а-я]+/gi, '');
  }

  function getField(form, name) {
    if (!form || !name) {
      return null;
    }
    if (form.elements && form.elements[name]) {
      return form.elements[name];
    }
    return form.querySelector('[name="' + cssEscape(name) + '"]');
  }

  function getEditorInstance(field) {
    try {
      if (!field || !win.jQuery || !win.jQuery.sceditor || typeof win.jQuery.sceditor.instance !== 'function') {
        return null;
      }
      return win.jQuery.sceditor.instance(field);
    } catch (err) {
      return null;
    }
  }

  function getFieldValue(field) {
    if (!field) {
      return '';
    }
    var instance = getEditorInstance(field);
    if (instance && typeof instance.val === 'function') {
      return instance.val();
    }
    return typeof field.value === 'string' ? field.value : '';
  }

  function setSelectValue(field, value) {
    var targetValue = String(value == null ? '' : value).trim();
    if (!targetValue) {
      return false;
    }

    var lowerValue = targetValue.toLowerCase();
    var normalizedValue = normalizeComparable(targetValue);
    var aliases = [normalizedValue];
    if (normalizedValue.indexOf('дублированный') !== -1) {
      aliases.push('профессиональныйдублированный');
    }

    var matched = false;
    Array.prototype.forEach.call(field.options || [], function (option) {
      if (matched) {
        return;
      }

      var optionValue = String(option.value || '').trim();
      var optionLabel = String(option.text || '').trim();
      var optionValueLower = optionValue.toLowerCase();
      var optionLabelLower = optionLabel.toLowerCase();
      var optionValueNormalized = normalizeComparable(optionValue);
      var optionLabelNormalized = normalizeComparable(optionLabel);

      if (optionValueLower === lowerValue || optionLabelLower === lowerValue) {
        field.value = option.value;
        matched = true;
        return;
      }

      if (
        aliases.indexOf(optionValueNormalized) !== -1 ||
        aliases.indexOf(optionLabelNormalized) !== -1 ||
        optionValueNormalized.indexOf(normalizedValue) !== -1 ||
        optionLabelNormalized.indexOf(normalizedValue) !== -1 ||
        normalizedValue.indexOf(optionValueNormalized) !== -1 ||
        normalizedValue.indexOf(optionLabelNormalized) !== -1
      ) {
        field.value = option.value;
        matched = true;
      }
    });

    return matched;
  }

  function setFieldValue(field, value) {
    if (!field || value == null) {
      return;
    }

    var nextValue = String(value).trim();
    if (!nextValue) {
      return;
    }

    if (field.tagName === 'SELECT') {
      if (!setSelectValue(field, nextValue)) {
        field.value = nextValue;
      }
      field.dispatchEvent(new Event('change', { bubbles: true }));
      return;
    }

    var instance = getEditorInstance(field);
    if (instance && typeof instance.val === 'function') {
      instance.val(nextValue);
      field.value = nextValue;
    } else {
      field.value = nextValue;
    }

    field.dispatchEvent(new Event('input', { bubbles: true }));
    field.dispatchEvent(new Event('change', { bubbles: true }));
  }

  function setStatus(root, message, state) {
    var box = root.querySelector('.upload-ai-status');
    if (!box) {
      return;
    }
    box.textContent = message || '';
    box.className = 'upload-ai-status is-visible' + (state ? ' is-' + state : '');
  }

  function renderRows(root, selector, rows, kind) {
    var box = root.querySelector(selector);
    if (!box) {
      return;
    }

    if (!rows || !rows.length) {
      box.innerHTML = kind === 'issues'
        ? '<li>Критичных замечаний не найдено.</li>'
        : '<span class="upload-ai-pill">Нет данных</span>';
      return;
    }

    if (kind === 'issues') {
      box.innerHTML = rows.map(function (row) {
        var severity = escapeHtml(row.severity || 'warning');
        var message = escapeHtml(row.message || '');
        return '<li class="' + severity + '">' + message + '</li>';
      }).join('');
      return;
    }

    var className = 'upload-ai-pill';
    if (kind === 'families') {
      className = 'upload-ai-family';
    } else if (kind === 'categories') {
      className = 'upload-ai-cat';
    }

    box.innerHTML = rows.map(function (row) {
      var label = escapeHtml(row.label || row.name || row.key || '');
      var value = row.probability != null ? escapeHtml(row.probability) + '%' : escapeHtml(row.value || '');
      return '<span class="' + className + '"><strong>' + label + '</strong> ' + value + '</span>';
    }).join('');
  }

  function renderSummary(root, data) {
    var list = root.querySelector('.upload-ai-summary-list');
    if (!list) {
      return;
    }

    var items = [];
    if (data.release && data.release.release_name) {
      items.push('Нормализованное имя релиза: ' + data.release.release_name);
    }
    if (data.release && data.release.display_title && data.release.display_title !== data.release.release_name) {
      items.push('Основное название распознано как: ' + data.release.display_title);
    }
    if (data.release && data.release.original_title) {
      items.push('Оригинальное название: ' + data.release.original_title);
    }
    if (data.family_probabilities && data.family_probabilities.length) {
      items.push('Наиболее вероятный тип: ' + data.family_probabilities[0].label + ' (' + data.family_probabilities[0].probability + '%).');
    }
    if (data.wikipedia) {
      if (data.wikipedia.ru && data.wikipedia.ru.url) {
        items.push('Подключена русская справка Wikipedia: ' + data.wikipedia.ru.title);
      } else if (data.wikipedia.en && data.wikipedia.en.url) {
        items.push('Подключена английская справка Wikipedia: ' + data.wikipedia.en.title);
      } else {
        items.push('Удалённая справка не понадобилась: ассистент собрал черновик по локальным данным.');
      }
    }
    if (data.poster_url) {
      items.push('Подобран постер по найденной справке.');
    }
    if (data.tags && data.tags.length) {
      items.push('Подобраны теги: ' + data.tags.join(', '));
    }
    if (!items.length) {
      items.push('Ассистент заполнил форму по доступным данным локально.');
    }

    list.innerHTML = items.map(function (item) {
      return '<li>' + escapeHtml(item) + '</li>';
    }).join('');
  }

  function renderResult(root, data) {
    var results = root.querySelector('.upload-ai-results');
    if (!results) {
      return;
    }

    var release = data.release || {};
    var pillRows = [];
    if (release.display_title) {
      pillRows.push({ label: 'Название', value: release.display_title });
    }
    if (release.year) {
      pillRows.push({ label: 'Год', value: release.year });
    }
    if (release.quality) {
      pillRows.push({ label: 'Качество', value: release.quality });
    }
    if (release.format) {
      pillRows.push({ label: 'Формат', value: release.format });
    }
    if (release.resolution) {
      pillRows.push({ label: 'Разрешение', value: release.resolution });
    }
    if (release.translation) {
      pillRows.push({ label: 'Перевод', value: release.translation });
    }
    if (release.audio_codec) {
      pillRows.push({ label: 'Аудио', value: release.audio_codec + (release.audio_bitrate ? ' / ' + release.audio_bitrate : '') });
    }
    if (data.genres && data.genres.length) {
      pillRows.push({ label: 'Жанры', value: data.genres.join(', ') });
    }

    renderRows(root, '.upload-ai-pill-row', pillRows, 'pills');
    renderRows(root, '.upload-ai-family-row', (data.family_probabilities || []).map(function (row) {
      return { label: row.label, probability: row.probability };
    }), 'families');
    renderRows(root, '.upload-ai-cat-row', (data.category_candidates || []).map(function (row) {
      return { label: row.name, probability: row.probability };
    }), 'categories');
    renderSummary(root, data);
    renderRows(root, '.upload-ai-issue-list', (data.audit && data.audit.issues) ? data.audit.issues : [], 'issues');
    results.classList.add('is-visible');
  }

  function applyFieldValues(form, fieldValues) {
    Object.keys(fieldValues || {}).forEach(function (name) {
      var field = getField(form, name);
      if (!field || typeof field.length === 'number' && !field.tagName && field.length > 1) {
        return;
      }
      setFieldValue(field, fieldValues[name]);
    });
  }

  function buildSlotMeta(label, className) {
    var node = doc.createElement('div');
    node.className = className;
    node.textContent = label;
    return node;
  }

  function mountFieldToSlot(form, root, name, config) {
    var slot = root.querySelector('[data-upload-slot="' + cssEscape(name) + '"]');
    var field = getField(form, name);
    if (!slot || !field) {
      return;
    }
    if (field.getAttribute('data-upload-ai-mounted') === '1') {
      return;
    }

    var row = field.closest('tr');
    var wrapper = doc.createElement('div');
    wrapper.className = 'upload-ai-slot-field';

    if (!slot.classList.contains('upload-ai-slot-main') && config.label) {
      wrapper.appendChild(buildSlotMeta(config.label, 'upload-ai-slot-label'));
    }

    field.setAttribute('data-upload-ai-mounted', '1');
    field.removeAttribute('size');

    if (field.tagName === 'INPUT' && field.type === 'file') {
      field.classList.add('upload-ai-file-input');
    } else {
      field.classList.add('upload-ai-inline-input');
      if (config.placeholder && typeof field.placeholder === 'string') {
        field.placeholder = config.placeholder;
      }
      field.setAttribute('autocomplete', 'off');
      field.setAttribute('spellcheck', 'false');
    }

    wrapper.appendChild(field);

    if (!slot.classList.contains('upload-ai-slot-main') && config.hint) {
      wrapper.appendChild(buildSlotMeta(config.hint, 'upload-ai-slot-hint'));
    }

    slot.innerHTML = '';
    slot.appendChild(wrapper);

    if (row && row.closest('[data-upload-assistant]') !== root) {
      row.style.display = 'none';
    }
  }

  function enhanceLayout(root) {
    if (root.getAttribute('data-upload-ai-layout') === '1') {
      return;
    }

    var form = root.closest('form');
    if (!form) {
      return;
    }

    var context = root.getAttribute('data-context') || 'generic';
    var slotMap = {
      generic: {
        name: {
          placeholder: 'Вставьте одно название релиза, фильма, сериала или сборки',
          label: 'Название релиза',
          hint: 'Достаточно одной строки вроде Avatar 2 2022 WEB-DL 1080p Dub.'
        },
        tfile: {
          label: '.torrent-файл',
          hint: 'Структура файлов помогает точнее определить категорию, формат и размер.'
        }
      },
      film: {
        name: {
          placeholder: 'Введите главное или русское название фильма',
          label: 'Основное название',
          hint: 'Можно писать по-русски или сразу в оригинале.'
        },
        origname: {
          placeholder: 'Оригинальное название, если знаете',
          label: 'Оригинальное название',
          hint: 'Необязательно. Если оставить пустым, ассистент попробует найти сам.'
        },
        tfile: {
          label: '.torrent-файл',
          hint: 'По составу файлов ассистент поймёт качество, формат и примерный тип релиза.'
        }
      }
    };

    var config = slotMap[context] || slotMap.generic;
    Object.keys(config).forEach(function (name) {
      mountFieldToSlot(form, root, name, config[name]);
    });

    root.setAttribute('data-upload-ai-layout', '1');
  }

  async function runAssistant(root) {
    var form = root.closest('form');
    var button = root.querySelector('.upload-ai-run');
    if (!form || !button) {
      return;
    }

    var titleField = getField(form, 'name');
    var originalTitleField = getField(form, 'origname');
    var torrentField = getField(form, 'tfile');
    var descrField = getField(form, 'descr');
    var csrfField = getField(form, 'csrf_token');
    var snapshotField = root.querySelector('.upload-ai-snapshot');

    var titleValue = getFieldValue(titleField).trim();
    var altTitleValue = getFieldValue(originalTitleField).trim();
    var effectiveTitle = titleValue || altTitleValue;
    var file = torrentField && torrentField.files && torrentField.files[0] ? torrentField.files[0] : null;

    if (!effectiveTitle && !file) {
      setStatus(root, 'Сначала введите название релиза или выберите .torrent для анализа.', 'error');
      return;
    }

    button.disabled = true;
    setStatus(root, 'Ассистент разбирает название, структуру торрент-файла и дополнительные данные...', '');

    var payload = new FormData();
    payload.append('context', root.getAttribute('data-context') || 'generic');
    payload.append('title', effectiveTitle);
    payload.append('alt_title', altTitleValue);
    payload.append('descr', getFieldValue(descrField));
    payload.append('ai_mediainfo', getFieldValue(getField(form, 'ai_mediainfo')));
    payload.append('ai_nfo', getFieldValue(getField(form, 'ai_nfo')));
    payload.append('use_wikipedia', root.querySelector('.upload-ai-use-wiki') && root.querySelector('.upload-ai-use-wiki').checked ? '1' : '0');
    if (csrfField && csrfField.value) {
      payload.append('csrf_token', csrfField.value);
    }
    if (file) {
      payload.append('tfile', file);
    }

    try {
      var response = await fetch('upload_assistant.php', {
        method: 'POST',
        body: payload,
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin'
      });

      var data = await response.json();
      if (!response.ok || !data || data.ok === false) {
        throw new Error(data && data.error ? data.error : 'Не удалось выполнить AI-анализ.');
      }

      applyFieldValues(form, data.field_values || {});
      if (snapshotField) {
        snapshotField.value = JSON.stringify(data);
      }
      renderResult(root, data);
      setStatus(root, 'Черновик релиза готов: название, описание, теги и рекомендации уже подставлены в форму.', 'success');
    } catch (error) {
      setStatus(root, error && error.message ? error.message : 'AI-анализ завершился ошибкой.', 'error');
    } finally {
      button.disabled = false;
    }
  }

  function boot() {
    Array.prototype.forEach.call(doc.querySelectorAll('[data-upload-assistant]'), function (root) {
      if (root.getAttribute('data-upload-assistant-ready') === '1') {
        return;
      }

      enhanceLayout(root);

      root.setAttribute('data-upload-assistant-ready', '1');
      root.addEventListener('click', function (event) {
        var button = event.target.closest('.upload-ai-run');
        if (!button) {
          return;
        }
        event.preventDefault();
        runAssistant(root);
      });
    });
  }

  if (doc.readyState === 'loading') {
    doc.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})(window, document);
