<?php
require_once("include/bittorrent.php");
dbconn();
stdhead("ЧаВо сайта $SITENAME");

// Получение категорий
$res = sql_query("SELECT `id`, `question`, `flag` FROM `faq` WHERE `type`='categ' ORDER BY `order` ASC");
$faq_categ = [];

while ($arr = mysqli_fetch_assoc($res)) {
    $faq_categ[$arr['id']]['title'] = $arr['question'];
    $faq_categ[$arr['id']]['flag']  = $arr['flag'];
}

// Получение пунктов FAQ
$res = sql_query("SELECT `id`, `question`, `answer`, `flag`, `categ` FROM `faq` WHERE `type`='item' ORDER BY `order` ASC");

while ($arr = mysqli_fetch_assoc($res)) {
    $faq_categ[$arr['categ']]['items'][$arr['id']] = [
        'question' => $arr['question'],
        'answer'   => $arr['answer'],
        'flag'     => $arr['flag'],
    ];
}

// Обработка категорий
if (!empty($faq_categ)) {

    // Выделение осиротевших пунктов (без категории)
    $faq_orphaned = [];
    foreach ($faq_categ as $id => $data) {
        if (!isset($data['title'])) {
            if (!empty($data['items'])) {
                foreach ($data['items'] as $id2 => $item) {
                    $faq_orphaned[$id2] = $item;
                }
            }
            unset($faq_categ[$id]);
        }
    }

    // Содержание
    begin_frame("Содержание");

    foreach ($faq_categ as $id => $cat) {
        if ($cat['flag'] === '1') {
            echo "<ul><li><a href=\"#{$id}\"><b>{$cat['title']}</b></a>\n<ul>\n";
            if (!empty($cat['items'])) {
                foreach ($cat['items'] as $id2 => $item) {
                    if ($item['flag'] === '1') {
                        echo "<li><a href=\"#$id2\" class=\"altlink\">{$item['question']}</a></li>\n";
                    } elseif ($item['flag'] === '2') {
                        echo "<li><a href=\"#$id2\" class=\"altlink\">{$item['question']}</a> <img src=\"{$pic_base_url}updated.png\" alt=\"Обновлено\" title=\"Обновлено\" align=\"absbottom\"></li>\n";
                    } elseif ($item['flag'] === '3') {
                        echo "<li><a href=\"#$id2\" class=\"altlink\">{$item['question']}</a> <img src=\"{$pic_base_url}new.png\" alt=\"Новое\" title=\"Новое\" align=\"absbottom\"></li>\n";
                    }
                }
            }
            echo "</ul></li></ul><br />\n";
        }
    }

    end_frame();

    // Вывод самих категорий и вопросов
    foreach ($faq_categ as $id => $cat) {
        if ($cat['flag'] === '1') {
            $frame = "{$cat['title']} - <a href=\"#top\">Наверх</a>";
            begin_frame($frame);
            echo "<a name=\"$id\" id=\"$id\"></a>\n";

            if (!empty($cat['items'])) {
                foreach ($cat['items'] as $id2 => $item) {
                    if ($item['flag'] !== '0') {
                        echo "<br /><b>{$item['question']}</b><a name=\"$id2\" id=\"$id2\"></a><br />\n";
                        echo "<br />{$item['answer']}<br /><br />\n";
                    }
                }
            }

            end_frame();
        }
    }
}

stdfoot();
