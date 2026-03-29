<?php
declare(strict_types=1);

require_once 'include/bittorrent.php';
require_once 'include/super_loto_lib.php';
dbconn();

$result = super_loto_run_draw([
    'strict_schedule' => true,
    'log_file' => 'super_loto_cron.log',
]);

if (!$result['ok']) {
    super_loto_write_log('super_loto_cron.log', 'Автозапуск завершился без результата: ' . (string)$result['message']);
}
