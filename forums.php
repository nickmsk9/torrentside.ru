<?php
declare(strict_types=1);

require_once __DIR__ . '/include/bittorrent.php';

dbconn(false);
stderr('Форум удалён', 'Встроенный форум полностью выпилен из движка.');
