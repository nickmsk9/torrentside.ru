<?php
declare(strict_types=1);

require_once __DIR__ . '/include/bittorrent.php';

dbconn(false);
loggedinorreturn();
stderr('Форум удалён', 'Модуль управления форумом больше недоступен.');
