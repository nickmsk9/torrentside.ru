<?php
$rootpath = __DIR__ . '/';

require_once($rootpath . "/include/bittorrent.php");
dbconn();
loggedinorreturn();

if (get_user_class() < UC_SYSOP) {
    stderr("Ошибка", "Доступ разрешён только системным администраторам.");
}

stdhead("Резервное копирование и восстановление БД");
begin_frame("Управление базой данных");

global $mysqli_host, $mysqli_user, $mysqli_pass, $mysqli_db;

$backupDir = $rootpath . "/backup";
if (!is_dir($backupDir)) mkdir($backupDir, 0777, true);

// Дамп базы данных
if (isset($_GET['act']) && $_GET['act'] === "dump") {
    $filename = "dump_" . date("Ymd_His") . ".sql";
    $filepath = $backupDir . "/" . $filename;

    $command = sprintf(
        "mysqldump --user=%s --password=%s --host=%s --default-character-set=utf8mb4 --skip-comments %s > %s",
        escapeshellarg($mysqli_user),
        escapeshellarg($mysqli_pass),
        escapeshellarg($mysqli_host),
        escapeshellarg($mysqli_db),
        escapeshellarg($filepath)
    );

    system($command, $retval);

    if ($retval === 0 && file_exists($filepath)) {
        header("Content-Disposition: attachment; filename=$filename");
        header("Content-Type: application/octet-stream");
        readfile($filepath);
        unlink($filepath); // удалить после загрузки
        exit;
    } else {
        stderr("Ошибка", "Не удалось создать дамп базы данных. Убедитесь, что mysqldump установлен и доступен.");
    }
}

// Загрузка SQL-файла
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_FILES['sqlfile'])) {
    $tmpFile = $_FILES['sqlfile']['tmp_name'];
    $ext = strtolower(pathinfo($_FILES['sqlfile']['name'], PATHINFO_EXTENSION));

    if ($ext !== 'sql') stderr("Ошибка", "Можно загружать только .sql файлы.");

    $sqlContent = file_get_contents($tmpFile);
    $mysqli->multi_query($sqlContent);
    while ($mysqli->more_results()) $mysqli->next_result();

    echo "<div style='color: green; font-weight: bold;'>Файл успешно загружен и восстановлен.</div><br>";
}
?>

<style>
.backup-block {
    background: #f4f9ff;
    border: 1px solid #ccc;
    padding: 15px;
    margin-bottom: 20px;
    border-radius: 8px;
}
input[type="submit"], button {
    background-color: #004E98;
    border: none;
    padding: 10px 18px;
    color: white;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
}
input[type="submit"]:hover, button:hover {
    background-color: #0072cc;
}
</style>

<div class="backup-block">
    <h3>Создание дампа базы данных</h3>
    <p>Скачайте полную резервную копию базы данных в формате .sql</p>
    <form method="get">
        <input type="hidden" name="act" value="dump">
        <button type="submit">📥 Скачать дамп БД</button>
    </form>
</div>

<div class="backup-block">
    <h3>Восстановление из SQL-файла</h3>
    <form method="post" enctype="multipart/form-data">
        <input type="file" name="sqlfile" accept=".sql" required>
        <input type="submit" value="🔄 Загрузить и восстановить">
    </form>
</div>

<?php
end_frame();
stdfoot();
?>
