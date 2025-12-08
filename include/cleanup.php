<?php
// Защита от прямого вызова
if (!defined('IN_TRACKER')) die('Hacking attempt!');

function docleanup(): void {
	global $torrent_dir, $use_ttl, $autoclean_interval, $ttl_days, $tracker_lang, $mysqli;

	// Объявляем переменные по умолчанию
	$signup_timeout = 3 * 86400;       // 3 дня
	$points_per_cleanup = 0.5;         // бонус за сидирование
	$max_dead_torrent_time = 7 * 86400; // 7 дней

	@set_time_limit(0);
	@ignore_user_abort(true);

	// === Удаление торрентов без .torrent-файлов ===
	do {
		$res = sql_query("SELECT id FROM torrents") or sqlerr(__FILE__, __LINE__);
		$ar = [];
		while ($row = mysqli_fetch_array($res, MYSQLI_NUM)) {
			$ar[(int)$row[0]] = 1;
		}
		if (!count($ar)) break;

		$dp = @opendir($torrent_dir);
		if (!$dp) break;

		$ar2 = [];
		while (($file = readdir($dp)) !== false) {
			if (!preg_match('/^(\d+)\.torrent$/', $file, $m)) continue;
			$id = (int)$m[1];
			$ar2[$id] = 1;
			if (isset($ar[$id])) continue;
			@unlink($torrent_dir . "/$file");
		}
		closedir($dp);

		$delids = array_diff(array_keys($ar), array_keys($ar2));
		if (count($delids)) {
			sql_query("DELETE FROM torrents WHERE id IN (" . implode(",", $delids) . ")") or sqlerr(__FILE__, __LINE__);
		}

		// Удаляем лишние записи из peers и files
		foreach (['peers', 'files'] as $table) {
			$res = sql_query("SELECT torrent FROM $table GROUP BY torrent") or sqlerr(__FILE__, __LINE__);
			$delids = [];
			while ($row = mysqli_fetch_array($res, MYSQLI_NUM)) {
				if (!isset($ar[(int)$row[0]])) $delids[] = (int)$row[0];
			}
			if (count($delids)) {
				sql_query("DELETE FROM $table WHERE torrent IN (" . implode(",", $delids) . ")") or sqlerr(__FILE__, __LINE__);
			}
		}
	} while (false);

	// === Чистим неактивных ===
	$deadtime = deadtime();
	sql_query("DELETE FROM peers WHERE last_action < FROM_UNIXTIME($deadtime)") or sqlerr(__FILE__, __LINE__);
	sql_query("UPDATE snatched SET seeder = 'no' WHERE seeder = 'yes' AND last_action < FROM_UNIXTIME($deadtime)") or sqlerr(__FILE__, __LINE__);
	$hide_time = $deadtime - $max_dead_torrent_time;
sql_query("UPDATE torrents
           SET visible = 'no'
           WHERE visible = 'yes'
             AND last_action < FROM_UNIXTIME($hide_time)
             AND COALESCE(visible_lock,0) = 0") or sqlerr(__FILE__, __LINE__);

// === Обновление статистики раздач ===
$torrents = [];

// Получаем количество сидов и личеров из таблицы peers
$res = sql_query("SELECT torrent, seeder, COUNT(*) AS c FROM peers GROUP BY torrent, seeder") or sqlerr(__FILE__, __LINE__);
while ($row = mysqli_fetch_assoc($res)) {
    $tid = (int)$row['torrent'];
    $k = ($row['seeder'] === 'yes') ? 'seeders' : 'leechers';
    if (!isset($torrents[$tid])) {
        $torrents[$tid] = ['seeders' => 0, 'leechers' => 0, 'comments' => 0];
    }
    $torrents[$tid][$k] = (int)$row['c'];
}

// Получаем количество комментариев по торрентам
$res = sql_query("SELECT torrent, COUNT(*) AS c FROM comments GROUP BY torrent") or sqlerr(__FILE__, __LINE__);
while ($row = mysqli_fetch_assoc($res)) {
    $tid = (int)$row['torrent'];
    if (!isset($torrents[$tid])) {
        $torrents[$tid] = ['seeders' => 0, 'leechers' => 0, 'comments' => 0];
    }
    $torrents[$tid]['comments'] = (int)$row['c'];
}

// Сравниваем с таблицей torrents и обновляем, если нужно
$res = sql_query("SELECT id, seeders, leechers, comments FROM torrents") or sqlerr(__FILE__, __LINE__);
while ($row = mysqli_fetch_assoc($res)) {
    $id = (int)$row['id'];
    $current = [
        'seeders'  => isset($row['seeders']) ? (int)$row['seeders'] : 0,
        'leechers' => isset($row['leechers']) ? (int)$row['leechers'] : 0,
        'comments' => isset($row['comments']) ? (int)$row['comments'] : 0,
    ];
    $new = $torrents[$id] ?? ['seeders' => 0, 'leechers' => 0, 'comments' => 0];

    $upd = [];
    foreach (['seeders', 'leechers', 'comments'] as $k) {
        if ($current[$k] !== $new[$k]) {
            $upd[] = "$k = {$new[$k]}";
        }
    }

    if (!empty($upd)) {
        sql_query("UPDATE torrents SET " . implode(", ", $upd) . " WHERE id = $id") or sqlerr(__FILE__, __LINE__);
    }
}


	// === Удаляем истёкшие баны ===
	sql_query("DELETE FROM bans WHERE until IS NOT NULL AND until > '1000-01-01 00:00:00' AND until < NOW()") or sqlerr(__FILE__, __LINE__);

	// === Удаление старых сообщений ===
	$days = 10 * 86400;
	$dt = sqlesc(get_date_time(gmtime() - $days));
	sql_query("DELETE FROM messages WHERE sender = '' AND unread = 'no' AND added < $dt") or sqlerr(__FILE__, __LINE__);
	sql_query("DELETE FROM messages WHERE unread = 'no' AND added < $dt") or sqlerr(__FILE__, __LINE__);

	// === Удаление неактивных/запаркованных аккаунтов ===
	foreach ([['no', 365], ['yes', 175]] as [$parked, $days]) {
		$dt = sqlesc(get_date_time(gmtime() - $days * 86400));
		$maxclass = UC_POWER_USER;
		$res = sql_query("SELECT id FROM users WHERE parked='$parked' AND status='confirmed' AND class <= $maxclass AND last_access < $dt") or sqlerr(__FILE__, __LINE__);
		while ($arr = mysqli_fetch_assoc($res)) {
			$id = (int)$arr['id'];
			foreach (['users','messages','friends','blocks','invites','peers','simpaty','addedrequests','checkcomm','offervotes'] as $table) {
				sql_query("DELETE FROM $table WHERE userid = $id OR friendid = $id OR blockid = $id OR inviter = $id OR receiver = $id") or sqlerr(__FILE__, __LINE__);
			}
		}
	}

	// === Удаление неподтвержденных после $signup_timeout ===
	$deadtime = time() - $signup_timeout;
	$dt = "FROM_UNIXTIME($deadtime)";
	$res = sql_query("SELECT id FROM users WHERE status = 'pending' AND added < $dt AND last_login < $dt AND last_access < $dt") or sqlerr(__FILE__, __LINE__);
	while ($arr = mysqli_fetch_assoc($res)) {
		sql_query("DELETE FROM users WHERE id = " . (int)$arr['id']) or sqlerr(__FILE__, __LINE__);
	}

	// === Добавление бонуса сидерам (JOIN вместо IN) ===
	sql_query("
		UPDATE users
		JOIN (
			SELECT userid FROM peers WHERE seeder = 'yes' GROUP BY userid
		) AS seeders ON users.id = seeders.userid
		SET users.bonus = users.bonus + $points_per_cleanup
	") or sqlerr(__FILE__, __LINE__);

	// === Снятие просроченных предупреждений ===
	$now = sqlesc(get_date_time());
	$modcomment = sqlesc(date("Y-m-d") . " - Предупреждение снято системой.\n");
	$msg = sqlesc("Ваше предупреждение снято по таймауту.");
	sql_query("INSERT INTO messages (sender, receiver, added, msg, poster) SELECT 0, id, $now, $msg, 0 FROM users WHERE warned = 'yes' AND warneduntil < NOW()") or sqlerr(__FILE__, __LINE__);
	sql_query("UPDATE users SET warned = 'no', warneduntil = NULL, modcomment = CONCAT($modcomment, modcomment) WHERE warned = 'yes' AND warneduntil < NOW()") or sqlerr(__FILE__, __LINE__);

	// === Автоповышение до Power User ===
	$limit = 10 * 1024 * 1024 * 1024;
	$minratio = 0.05;
	$maxdt = sqlesc(get_date_time(gmtime() - 86400 * 20));
	$subject = sqlesc("Вы были повышены");
	$modcomment = sqlesc(date("Y-m-d") . " - Повышен до Power User системой.\n");
	$msg = sqlesc("Поздравляем! Вы автоматически повышены до Power User.");
	$now = sqlesc(get_date_time());
	sql_query("INSERT INTO messages (sender, receiver, added, msg, poster, subject) SELECT 0, id, $now, $msg, 0, $subject FROM users WHERE class = 0 AND uploaded >= $limit AND uploaded / downloaded >= $minratio AND added < $maxdt") or sqlerr(__FILE__, __LINE__);
	sql_query("UPDATE users SET class = " . UC_POWER_USER . ", modcomment = CONCAT($modcomment, modcomment) WHERE class = 0 AND uploaded >= $limit AND uploaded / downloaded >= $minratio AND added < $maxdt") or sqlerr(__FILE__, __LINE__);

	// Очистка сессий
	$dt = time() - 3600;
	sql_query("DELETE FROM sessions WHERE time < $dt") or sqlerr(__FILE__, __LINE__);

	// Очистка старых голосов кармы
	$dt = time() - 86400;
	sql_query("DELETE FROM karma WHERE added < $dt") or sqlerr(__FILE__, __LINE__);

	// Пересчёт тэгов
	sql_query("UPDATE tags AS t SET t.howmuch = (SELECT COUNT(*) FROM torrents AS ts WHERE ts.tags LIKE CONCAT('%', t.name, '%') AND ts.category = t.category)");
	sql_query("DELETE FROM tags WHERE howmuch = 0");
}
