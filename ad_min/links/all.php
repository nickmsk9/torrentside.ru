<?php
// ad_min/links/_all.php
// Единый список ссылок для сетки админки (новый формат: только return [...])

return [

  // ===== Пользователи =====
  [
    'title'    => 'Смена класса',
    'url'      => 'setclass.php',
    'icon'     => 'war.png',          // убедись, что файл есть: ad_min/pic/war.png
    'category' => 'Пользователи',
    'desc'     => 'Изменение класса пользователя',
  ],
  [
    'title'    => 'Смена пароля',
    'url'      => 'admincp.php?op=iUsers',
    'icon'     => 'password.png',
    'category' => 'Пользователи',
    'desc'     => 'Управление паролями пользователей',
  ],

  // ===== Бонусы =====
  [
    'title'    => 'Категории бонуса',
    'url'      => 'mybonus.php',
    'icon'     => 'bon.png',
    'category' => 'Бонусы',
    'desc'     => 'Управление категориями бонусной системы',
  ],
  [
    'title'    => 'Пункт обмена',
    'url'      => 'bonus_exchange.php',
    'icon'     => 'bon-exchange.svg',
    'category' => 'Бонусы',
    'desc'     => 'Обмен бонусов на трафик и привилегии',
  ],
  [
    'title'    => 'Логи операций',
    'url'      => 'bonus_logs.php',
    'icon'     => 'bon-log.svg',
    'category' => 'Бонусы',
    'desc'     => 'История списаний и начислений',
  ],

  // ===== Игры =====
  [
    'title'    => 'Разыграть Супер-Лото',
    'url'      => 'loto-start.php',
    'icon'     => 'lt.png',
    'category' => 'Игры',
    'desc'     => 'Запуск и управление розыгрышем',
  ],

  // ===== Чат =====
  [
    'title'    => 'Очистить чат',
    'url'      => 'clear.php',
    'icon'     => 'add.png',
    'category' => 'Чат',
    'desc'     => 'Удаление сообщений чата/шутбокса',
  ],

  // ===== Контент =====
  [
    'title'    => 'Новости',
    'url'      => 'news.php',
    'icon'     => 'gl.png',
    'category' => 'Контент',
    'desc'     => 'Управление новостями',
  ],
  [
    'title'    => 'Категории',
    'url'      => 'category.php',
    'icon'     => 'cat.png',
    'category' => 'Контент',
    'desc'     => 'Редактор категорий раздач',
  ],
  [
    'title'    => 'Управление тегами',
    'url'      => 'tags-admin.php',
    'icon'     => 'tags.png',
    'category' => 'Контент',
    'desc'     => 'Создание и правка тегов',
  ],
  [
    'title'    => 'Настройки ЧаВо',
    'url'      => 'admincp.php?op=FaqAdmin',
    'icon'     => 'faq.png',
    'category' => 'Контент',
    'desc'     => 'Раздел «Вопросы и ответы»',
  ],

  // ===== Коммуникации =====
  [
    'title'    => 'Массовое ЛС',
    'url'      => 'staffmess.php',
    'icon'     => 'ls.png',
    'category' => 'Коммуникации',
    'desc'     => 'Рассылка личных сообщений пользователям',
  ],

  // ===== Безопасность =====
  [
    'title'    => 'Спам-контроль',
    'url'      => 'spam.php',
    'icon'     => 'spam.png',
    'category' => 'Безопасность',
    'desc'     => 'Фильтры и поиск спама',
  ],

  // ===== Система =====
  [
    'title'    => 'Оптимизация БД',
    'url'      => 'statusdb.php',
    'icon'     => 'db.png',
    'category' => 'Система',
    'desc'     => 'Проверка и оптимизация таблиц',
  ],
  [
    'title'    => 'Бэкап БД',
    'url'      => 'backup/dumper.php',
    'icon'     => 'dbb.png',
    'category' => 'Система',
    'desc'     => 'Резервное копирование базы данных',
  ],
  [
    'title'    => 'Выполнить клинап',
    'url'      => 'docleanup.php',
    'icon'     => 'dc.png',           // исправлено: dc.png (латинская "c")
    'category' => 'Система',
    'desc'     => 'Обслуживание и очистка временных данных',
  ],
  [
    'title'    => 'Лог сайта',
    'url'      => 'log.php',
    'icon'     => 'map.png',
    'category' => 'Система',
    'desc'     => 'Системный журнал событий',
  ],

];
