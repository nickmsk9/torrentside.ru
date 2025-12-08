<?php
if (!defined('UC_SYSOP')) {
    die('Direct access denied.');
}

/**
 * Вспомогалка: безопасный echo либо Smarty->fetch()
 * $tpl – относительный путь до шаблона внутри templates/
 * $vars – переменные для шаблона
 * $fallback – строковый HTML на случай, если нет $smarty
 */
function _ui_echo_via_smarty(string $tpl, array $vars, string $fallback): void {
    global $smarty;
    if (isset($smarty) && $smarty instanceof Smarty) {
        // Передаём переменные только в локальную выборку
        $smarty->assign($vars);
        // echo вместо display, чтобы не ломать begin_/end_ композицию
        echo $smarty->fetch($tpl);
        // Важно: не оставляем "залипших" assign'ов
        foreach (array_keys($vars) as $k) {
            $smarty->clearAssign($k);
        }
    } else {
        echo $fallback;
    }
}

// ===== Начало главного блока =====
function begin_main_frame(): void {
    $fallback = <<<HTML
<table class="main" width="100%" border="0" cellspacing="0" cellpadding="0">
  <tr>
    <td class="embedded2">
HTML;
    _ui_echo_via_smarty(
        'ui/main_frame_begin.tpl',
        [],
        $fallback
    );
}

// ===== Конец главного блока =====
function end_main_frame(): void {
    $fallback = "</td></tr></table>\n";
    _ui_echo_via_smarty(
        'ui/main_frame_end.tpl',
        [],
        $fallback
    );
}

// ===== Начало таблицы =====
function begin_table(bool $fullwidth = false, int $padding = 5): void {
    $width = $fullwidth ? ' width="100%"' : '';
    $fallback = "<table class=\"main\"{$width} border=\"1\" cellspacing=\"0\" cellpadding=\"{$padding}\">\n";
    _ui_echo_via_smarty(
        'ui/table_begin.tpl',
        ['fullwidth' => $fullwidth, 'padding' => $padding],
        $fallback
    );
}

// ===== Конец таблицы =====
function end_table(): void {
    $fallback = "</td></tr></table>\n";
    _ui_echo_via_smarty(
        'ui/table_end.tpl',
        [],
        $fallback
    );
}

// ===== Начало обрамляющего фрейма =====
function begin_frame(string $caption = "", string $width = "100", bool $center = false, int $padding = 10): void {
    // ВНИМАНИЕ: сохраняем ТЕ ЖЕ самые дивы/таблицы, что были у тебя — 1:1
    // ($width и $padding в твоём оригинале не использовались — оставляем как есть)
    if ($caption) {
        $fallback = <<<HTML
<div class="new">
  <div class="n_1"><div class="n_2"><div class="n_3"><div class="n_4"><div class="n_5">
  <div class="n_6"><div class="n_7"><div class="n_8"><div class="nn">
  <div align="right" class="cat">
    <table cellpadding="0" cellspacing="0">
      <tr>
        <td>
          <div class="cat-1"><div class="cat-2"><div class="cat-3">
            <div class="category">{$caption}</div>
          </div></div></div>
        </td>
      </tr>
    </table>
  </div>
  <div class="n_t">
    <div class="tit"><h1>{$caption}</h1></div>
  </div>
  <div class="news"><br />
HTML;
        // Сохраняем оригинальное поведение align=center (в оригинале оно не закрывалось)
        if ($center) {
            $fallback .= "<div align=\"center\">\n";
        }
        _ui_echo_via_smarty(
            'ui/frame_begin.tpl',
            ['caption' => $caption, 'center' => $center],
            $fallback
        );
    } else {
        // если нет caption — в твоём коде begin_frame ничего не выводил (кроме опционального center)
        if ($center) {
            _ui_echo_via_smarty(
                'ui/frame_begin_empty_center.tpl',
                ['center' => true],
                "<div align=\"center\">\n"
            );
        }
    }
}

// ===== Вставка дополнительного блока в фрейм =====
function attach_frame(int $padding = 10): void {
    // твой оригинал генерил только разделитель строк
    $fallback = "</td></tr><tr><td style=\"border-top: 0px\">\n";
    _ui_echo_via_smarty(
        'ui/frame_attach.tpl',
        [],
        $fallback
    );
}

// ===== Закрытие фрейма =====
function end_frame(): void {
    // ВАЖНО: это тот же гигантский хвост из твоего оригинала — без изменений
    $fallback = <<<HTML
<br />
</div></div></div></div></div>
</div></div></div></div></div>
</div></div></div></div></div>
</div></div></div></div></div>
</div></div></div></div></div>
</div></div></div></div></div>
</div></div></div></div></div>
HTML;
    _ui_echo_via_smarty(
        'ui/frame_end.tpl',
        [],
        $fallback
    );
}

// ===== Фрейм со смайлами =====
function insert_smilies_frame(): void {
    global $smilies, $DEFAULTBASEURL, $smarty;

    begin_frame("Смайлы");
    // Тело таблицы отдаём через Smarty, но оставляем прежнюю структуру
    $vars = [
        'smilies' => $smilies,
        'baseurl' => $DEFAULTBASEURL,
    ];
    _ui_echo_via_smarty(
        'ui/smilies_frame.tpl',
        $vars,
        // fallback = точный старый HTML (на случай отсутствия Smarty)
        (function () use ($smilies, $DEFAULTBASEURL) {
            $h = function($s) { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); };
            $out  = "<table align=\"center\" class=\"main\" border=\"1\" cellspacing=\"0\" cellpadding=\"5\">";
            $out .= "<tr><td class=\"colhead\">Написание</td><td class=\"colhead\">Смайл</td></tr>\n";
            foreach ($smilies as $code => $url) {
                $out .= "<tr><td>".$h($code)."</td><td><img src=\"".$h($DEFAULTBASEURL)."/pic/smilies/".$h($url)."\" alt=\"\"></td></tr>\n";
            }
            $out .= "</table>";
            return $out;
        })()
    );
    end_frame();
}
