<?php
ob_start("ob_gzhandler");
require_once "include/bittorrent.php";
dbconn(false);
loggedinorreturn();

stdhead("Хиты закачек");
begin_frame("Хиты закачек");

// CSS стили в стиле 2025 года
echo <<<CSS
<style>
.card-grid {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 25px;
    margin-top: 20px;
}
.card {
    width: 180px;
    background: #fff;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 6px 12px rgba(0,0,0,0.15);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}
.card:hover {
    transform: translateY(-5px);
    box-shadow: 0 12px 20px rgba(0,0,0,0.25);
}
.card img {
    width: 100%;
    height: 250px;
    object-fit: cover;
    display: block;
    border-bottom: 1px solid #eee;
}
.card-footer {
    padding: 10px;
    text-align: center;
    font-size: 14px;
    font-weight: bold;
    color: #c0392b;
}
.card-footer a {
    color: inherit;
    text-decoration: none;
}
</style>
CSS;



// SQL-запрос на топ-20
$res = sql_query("SELECT id, name, image1, times_completed FROM torrents WHERE visible='yes' AND category <> 31 ORDER BY times_completed DESC LIMIT 20");

if (mysqli_num_rows($res) > 0) {
    echo '<div class="card-grid">';

    while ($row = mysqli_fetch_assoc($res)) {
        $id = (int)$row['id'];
        $name = htmlspecialchars($row['name']);
        $image = htmlspecialchars($row['image1']);
        $completed = (int)$row['times_completed'];

        echo <<<HTML
        <div class="card">
            <a href="details.php?id=$id">
                <img class="instant nocorner" src="$image" alt="$name">
            </a>
            <div class="card-footer">
                <a href="details.php?id=$id">Скачан: $completed раз(а)</a>
            </div>
        </div>
        HTML;
    }

    echo '</div>';
} else {
    echo "<p align='center'><b>Нет популярных раздач.</b></p>";
}

end_frame();
stdfoot();
?>
