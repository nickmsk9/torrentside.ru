-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Хост: 127.0.0.1:3306
-- Время создания: Дек 08 2025 г., 12:40
-- Версия сервера: 8.0.30
-- Версия PHP: 8.1.9

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- База данных: `torrent2`
--

-- --------------------------------------------------------

--
-- Структура таблицы `avps`
--

CREATE TABLE `avps` (
  `arg` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value_s` varchar(1024) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `value_i` bigint NOT NULL DEFAULT '0',
  `value_u` bigint UNSIGNED NOT NULL DEFAULT '0',
  `value_u_dt` datetime GENERATED ALWAYS AS (from_unixtime(`value_u`)) VIRTUAL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `avps`
--

INSERT INTO `avps` (`arg`, `value_s`, `value_i`, `value_u`, `updated_at`) VALUES
('lastcleantime', '', 0, 1765185714, '2025-12-08 09:21:53'),
('load_connected', '2025-10-17 14:37:17', 0, 1760701037, '2025-10-17 11:37:17'),
('load_guest', '2025-10-17 14:37:17', 0, 1760701037, '2025-10-17 11:37:17'),
('load_peers', '2025-10-17 14:37:17', 0, 1760701037, '2025-10-17 11:37:17'),
('maxattendance', '', 7, 1240972964, '2025-09-23 06:15:44');

-- --------------------------------------------------------

--
-- Структура таблицы `bans`
--

CREATE TABLE `bans` (
  `id` int UNSIGNED NOT NULL,
  `added` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `addedby` int UNSIGNED NOT NULL DEFAULT '0',
  `comment` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `first` int UNSIGNED DEFAULT NULL,
  `last` int UNSIGNED DEFAULT NULL,
  `until` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `bj`
--

CREATE TABLE `bj` (
  `id` int UNSIGNED NOT NULL,
  `placeholder` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `gamer` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `points` tinyint UNSIGNED NOT NULL DEFAULT '0',
  `plstat` enum('playing','waiting','finished') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'playing',
  `bet` bigint UNSIGNED NOT NULL DEFAULT '0',
  `cards` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `date` bigint UNSIGNED NOT NULL DEFAULT '0',
  `date_dt` datetime GENERATED ALWAYS AS (from_unixtime(`date`)) VIRTUAL,
  `winner` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `gamewithid` int UNSIGNED NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `bj`
--

INSERT INTO `bj` (`id`, `placeholder`, `gamer`, `points`, `plstat`, `bet`, `cards`, `date`, `winner`, `gamewithid`) VALUES
(1, 'admin', '', 10, 'playing', 15, '49', 1752668894, '', 0),
(2, 'admin', '', 10, 'playing', 20, '51', 1752668896, '', 0),
(3, 'admin', '', 8, 'playing', 15, '46', 1752669328, '', 0),
(4, 'admin', '', 8, 'playing', 15, '33', 1752669342, '', 0),
(5, 'admin', '', 17, 'playing', 15, '32 12', 1752669385, '', 0),
(6, 'admin', '', 8, 'playing', 15, '33', 1753707397, '', 0),
(7, 'admin', '', 10, 'playing', 15, '48', 1753773185, '', 0),
(8, 'admin', '', 10, 'playing', 15, '50', 1753773210, '', 0),
(9, 'admin', '', 3, 'playing', 15, '41', 1753773212, '', 0),
(10, 'admin', '', 10, 'playing', 15, '50', 1753773214, '', 0),
(11, 'admin', '', 16, 'playing', 100, '27 21 30', 1753773746, '', 0),
(12, 'admin', '', 20, 'playing', 20, '13 30 41 52 10', 1753773775, '', 0),
(13, '1', '', 22, 'playing', 15, '40 28 45 11', 1753773965, '', 0),
(14, '1', '', 9, 'playing', 15, '34', 1753774113, '', 0),
(15, '1', '', 9, 'playing', 15, '27 32', 1753774251, '', 0),
(16, '1', '', 10, 'playing', 15, '48', 1753774762, '', 0),
(17, 'admin', '', 4, 'playing', 15, '3', 1753789955, '', 0),
(18, 'admin', '', 10, 'playing', 15, '36', 1758532245, '', 0),
(19, 'admin', '777', 20, 'finished', 15, '12 37', 1759488382, 'Никто не выиграл', 20),
(21, '777', 'admin', 20, 'waiting', 70, '36 51', 1759488410, '', 0),
(22, '777', 'admin', 23, 'finished', 15, '41 1 15 2 40 35', 1759738576, 'admin', 15),
(23, 'admin', '', 20, 'waiting', 70, '9 51', 1759738383, '', 21),
(25, 'admin', '', 16, 'waiting', 100, '35 15 41', 1759738592, '', 0),
(26, 'admin', '', 23, 'waiting', 50, '24 26 27 10', 1759838089, '', 0);

-- --------------------------------------------------------

--
-- Структура таблицы `cards`
--

CREATE TABLE `cards` (
  `id` int UNSIGNED NOT NULL,
  `points` tinyint UNSIGNED NOT NULL DEFAULT '0',
  `pic` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `cards`
--

INSERT INTO `cards` (`id`, `points`, `pic`) VALUES
(1, 2, '2p.JPG'),
(2, 3, '3p.JPG'),
(3, 4, '4p.JPG'),
(4, 5, '5p.JPG'),
(5, 6, '6p.JPG'),
(6, 7, '7p.JPG'),
(7, 8, '8p.JPG'),
(8, 9, '9p.JPG'),
(9, 10, '10p.JPG'),
(10, 10, 'vp.JPG'),
(11, 10, 'dp.JPG'),
(12, 10, 'kp.JPG'),
(13, 1, 'tp.JPG'),
(14, 2, '2b.JPG'),
(15, 3, '3b.JPG'),
(16, 4, '4b.JPG'),
(17, 5, '5b.JPG'),
(18, 6, '6b.JPG'),
(19, 7, '7b.JPG'),
(20, 8, '8b.JPG'),
(21, 9, '9b.JPG'),
(22, 10, '10b.JPG'),
(23, 10, 'vb.JPG'),
(24, 10, 'db.JPG'),
(25, 10, 'kb.JPG'),
(26, 1, 'tb.JPG'),
(27, 2, '2k.JPG'),
(28, 3, '3k.JPG'),
(29, 4, '4k.JPG'),
(30, 5, '5k.JPG'),
(31, 6, '6k.JPG'),
(32, 7, '7k.JPG'),
(33, 8, '8k.JPG'),
(34, 9, '9k.JPG'),
(35, 10, '10k.JPG'),
(36, 10, 'vk.JPG'),
(37, 10, 'dk.JPG'),
(38, 10, 'kk.JPG'),
(39, 1, 'tk.JPG'),
(40, 2, '2c.JPG'),
(41, 3, '3c.JPG'),
(42, 4, '4c.JPG'),
(43, 5, '5c.JPG'),
(44, 6, '6c.JPG'),
(45, 7, '7c.JPG'),
(46, 8, '8c.JPG'),
(47, 9, '9c.JPG'),
(48, 10, '10c.JPG'),
(49, 10, 'vc.JPG'),
(50, 10, 'dc.JPG'),
(51, 10, 'kc.JPG'),
(52, 1, 'tc.JPG');

-- --------------------------------------------------------

--
-- Структура таблицы `categories`
--

CREATE TABLE `categories` (
  `id` int UNSIGNED NOT NULL,
  `sort` int NOT NULL DEFAULT '0',
  `name` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `image` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `categories`
--

INSERT INTO `categories` (`id`, `sort`, `name`, `image`) VALUES
(5, 7, 'Игры / PC', 'pc.png'),
(6, 8, 'Игры / PS', 'ps.png'),
(7, 9, 'Игры / X-Box', 'xbox.png'),
(8, 10, 'Игры / PSP', 'psp.png'),
(10, 11, 'Музыка / Альбомы', 'music.png'),
(11, 4, 'Сериалы / XviD and DivX', 'ser.png'),
(13, 1, 'Кино /  XviD and DivX', 'film.png'),
(14, 3, 'Кино /  HDTV and BluRay', 'hd.png'),
(15, 2, 'Кино /  DVD', 'dvd.png'),
(16, 14, 'Разное / Книги', 'books.png'),
(20, 16, 'Разное / Всё вместе', 'all.png'),
(23, 6, 'Сериалы / HDTV and BluRay', 'serhd.png'),
(24, 12, 'Музыка / Клипы', 'mvideo.png'),
(26, 15, 'Разное / Анимэ', 'anime.png'),
(27, 5, 'Сериалы / DVD', 'serdvd.png'),
(28, 13, 'Разное / Софт', 'soft.png'),
(31, 17, 'XXX / Видео', 'xxx.png'),
(32, 18, 'XXX / Игры', 'xxxg.png');

-- --------------------------------------------------------

--
-- Структура таблицы `checkcomm`
--

CREATE TABLE `checkcomm` (
  `id` int UNSIGNED NOT NULL,
  `checkid` int UNSIGNED NOT NULL DEFAULT '0',
  `userid` int UNSIGNED NOT NULL DEFAULT '0',
  `offer` tinyint UNSIGNED NOT NULL DEFAULT '0',
  `torrent` tinyint UNSIGNED NOT NULL DEFAULT '0',
  `req` tinyint UNSIGNED NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `checkcomm`
--

INSERT INTO `checkcomm` (`id`, `checkid`, `userid`, `offer`, `torrent`, `req`) VALUES
(1, 1, 1, 0, 1, 0),
(2, 1, 1, 0, 1, 0),
(3, 1, 1, 0, 1, 0),
(4, 1, 1, 0, 1, 0),
(5, 2, 1, 0, 1, 0);

-- --------------------------------------------------------

--
-- Структура таблицы `comments`
--

CREATE TABLE `comments` (
  `id` int UNSIGNED NOT NULL,
  `user` int UNSIGNED NOT NULL DEFAULT '0',
  `torrent` int UNSIGNED NOT NULL DEFAULT '0',
  `added` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ori_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `editedby` int UNSIGNED NOT NULL DEFAULT '0',
  `editedat` datetime DEFAULT NULL,
  `request` varchar(11) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `offer` varchar(11) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `ip` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `trailer` tinyint UNSIGNED NOT NULL DEFAULT '0',
  `karma` int DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `comments`
--

INSERT INTO `comments` (`id`, `user`, `torrent`, `added`, `text`, `ori_text`, `editedby`, `editedat`, `request`, `offer`, `ip`, `trailer`, `karma`) VALUES
(1, 1, 1, '2025-09-23 10:34:19', 'тестируем', 'тестируем', 0, NULL, '0', '0', '127.0.0.1', 0, 0),
(3, 1, 1, '2025-09-23 10:58:11', 'фаываы выпавы', 'фаываы', 1, '2025-09-30 11:54:02', '0', '0', '127.0.0.1', 0, 0),
(4, 2, 1, '2025-09-29 14:51:57', 'скоро!', 'скоро!', 0, NULL, '0', '0', '127.0.0.1', 0, 2),
(5, 1, 1, '2025-09-30 11:58:08', '[quote=1]скоро![/quote]\nработает ура', '[quote=1]скоро![/quote]\nработает ура', 0, NULL, '0', '0', '127.0.0.1', 0, 0),
(6, 1, 1, '2025-09-30 11:58:22', 'аввафы', 'аввафы', 0, NULL, '0', '0', '127.0.0.1', 0, 0),
(7, 1, 1, '2025-09-30 11:58:23', 'вапва', 'вапва', 0, NULL, '0', '0', '127.0.0.1', 0, 0),
(8, 1, 1, '2025-09-30 11:58:25', 'апврвпрв', 'апврвпрв', 0, NULL, '0', '0', '127.0.0.1', 0, 0),
(9, 1, 1, '2025-09-30 11:58:27', 'аврапр', 'аврапр', 0, NULL, '0', '0', '127.0.0.1', 0, 0),
(10, 1, 1, '2025-09-30 11:58:29', 'аправпр', 'аправпр', 0, NULL, '0', '0', '127.0.0.1', 0, 1),
(11, 1, 1, '2025-09-30 11:58:31', 'авпрапр', 'авпрапр', 0, NULL, '0', '0', '127.0.0.1', 0, 0),
(13, 1, 1, '2025-09-30 13:40:44', '[quote=admin]вапва[/quote]', '[quote=admin]вапва[/quote]', 0, NULL, '0', '0', '127.0.0.1', 0, 0),
(15, 1, 1, '2025-09-30 14:04:47', '[quote=admin][quote=admin]вапва[/quote]\n[/quote]', '[quote=admin][quote=admin]вапва[/quote]\n[/quote]', 0, NULL, '0', '0', '127.0.0.1', 0, 2),
(19, 2, 1, '2025-10-01 12:11:14', 'fgsdfg', 'fgsdfg', 0, NULL, '0', '0', '127.0.0.1', 0, 1),
(20, 2, 1, '2025-10-01 12:21:08', '[quote=1]fgsdfg[/quote]', '[quote=1]fgsdfg[/quote]', 0, NULL, '0', '0', '127.0.0.1', 0, 0),
(21, 2, 1, '2025-10-01 13:51:35', 'http://torrentside.ru/userdetails.php?id=2', 'http://torrentside.ru/userdetails.php?id=2', 0, NULL, '0', '0', '127.0.0.1', 0, 1),
(22, 1, 1, '2025-10-01 16:17:34', 'jвываыва', 'jвываыва', 0, NULL, '0', '0', '127.0.0.1', 0, 2),
(23, 1, 1, '2025-10-02 09:20:17', ':)', ':)', 0, NULL, '0', '0', '127.0.0.1', 0, 2),
(24, 1, 1, '2025-10-02 14:30:24', 'куку', 'куку', 0, NULL, '0', '0', '127.0.0.1', 0, 1),
(25, 2, 1, '2025-10-02 15:34:12', '[color=#ffc6c3]каувыаываыв[/color]', '[color=#ffc6c3]каувыаываыв[/color]', 0, NULL, '0', '0', '127.0.0.1', 0, 1),
(26, 12, 1, '2025-10-02 16:15:40', 'gdfgdsfgd', 'gdfgdsfgd', 0, NULL, '0', '0', '127.0.0.1', 0, 0),
(27, 12, 1, '2025-10-02 16:15:49', '[quote=1][color=#ffc6c3]каувыаываыв[/color][/quote]\n да ну?', '[quote=1][color=#ffc6c3]каувыаываыв[/color][/quote]\n да ну?', 0, NULL, '0', '0', '127.0.0.1', 0, 2),
(28, 1, 1, '2025-10-06 12:48:19', 'dfdsfdsa', 'dfdsfdsa', 0, NULL, '0', '0', '127.0.0.1', 0, 0);

-- --------------------------------------------------------

--
-- Структура таблицы `config`
--

CREATE TABLE `config` (
  `config_id` int UNSIGNED NOT NULL,
  `config` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` bigint UNSIGNED NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `config`
--

INSERT INTO `config` (`config_id`, `config`, `value`) VALUES
(1, 'update_online_time', 300),
(2, 'last_update_online', 1224278298),
(3, 'guests', 1),
(4, 'min_bots', 0),
(5, 'min_guests', 0),
(6, 'max_guests', 0),
(7, 'active_super_loto', 0);

-- --------------------------------------------------------

--
-- Структура таблицы `countries`
--

CREATE TABLE `countries` (
  `id` int UNSIGNED NOT NULL,
  `name` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `flagpic` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `countries`
--

INSERT INTO `countries` (`id`, `name`, `flagpic`) VALUES
(1, 'Швеция', 'Sweden.png'),
(2, 'США', 'United States of America (USA).png'),
(3, 'Россия', 'Russian.png'),
(4, 'Финляндия', 'Finland.png'),
(5, 'Канада', 'Canada.png'),
(6, 'Франция', 'France.png'),
(7, 'Германия', 'Germany.png'),
(8, 'Китай', 'China.png'),
(9, 'Италия', 'Italy.png'),
(10, 'Denmark', 'Denmark.png'),
(11, 'Норвегия', 'Norway.png'),
(12, 'Англия', 'United Kingdom(Great Britain).png'),
(13, 'Ирландия', 'Ireland.png'),
(14, 'Польша', 'Poland.png'),
(15, 'Нидерланды', 'Netherlands.png'),
(16, 'Бельгия', 'Belgium.png'),
(18, 'Бразилия', 'Brazil.png'),
(19, 'Аргентина', 'Argentina.png'),
(20, 'Австралия', 'Australia.png'),
(21, 'Новая Зеландия', 'New Zealand.png'),
(22, 'Испания', 'Spain.png'),
(23, 'Португалия', 'portugal.png'),
(24, 'Мексика', 'Mexico.png'),
(25, 'Сингапур', 'Singapore.png'),
(26, 'Индия', 'India.png'),
(27, 'Албания', 'Albania.png'),
(28, 'Южная Африка', 'South Africa.png'),
(29, 'Южная Корея', 'South Korea.png'),
(31, 'Люксембург', 'Luxembourg.png'),
(32, 'Гонк Конг', 'Hong Kong.png'),
(33, 'Belize', 'Belize.png'),
(34, 'Алжир', 'Algeria.png'),
(35, 'Ангола', 'Angola.png'),
(36, 'Австрия', 'Austria.png'),
(37, 'Югославия', 'Serbia(Yugoslavia).png'),
(38, 'Южные Самоа', 'Samoa.png'),
(39, 'Малайзия', 'Malaysia.png'),
(40, 'Доминиканская Республика', 'Dominican Republic.png'),
(41, 'Греция', 'Greece.png'),
(42, 'Гуатемала', 'Guatemala.png'),
(43, 'Израиль', 'Israel.png'),
(44, 'Пакистан', 'Pakistan.png'),
(45, 'Чехия', 'Czech Republic.png'),
(46, 'Сербия', 'Serbia(Yugoslavia).png'),
(47, 'Сейшельские Острова', 'Seychelles.png'),
(48, 'Тайвань', 'Taiwan.png'),
(49, 'Пуерто Рико', 'Puerto Rico.png'),
(50, 'Чили', 'Chile.png'),
(51, 'Куба', 'Cuba.png'),
(52, 'Кного', 'Congo-Brazzaville.png'),
(53, 'Афганистан', 'Afghanistan.png'),
(54, 'Турция', 'Turkey.png'),
(55, 'Узбекистан', 'Uzbekistan.png'),
(56, 'Швейцария', 'Switzerland.png'),
(57, 'Кирибати', 'Kiribati.png'),
(58, 'Филиппины', 'Philippines.png'),
(59, 'Burkina Faso', 'Burkina Faso.png'),
(60, 'Нигерия', 'Nigeria.png'),
(61, 'Ирландия', 'Iceland.png'),
(62, 'Науру', 'Nauru.png'),
(63, 'Словакия', 'Slovenia.png'),
(64, 'Туркменистан', 'Turkmenistan.png'),
(65, 'Босния', 'Bosnia & Herzegovina.png'),
(66, 'Андора', 'Andorra.png'),
(67, 'Литва', 'Lithuania.png'),
(68, 'Македония', 'Macedonia.png'),
(69, 'Нидерландские Антиллы', 'Netherlands Antilles.png'),
(70, 'Украина', 'Ukraine.png'),
(71, 'Венесуела', 'Venezuela.png'),
(72, 'Венгрия', 'Hungary.png'),
(73, 'Румуния', 'Romania.png'),
(74, 'Вануату', 'Vanutau.png'),
(75, 'Вьетнам', 'Viet Nam.png'),
(76, 'Trinidad ', 'Trinidad & Tobago.png'),
(77, 'Гондурас', 'Honduras.png'),
(78, 'Киргистан', 'Kyrgyzstan.png'),
(79, 'Эквадор', 'Ecuador.png'),
(80, 'Багамы', 'Bahamas.png'),
(81, 'Перу', 'Peru.png'),
(82, 'Камбоджа', 'Cambodja.png'),
(83, 'Барбадос', 'Barbados.png'),
(84, 'Бенгладеш', 'Bangladesh.png'),
(85, 'Лаос', 'Laos.png'),
(86, 'Уругвай', 'Uruguay.png'),
(87, 'Antigua Barbuda', 'Antigua & Barbuda.png'),
(88, 'Парагвая', 'Paraguay.png'),
(89, 'Тайланд', 'Thailand.png'),
(90, 'СССР', 'Russian.png'),
(91, 'Senegal', 'Senegal.png'),
(92, 'Того', 'Togo.png'),
(93, 'Северная Корея', 'North Korea.png'),
(94, 'Хорватия', 'Croatia.png'),
(95, 'Эстония', 'Estonia.png'),
(96, 'Колумбия', 'Colombia.png'),
(97, 'Леванон', 'Lebanon.png'),
(98, 'Латвия', 'Latvia.png'),
(99, 'Коста Рика', 'Costa Rica.png'),
(100, 'Египт', 'Egypt.png'),
(101, 'Болгария', 'Bulgaria.png'),
(102, 'Исла де Муерто', 'Isle.png');

-- --------------------------------------------------------

--
-- Структура таблицы `faq`
--

CREATE TABLE `faq` (
  `id` int UNSIGNED NOT NULL,
  `type` enum('categ','item') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'item',
  `question` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `answer` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `flag` tinyint(1) NOT NULL DEFAULT '1',
  `categ` int UNSIGNED NOT NULL DEFAULT '0',
  `order` int UNSIGNED NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `faq`
--

INSERT INTO `faq` (`id`, `type`, `question`, `answer`, `flag`, `categ`, `order`) VALUES
(1, 'categ', 'О сайте', '', 1, 0, 1),
(2, 'categ', 'Аккаунт и безопасность', '', 1, 0, 2),
(3, 'categ', 'Статистика и рейтинг', '', 1, 0, 3),
(4, 'categ', 'Загрузка (аплоад)', '', 1, 0, 4),
(5, 'categ', 'Скачивание', '', 1, 0, 5),
(6, 'categ', 'Скорость и сеть', '', 1, 0, 6),
(7, 'categ', 'Прокси / VPN', '', 1, 0, 7),
(8, 'categ', 'Доступ и проблемы', '', 1, 0, 8),
(9, 'categ', 'Помощь', '', 1, 0, 9),
(10, 'item', 'Что такое BitTorrent и как тут всё работает?', 'BitTorrent — P2P-протокол обмена файлами. Вы скачиваете части файла и одновременно раздаёте уже полученные части другим пользователям. \nНаш сайт — это трекер и каталог раздач. Трекер помогает участникам найти друг друга и учитывает статистику. \nДля работы нужен торрент-клиент (qBittorrent, Transmission, Deluge, rtorrent/rutorrent, а также µTorrent Classic ≤3.5.x).', 1, 1, 1),
(11, 'item', 'Есть ли зеркало/дополнительные домены?', 'Актуальный домен и статус работ публикуем на главной. Добавьте наш официальный канал/новости в закладки. \nНикому не сообщайте логин/пароль и не вводите их на «похожих» сайтах.', 1, 1, 2),
(12, 'item', 'Забыл логин/пароль — что делать?', 'Воспользуйтесь формой восстановления: <a class=altlink href=\"recover.php\">recover.php</a>. Проверьте папку «Спам». \nЕсли к аккаунту привязан устаревший e-mail — напишите в поддержку через форму на сайте.', 1, 2, 1),
(13, 'item', 'Можно ли переименовать аккаунт или удалить его?', 'Переименование не поддерживается. Удаление — через <a class=altlink href=\"delacct.php\">delacct.php</a> (без возможности восстановления).', 1, 2, 2),
(14, 'item', 'Что такое пасскей и как его защитить?', '<b>Пасскей</b> — токен в announce-URL, по которому трекер связывает сессии с вашим аккаунтом. \nНе публикуйте .torrent со своим пасскеем и не делайте скриншоты с видимым URL. \nЕсли подозреваете утечку — <b>сбросьте пасскей</b> в профиле и перекачайте .torrent.', 1, 2, 3),
(15, 'item', 'Можно ли использовать один аккаунт на нескольких устройствах?', 'Да. Важно авторизоваться на сайте перед стартом новой сессии. Активные сессии с разных IP поддерживаются.', 1, 2, 4),
(16, 'item', 'Что такое рейтинг (ratio) и какой он должен быть?', 'Ratio = отдано/скачано. Нормой считаем ≥1.0. Поддерживайте раздачу после завершения скачивания — этим вы помогаете сообществу.', 1, 3, 1),
(17, 'item', 'Почему статистика иногда обновляется с задержкой?', 'Трекер агрегирует события пакетно. Обычная задержка — до ~30 минут. Не перезапускайте клиент каждые 5 минут — это только ухудшит ситуацию.', 1, 3, 2),
(18, 'item', 'Что такое «ghost peers» в профиле?', 'Если клиент завершился некорректно или сеть оборвалась, трекер может «видеть» старую сессию ещё некоторое время. \nОбычно записи очищаются автоматически в течение 30–60 минут.', 1, 3, 3),
(19, 'item', 'Какие требования к релизам?', 'Проверьте правила раздела и шаблоны описаний. Скриншоты, технические данные (кодеки, битрейт), корректные категории/теги — обязательно. \nПовторы и фейки удаляются.', 1, 4, 1),
(20, 'item', 'Можно ли раздавать наши торренты на других трекерах?', 'Нет, перепубликация .torrent с нашим announce запрещена. Используйте собственный контент по своему усмотрению, \nно .torrent с нашим пасскеем не распространяйте.', 1, 4, 2),
(21, 'item', 'Почему торрент исчез из списка?', 'Возможные причины: нарушение правил, плохой релиз (заменён аплоадером), истечение TTL в архивных разделах. \nСледите за новостями и разделом правил.', 1, 5, 1),
(22, 'item', 'Как возобновить раздачу, если торрент пропал из клиента?', 'Откройте исходный .torrent, укажите путь к уже имеющимся файлам — клиент проверит хэш и продолжит раздачу.', 1, 5, 2),
(23, 'item', 'Почему иногда загрузка «застывает» на 99%?', 'Редкие части могут отсутствовать у подключённых пиров. Оставьте сессию включённой — появление сидера/комплита доприведёт файл. \nПроверьте диск на ошибки и не используйте «сомнительные» клиенты.', 1, 5, 3),
(24, 'item', 'Медленная загрузка. Что сделать в первую очередь?', '<ul>\n<li>Откройте входящие соединения на роутере: <b>форвард портов клиента</b> на ваш ПК.</li>\n<li>Выберите нестандартный диапазон, предпочтительно <b>49152–65535</b>.</li>\n<li>Ограничьте аплоад ~до 70–85% от максимумa линии, чтобы не «душить» ACK.</li>\n<li>Не запускайте десятки торрентов одновременно — лучше меньше, но быстрее.</li>\n</ul>', 1, 6, 1),
(25, 'item', 'Какие BT-клиенты рекомендуете?', '<b>Windows:</b> qBittorrent, µTorrent Classic ≤3.5.x (без «версий Web»), Deluge. \n<b>macOS:</b> qBittorrent, Transmission. <b>Linux:</b> qBittorrent-nox, rtorrent/rutorrent, Deluge. \nИзбегайте старых/модифицированных клиентов с чит-функциями — бан.', 1, 6, 2),
(26, 'item', 'Нужно ли отключать DHT/PEX/LSD?', 'Если раздача приватная (private flag) — клиент сам отключит DHT/PEX/LSD для данного торрента. \nМы рекомендуем глобально <b>включить</b> их, но уважайте настройки раздач: для приватных торрентов механизмы будут отключены автоматически.', 1, 6, 3),
(27, 'item', 'Порты по умолчанию заблокированы?', 'Многие провайдеры режут «стандартные» p2p-порты. Используйте динамический диапазон 49152–65535 и зафиксируйте его в клиенте и в NAT-правиле.', 1, 6, 4),
(28, 'item', 'Почему веб-сайты «тормозят», когда я качаю?', 'Загрузка/раздача используют канал. Ограничьте скорость в клиенте, включите QoS/Smart Queue в роутере, \nлибо используйте менеджеры полосы наподобие NetLimiter.', 1, 6, 5),
(29, 'item', 'Можно ли использовать VPN/прокси?', 'VPN разрешён при условии стабильного входящего порта. Бесплатные/шаред-VPN часто дают «закрытые» порты — скорость будет ниже. \nРегистрироваться через анонимные прокси запрещено.', 1, 7, 1),
(30, 'item', 'Почему трекер определяет меня как «закрытый порт», хотя NAT отключён?', 'Если вы за корпоративным/операторским прокси и он не передаёт X-Forwarded-For, трекер может видеть IP прокси. \nПроверьте, что в клиенте открыт и проброшен один и тот же порт, и используйте VPN с выделенным портом при необходимости.', 1, 7, 2),
(31, 'item', 'Не могу войти на сайт', 'Очистите cookie сайта, проверьте время/часовой пояс системы и включён ли JavaScript. \nЕсли используете расширения-блокировщики — добавьте сайт в исключения.', 1, 8, 1),
(32, 'item', '«Unknown passkey» / «connection limit» — что делать?', 'Если только что сменили пасскей — перекачайте .torrent. При «подвисших» сессиях дождитесь автоочистки (30–60 мин) \nили смените пасскей и запустите раздачи заново.', 1, 8, 2),
(33, 'item', 'Торрент «запрещён до N часов»', 'Новички и пользователи с низким ratio могут получать задержку на новые релизы. \nЭто касается и скачивания, и сидирования полученных с других источников файлов. Дождитесь истечения таймера.', 1, 8, 3),
(34, 'item', 'Где задать вопрос, если не нашёл ответа?', 'Задайте вопрос на <a class=altlink href=\"forums.php\">форуме</a>. \nОпишите клиента/ОС, тип подключения, логи клиента, скриншоты настроек порта и вашего роутера. Вежливость ускоряет ответы :)', 1, 9, 1),
(35, 'item', 'Полезные ссылки', '<ul>\n<li>Переадресация портов: руководство к вашему роутеру.</li>\n<li>qBittorrent: официальная документация.</li>\n<li>Transmission/rtorrent: вики-страницы проектов.</li>\n</ul>', 1, 9, 2);

-- --------------------------------------------------------

--
-- Структура таблицы `files`
--

CREATE TABLE `files` (
  `id` int UNSIGNED NOT NULL,
  `torrent` int UNSIGNED NOT NULL DEFAULT '0',
  `filename` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `size` bigint UNSIGNED NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `forums`
--

CREATE TABLE `forums` (
  `sort` tinyint UNSIGNED NOT NULL DEFAULT '0',
  `id` int UNSIGNED NOT NULL,
  `name` varchar(60) NOT NULL,
  `description` varchar(200) NOT NULL DEFAULT '',
  `f_com` text,
  `minclassread` tinyint UNSIGNED NOT NULL DEFAULT '0',
  `minclasswrite` tinyint UNSIGNED NOT NULL DEFAULT '0',
  `postcount` int UNSIGNED NOT NULL DEFAULT '0',
  `topiccount` int UNSIGNED NOT NULL DEFAULT '0',
  `minclasscreate` tinyint UNSIGNED NOT NULL DEFAULT '0',
  `visible` enum('yes','no') NOT NULL DEFAULT 'yes'
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `forums`
--

INSERT INTO `forums` (`sort`, `id`, `name`, `description`, `f_com`, `minclassread`, `minclasswrite`, `postcount`, `topiccount`, `minclasscreate`, `visible`) VALUES
(1, 1, 'тестовая', 'тестовая', NULL, 6, 6, 0, 0, 6, 'yes');

-- --------------------------------------------------------

--
-- Структура таблицы `friends`
--

CREATE TABLE `friends` (
  `id` int UNSIGNED NOT NULL,
  `userid` int UNSIGNED NOT NULL DEFAULT '0',
  `friendid` int UNSIGNED NOT NULL DEFAULT '0',
  `status` enum('yes','no','pending') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `friends`
--

INSERT INTO `friends` (`id`, `userid`, `friendid`, `status`) VALUES
(10, 1, 2, 'yes'),
(11, 2, 1, 'yes');

-- --------------------------------------------------------

--
-- Структура таблицы `futurerls`
--

CREATE TABLE `futurerls` (
  `id` int UNSIGNED NOT NULL,
  `userid` int UNSIGNED NOT NULL DEFAULT '0',
  `comments` int UNSIGNED NOT NULL DEFAULT '0',
  `trailer` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `added` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `name` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `realeasedate` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `descr` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `cat` int UNSIGNED NOT NULL,
  `download` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `groups`
--

CREATE TABLE `groups` (
  `id` int UNSIGNED NOT NULL,
  `name` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `groups`
--

INSERT INTO `groups` (`id`, `name`) VALUES
(1, 'TorrentSide Rippers');

-- --------------------------------------------------------

--
-- Структура таблицы `hackers`
--

CREATE TABLE `hackers` (
  `id` int UNSIGNED NOT NULL,
  `added` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ip` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `system` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `event` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `invites`
--

CREATE TABLE `invites` (
  `id` int UNSIGNED NOT NULL,
  `inviter` int UNSIGNED NOT NULL DEFAULT '0',
  `inviteid` int UNSIGNED NOT NULL DEFAULT '0',
  `invite` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `time_invited` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `confirmed` enum('no','yes') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'no'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `invites`
--

INSERT INTO `invites` (`id`, `inviter`, `inviteid`, `invite`, `time_invited`, `confirmed`) VALUES
(1, 1, 0, '12889dc35c913302a2173bc70ce77185', '2025-09-24 13:13:42', 'no');

-- --------------------------------------------------------

--
-- Структура таблицы `karma`
--

CREATE TABLE `karma` (
  `id` int UNSIGNED NOT NULL,
  `type` enum('torrent','comment','user') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'torrent',
  `user` int UNSIGNED NOT NULL,
  `value` int UNSIGNED NOT NULL,
  `added` int UNSIGNED NOT NULL DEFAULT '0',
  `voted_on` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `messages`
--

CREATE TABLE `messages` (
  `id` int UNSIGNED NOT NULL,
  `sender` int UNSIGNED NOT NULL DEFAULT '0',
  `receiver` int UNSIGNED NOT NULL DEFAULT '0',
  `added` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `subject` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'No team',
  `msg` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `unread` enum('yes','no') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'yes',
  `poster` int UNSIGNED NOT NULL DEFAULT '0',
  `location` tinyint(1) NOT NULL DEFAULT '1',
  `saved` enum('no','yes') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'no'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `messages`
--

INSERT INTO `messages` (`id`, `sender`, `receiver`, `added`, `subject`, `msg`, `unread`, `poster`, `location`, `saved`) VALUES
(1, 1, 4, '2025-09-23 11:56:56', 'выпаап', 'авпвыап', 'yes', 0, 1, 'no'),
(3, 0, 4, '2025-09-23 11:57:10', 'Попытка входа под Вашим аккаунтом!', 'Только что была произведена неудачная попытка входа под Вашим аккаунтом с IP 127.0.0.1, [1]. Если это не Вы, рекомендуем немедленно сменить пароль и сообщить администрации.', 'yes', 0, 1, 'no'),
(4, 0, 4, '2025-09-23 11:57:49', 'Попытка входа под Вашим аккаунтом!', 'Только что была произведена неудачная попытка входа под Вашим аккаунтом с IP 127.0.0.1, [123456]. Если это не Вы, рекомендуем немедленно сменить пароль и сообщить администрации.', 'yes', 0, 1, 'no'),
(5, 0, 4, '2025-09-23 12:04:49', 'Вы были повышены', 'Вы были повышены до класса \"Администратор\" пользователем admin.', 'yes', 0, 1, 'no'),
(6, 0, 4, '2025-09-23 12:06:49', 'Вы были повышены', 'Вы были повышены до класса \"Администратор\" пользователем admin.', 'yes', 0, 1, 'no'),
(7, 0, 4, '2025-09-23 12:06:49', 'No team', 'Без ограниченный рейтинг вам включил admin. Качайте на здоровье.', 'yes', 0, 1, 'no'),
(8, 0, 4, '2025-09-26 10:57:17', 'Вы были понижены', 'Вы были понижены до класса \"Модератор\" пользователем admin.', 'yes', 0, 1, 'no'),
(9, 0, 4, '2025-09-26 10:57:17', 'Вы получили предупреждение', 'Вы получили [url=rules.php#warning]предупреждение[/url] на 1 неделю от пользователя admin\n\nПричина: наказан', 'yes', 0, 1, 'no'),
(10, 1, 4, '2025-09-29 12:40:40', 'ghfdhf', 'ghfdghfd', 'yes', 1, 1, 'yes'),
(11, 1, 4, '2025-09-29 13:43:22', 'sdfgdsfg', 'gdfgsdf', 'yes', 1, 1, 'no'),
(12, 1, 4, '2025-09-29 13:45:32', 'sdfgdsfg', 'gdfgsdf', 'yes', 1, 1, 'no'),
(13, 1, 4, '2025-09-29 13:51:29', 'ыфвафыв', 'выаыфва', 'yes', 1, 1, 'no'),
(29, 0, 4, '2025-10-01 10:08:44', 'Изменение списка друзей', 'Пользователь [url=userdetails.php?id=1]admin[/url] удалил вас из списка друзей.', 'yes', 0, 1, 'no'),
(35, 2, 4, '2025-10-01 12:00:03', 'ываыв', 'ываы', 'yes', 0, 1, 'no'),
(36, 0, 4, '2025-10-01 12:07:41', 'No team', 'Без ограниченный рейтинг вам отключил 1. Скорей всего это случилось из-за нарушения правил.', 'yes', 0, 1, 'no'),
(37, 0, 4, '2025-10-01 12:07:55', 'No team', 'Без ограниченный рейтинг вам включил 1. Качайте на здоровье.', 'yes', 0, 1, 'no'),
(38, 1, 4, '2025-10-01 16:15:19', '32131', 'конь', 'yes', 0, 1, 'no'),
(39, 0, 4, '2025-10-02 14:57:05', 'Вы были понижены', 'Вы были понижены до класса \"VIP\" пользователем 1.', 'yes', 0, 1, 'no'),
(40, 0, 4, '2025-10-02 14:57:05', 'No team', 'Без ограниченный рейтинг вам отключил 1. Скорей всего это случилось из-за нарушения правил.', 'yes', 0, 1, 'no'),
(44, 0, 4, '2025-10-03 11:12:19', 'No team', 'Ваше предупреждение снято по таймауту.', 'yes', 0, 1, 'no'),
(49, 0, 4, '2025-10-06 13:07:04', 'Вы получили предупреждение', 'Вы получили [url=rules.php#warning]предупреждение[/url] на 1 неделю от пользователя admin\n\nПричина: проверяем', 'yes', 0, 1, 'no'),
(50, 0, 17, '2025-10-10 09:51:43', 'Вы были повышены', 'Вы были повышены до класса \"Продвинутый\" пользователем admin.', 'yes', 0, 1, 'no'),
(51, 0, 4, '2025-10-16 12:56:52', 'No team', 'Ваше предупреждение снято по таймауту.', 'yes', 0, 1, 'no');

-- --------------------------------------------------------

--
-- Структура таблицы `mybonus`
--

CREATE TABLE `mybonus` (
  `id` int UNSIGNED NOT NULL,
  `bonus_position` smallint UNSIGNED NOT NULL DEFAULT '0',
  `bonus_title` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `bonus_description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `bonus_points` decimal(10,1) NOT NULL DEFAULT '0.0',
  `bonus_art` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'traffic',
  `bonus_menge` bigint UNSIGNED NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `mybonus`
--

INSERT INTO `mybonus` (`id`, `bonus_position`, `bonus_title`, `bonus_description`, `bonus_points`, `bonus_art`, `bonus_menge`) VALUES
(1, 1, '1GB Траффика', 'Обменять бонусы на траффик.', '1000.0', 'traffic', 1073741824),
(2, 2, '2.5GB Траффика', 'Обменять бонусы на траффик.', '2400.0', 'traffic', 2684354560),
(3, 3, '5GB Траффика', 'Обменять бонусы на траффик.', '4700.0', 'traffic', 5368709120),
(4, 4, '3 Приглашения', 'Обменять бонусы на Приглашения.', '250.0', 'invite', 3),
(5, 4, '10GB Траффика', 'Обменять бонусы на траффик.', '7200.0', 'traffic', 10737418240),
(6, 4, '20GB Траффика', 'Обменять бонусы на траффик.', '9999.9', 'traffic', 21474836480);

-- --------------------------------------------------------

--
-- Структура таблицы `news`
--

CREATE TABLE `news` (
  `id` int UNSIGNED NOT NULL,
  `userid` int UNSIGNED NOT NULL DEFAULT '0',
  `added` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `body` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `news`
--

INSERT INTO `news` (`id`, `userid`, `added`, `body`, `subject`) VALUES
(1, 1, '2025-07-17 12:41:21', 'Тестируем, друзья!', 'Тестирум новость?');

-- --------------------------------------------------------

--
-- Структура таблицы `notconnectablepmlog`
--

CREATE TABLE `notconnectablepmlog` (
  `id` int UNSIGNED NOT NULL,
  `user` int UNSIGNED NOT NULL DEFAULT '0',
  `date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `notconnectablepmlog`
--

INSERT INTO `notconnectablepmlog` (`id`, `user`, `date`) VALUES
(1, 1, '2025-07-17 10:49:14');

-- --------------------------------------------------------

--
-- Структура таблицы `pages`
--

CREATE TABLE `pages` (
  `id` int UNSIGNED NOT NULL,
  `img` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `date` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `country` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `img1` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `img2` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `img3` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `img4` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `pages`
--

INSERT INTO `pages` (`id`, `img`, `name`, `content`, `date`, `country`, `img1`, `img2`, `img3`, `img4`) VALUES
(1, 'https://kinopoisk-ru.clstorage.net/14q9Ql203/279975roPzH/-PWG3tXlFIzAX8EhG11wPoj4bzsPHPaqV8G8_UHq08WUR4bn-euu7IEJgbE3Mkf1NmADluLutQ-uOHt0P7BtkKhigxkG9YHIwEgFZ01dJ7jPLPkvT07kqQJFr29UOdSBQxCRLDXhombdgA57PzlfpKZwBcH1OGUebO8-9nohfIwz8xYJWexYR0ETBHJU0TEgBbhs8kQUaGoVBmIwhi5MilgKSWV6oW-oWYaJ_L3_0SVgNN6IsvLi3qQusr3saL1JdSdcTZFkFguK2Ay43N34LEr7Kf_MFqetH02lPULviw7VhcmlcGwq5BGLhvcyOpEk4bOSwf6ybd8jrfm05678jvanX08fa1ABCFrAO9RXf6uKuWB2CFvmqx9N6XLIbwQMX0cFozev6yRYhsxy9r2WZaN51cl1_qXSK2W29qnnOgY9K1iJXiPdDYvVRPubHzEsAPPpuQ0TLCdcjGR5AyMBDpXAz2e26CHgUUkJsfPzVuxq_FJCuXcvHyuj-Haq5zGNfKfZxlerHgVKmkO3ENb2rsY2ojkPGehiHwUieouqS0eXzIDutGquK9tPhnX8_hxlLvmWzr367patpvx_KCnzgTIglYiWKllEg5EOsxYe_uzPsSCwDphjo98OqXdKKsgPHonAIjGq5i4Wg4g3eLZe7ah1X0AxP6If7iRzuacvt8h-JRCEHG0ZR0zewf9bnnSpA7UpeweapCDbSCu5yuxIQt0OwWs7o6xs1kiAfTu61OKgOR3APDstlikvM_ghLTjIsmfQBh4l3sQC38H6GJo1KYg9qrNNHGarl4nv9EItgQQfhYhp-q-ub1PCS_11tRVhpbYWCPpwLl7kbfu-oW05h7WlWIZQIRmHxdsD8xTY-OXNsS-xhhxvq9NJ4bRFJkvL1AiGL7uoLu8Qhkdy9fGWbWm9ksc7sySWqWA2-iqjskB_ot7HGSMex8hWTTdS1XDmDTGpP4Of52BQjyewiaOND9TMRGp-4yEi0coM9HN5lmjit5tK9T3iHOWrMzGjZD1CtqBdzVzjGcJAWM52FFg6aQ32rvcNEeGqlgbuP8IvhoaVBI5gfiknbNNCiXEz8NAtrj8fg_-6Y1Pibnv46iaxgHRtVcscYVVHhB1I8xZePK4CeqB-DpcmYFLA6T6LY8wEnEGOJjxnq62RyQo7PzYXJeC7V0u8eu4br-k2eiNluMV2bRRMn6zah8wQw3EZUvdnzXSodkcXrGtUSeXxRWZDhR6GRqr2aaRt0weIubvzlW2lvx-FfzBo2u-ocnasbnmPv2KTRFnlVkpGGkk4G5H8JcL05PlPW2ghFYrld0OjzAlbiA6utKxi51uNTP27OpjkKXwVjzo6ZZstIfu25S54jf9mkMtRoZmCgl-Ifp6dNaBFM-mwRBCoIptNozBF7AbBGgeLbzYtICXZCEZ0sHoQaSm3VgY2saOZL6fx9SkksMm4Kl7LVuyez8GQRHsZ2L9uC_1gsYKfJaSeBmS2ja0FhhTIh2d772xvnMcIv_K5Uahhud8Dv7prm-mnt3ChYLEHPKDTTFQh140Nms45k5V4KA81LzhG0q5j3QWvfwYuxMPbT4lofCrjbFHPD7J9f15h4rWUCb865VNr5Hjwbyl3QXWr3EbRIhCAAl5JttVRsuiCsiz2Qtdm7B-H739ErIoFlI5DL3aj4WMawARztXgWqmM42k_xPmSQZ2d6-GWk-Ys-JV1BFe2QB4PXhD-YkbllTHlk9owUoyEUxuqwDeKNi9KODuK_KmOuk4NAeb560ePuuFRI9fAlmCWhu75voX3CMmuZjpGt2YXNFkmzlhkwLsf1aDxIFGdsEI9j8sqhxgJWzYto9qRrbNrPzPKzsJynLbNUDbH4Lxsv6_k_paB5gnRmVAdZol5LwNcHNpYYveFGeGF7D1ys6p3BITRK64tEFUCBrjdi4CwXz0oz_f5RoC7z2Ml0fi2ebmZ_fGrjd4i3aFeG2CoQCEzQxL9RkHUkhnHmMAJZ6euazCZ3TW4JRNPOyy5yqO9iUU-DPfI2keJqsVTNuvssnuvnc3jlo3oOtmkdSR9pEEhOUMu5mdI3LMV5p3MAFqxpFMZtPkDjAU2Qj0boOalpqBlHR_T8v1gjLz_Si3Y3Z9mkbbcyoe0_R7SqF0QZY9SEylgAPBBZueQDc-3xhtthJ1jBrTEAYwUDFo9ILb9qZGLZzsl4MPbQ5-33FQV7-68aK-D68q8gPAc45RnLVmxSiwyRzL7RnDQgx3Dv809apuyczeQwyWHIBtNEQWL6o2MunAfEvLf3WakmeZ-Dszci22_vMTAlLbTOeqFbTxPgUcRGUMz8FNXxLc7x5_AP0yYgk86r902vjgbXDo9mOGgo5d5Px3k1cpVl7nDSDjQwqNipY3D1IWx6g7RvEQ7XKRaFgVfBexSZNe4HOyF7gpvgKR7BrLcILoUK14eE6blqoS7Riom8fHjU7OpwXMJ8sq8YYmw5OOTkOEi975GOlWUYwADWBPjbHzFuxfWq8I1TZyrdziN8zGdGxBMKAys_o6LkGYlPe7xyGKCjMJbANH9tH-5hs7mqK3KBOq_XTBFgXojBGIn_3lhw6IR94jJOGGzqlYRt-cxtxsqfQcFgP-wkLJvFTzN8flhiazfYSPSxYlFqZndxoGmzCLKrmUwdK5jKA5oGuxuYP-GAvax7TZCspNcGKnABLg6BXk4FYv_ko2tSyweyM7cXrGi_nMKxMaxTZ-50dCPmsQI1o1EE0KXciwmWTzBT2jqoCD1lMQbebyqcD2N2xaUDwRJHzKA57aQo0QcGuH51n-fucRrD-XOv3uFpub4i5PdJdu1dzd4lEEhI3Qy62Ra_r4q077bFUG3lVIfr-A7iS8KdyI2hdKWu4haHxLU_cFqopTkcTXa17p8ur_S1ZyN9Bv6hGYCQoVQLCVCOtxZR-msGNWixSl4vap5NKTKI7cyDloZEqzfhI-dTCEB3e__XLGr1UEo8u6LZKw', 'Бред Питт', 'Уильям Брэдли Питт родился 18 декабря 1963 года в городе Шони (штат Оклахома, США), вырос в очень религиозной американской семье. Его отец, Уильям Питт, работал менеджером в компании, занимавшейся грузоперевозками, мать, Джейн Этта Хиллхаус (1940—2025) — психологом-консультантом в местной школе. Он, его брат Даг Питт и сестра Джулия Питт росли в Спрингфилде (штат Миссури), куда семья переехала вскоре после его рождения. В школе Питт занимался спортом, состоял в дебатном клубе, музыкальной секции и участвовал в студенческом самоуправлении. После школы Уильям поступил в университет Миссури — Колумбия, где изучал журналистику и рекламное дело. Однако после окончания университета по профессии работать он не пошёл, а отправился в Голливуд с целью начать актёрскую карьеру. Там он сменил своё имя на «Брэд Питт.\r\n\r\nВ его честь был назван открытый в 2015 году вид ос Conobregma bradpitti.', '09.03.1995', '2', 'https://i2.imageban.ru/out/2025/10/06/4c7569e444656a738a031e7b18412069.png', 'https://i2.imageban.ru/out/2025/10/06/4c7569e444656a738a031e7b18412069.png', 'https://i2.imageban.ru/out/2025/10/06/4c7569e444656a738a031e7b18412069.png', 'https://i2.imageban.ru/out/2025/10/06/4c7569e444656a738a031e7b18412069.png');

-- --------------------------------------------------------

--
-- Структура таблицы `peers`
--

CREATE TABLE `peers` (
  `id` int UNSIGNED NOT NULL,
  `torrent` int UNSIGNED NOT NULL DEFAULT '0',
  `peer_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `ip` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `port` smallint UNSIGNED NOT NULL DEFAULT '0',
  `uploaded` bigint UNSIGNED NOT NULL DEFAULT '0',
  `downloaded` bigint UNSIGNED NOT NULL DEFAULT '0',
  `uploadoffset` bigint UNSIGNED NOT NULL DEFAULT '0',
  `downloadoffset` bigint UNSIGNED NOT NULL DEFAULT '0',
  `to_go` bigint UNSIGNED NOT NULL DEFAULT '0',
  `seeder` enum('yes','no') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'no',
  `started` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_action` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `prev_action` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `connectable` enum('yes','no') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'yes',
  `userid` int UNSIGNED NOT NULL DEFAULT '0',
  `agent` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `finishedat` int UNSIGNED NOT NULL DEFAULT '0',
  `passkey` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `pollanswers`
--

CREATE TABLE `pollanswers` (
  `id` int UNSIGNED NOT NULL,
  `pollid` int UNSIGNED NOT NULL DEFAULT '0',
  `userid` int UNSIGNED NOT NULL DEFAULT '0',
  `selection` tinyint UNSIGNED NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `pollanswers`
--

INSERT INTO `pollanswers` (`id`, `pollid`, `userid`, `selection`) VALUES
(1, 26, 1, 0),
(2, 26, 2, 2),
(3, 1, 1, 0),
(4, 1, 2, 2),
(5, 1, 12, 2),
(6, 1, 17, 0);

-- --------------------------------------------------------

--
-- Структура таблицы `polls`
--

CREATE TABLE `polls` (
  `id` int UNSIGNED NOT NULL,
  `added` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `question` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `option0` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `option1` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `option2` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `option3` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `option4` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `option5` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `option6` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `option7` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `option8` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `option9` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `option10` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `option11` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `option12` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `option13` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `option14` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `option15` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `option16` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `option17` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `option18` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `option19` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `sort` enum('yes','no') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'yes'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `polls`
--

INSERT INTO `polls` (`id`, `added`, `question`, `option0`, `option1`, `option2`, `option3`, `option4`, `option5`, `option6`, `option7`, `option8`, `option9`, `option10`, `option11`, `option12`, `option13`, `option14`, `option15`, `option16`, `option17`, `option18`, `option19`, `sort`) VALUES
(1, '2025-07-16 15:06:11', 'опрос?', 'да', 'нет', 'ещёё', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 'yes');

-- --------------------------------------------------------

--
-- Структура таблицы `posts`
--

CREATE TABLE `posts` (
  `id` int UNSIGNED NOT NULL,
  `topicid` int UNSIGNED NOT NULL,
  `forumid` int UNSIGNED NOT NULL,
  `userid` int UNSIGNED NOT NULL,
  `added` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'время добавления',
  `body` text COLLATE utf8mb4_unicode_ci,
  `body_orig` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `editedby` int UNSIGNED NOT NULL DEFAULT '0',
  `editedat` datetime DEFAULT NULL COMMENT 'время редактирования'
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `posts`
--

INSERT INTO `posts` (`id`, `topicid`, `forumid`, `userid`, `added`, `body`, `body_orig`, `editedby`, `editedat`) VALUES
(1, 3, 1, 1, '2025-10-22 13:45:39', 'фывфывфыв', 'фывфывфыв', 0, NULL),
(2, 4, 1, 1, '2025-10-22 13:46:59', 'фывфывфыв', 'фывфывфыв', 0, NULL);

-- --------------------------------------------------------

--
-- Структура таблицы `rangclass`
--

CREATE TABLE `rangclass` (
  `id` int UNSIGNED NOT NULL,
  `name` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rangpic` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `rangclass`
--

INSERT INTO `rangclass` (`id`, `name`, `rangpic`) VALUES
(1, '10 загруженных торрентов.', 'rang1.gif'),
(2, '30 загруженных торрентов.', 'rang2.gif'),
(3, '80 загруженных торрентов.', 'rang3.gif'),
(4, '100 загруженных торрентов.', 'rang4.png'),
(5, '250 загруженных торрентов.', 'rang5.png'),
(6, '500 загруженных торрентов.', 'rang6.png'),
(7, '1000 загруженных торрентов.', 'pop_release.gif'),
(8, 'Более 1000 загруженных торрентов.', 'best_release.gif');

-- --------------------------------------------------------

--
-- Структура таблицы `ratings`
--

CREATE TABLE `ratings` (
  `id` int UNSIGNED NOT NULL,
  `torrent` int UNSIGNED NOT NULL DEFAULT '0',
  `user` int UNSIGNED NOT NULL DEFAULT '0',
  `rating` tinyint UNSIGNED NOT NULL DEFAULT '0',
  `added` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `ratings`
--

INSERT INTO `ratings` (`id`, `torrent`, `user`, `rating`, `added`) VALUES
(40, 1, 2, 5, '2025-09-30 12:34:18'),
(44, 1, 12, 1, '2025-10-03 13:24:34');

-- --------------------------------------------------------

--
-- Структура таблицы `readposts`
--

CREATE TABLE `readposts` (
  `id` int UNSIGNED NOT NULL,
  `userid` int UNSIGNED NOT NULL,
  `topicid` int UNSIGNED NOT NULL,
  `lastpostread` int UNSIGNED NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Дамп данных таблицы `readposts`
--

INSERT INTO `readposts` (`id`, `userid`, `topicid`, `lastpostread`) VALUES
(1, 1, 4, 2);

-- --------------------------------------------------------

--
-- Структура таблицы `sendbonus`
--

CREATE TABLE `sendbonus` (
  `id` int UNSIGNED NOT NULL,
  `owner` int UNSIGNED NOT NULL,
  `receiver` int UNSIGNED NOT NULL,
  `amount` bigint UNSIGNED NOT NULL,
  `msg` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `added` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `sessions`
--

CREATE TABLE `sessions` (
  `sid` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `uid` int UNSIGNED NOT NULL DEFAULT '0',
  `username` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `class` tinyint NOT NULL DEFAULT '0',
  `ip` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `time` bigint NOT NULL DEFAULT '0',
  `time_dt` datetime GENERATED ALWAYS AS (from_unixtime(`time`)) VIRTUAL,
  `url` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `useragent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `sessions`
--

INSERT INTO `sessions` (`sid`, `uid`, `username`, `class`, `ip`, `time`, `url`, `useragent`) VALUES
('ca2074cd17b081538d7cca0af08a6782ebaa3119768048f14e171f9fe9cf39c7', 1, 'admin', 6, '127.0.0.1', 1765185712, '/', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 YaBrowser/25.10.0.0 Safari/537.36');

-- --------------------------------------------------------

--
-- Структура таблицы `shoutbox`
--

CREATE TABLE `shoutbox` (
  `id` int UNSIGNED NOT NULL,
  `userid` int UNSIGNED NOT NULL DEFAULT '0',
  `class` int NOT NULL DEFAULT '0',
  `username` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `date` bigint NOT NULL DEFAULT '0',
  `date_dt` datetime GENERATED ALWAYS AS (from_unixtime(`date`)) VIRTUAL,
  `text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `orig_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `warned` enum('yes','no') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'no',
  `donor` enum('yes','no') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'no'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `shoutbox`
--

INSERT INTO `shoutbox` (`id`, `userid`, `class`, `username`, `date`, `text`, `orig_text`, `warned`, `donor`) VALUES
(1, 1, 6, 'admin', 1759230869, ':whistle:', ':whistle:', 'no', 'no'),
(2, 2, 5, '1', 1759390733, ':whistle:', ':whistle:', 'no', 'no'),
(3, 2, 5, '1', 1759396297, ':blink:', ':blink:', 'no', 'no'),
(4, 1, 6, 'admin', 1759397182, ':spidey:', ':spidey:', 'no', 'no'),
(5, 1, 6, 'admin', 1759401114, ':yes:', ':yes:', 'no', 'no'),
(6, 2, 5, '1', 1759408652, 'впаы', 'впаы', 'no', 'no'),
(7, 12, 3, '777', 1759410532, 'privat(admin)  :whistle:', 'privat(admin)  :whistle:', 'no', 'no'),
(8, 1, 6, 'admin', 1759480321, ':ike:', ':ike:', 'no', 'no'),
(9, 1, 6, 'admin', 1759481075, ':ike:', ':ike:', 'no', 'no'),
(10, 1, 6, 'admin', 1759736318, ':evilmad:', ':evilmad:', 'no', 'no'),
(11, 2, 5, '1', 1759916480, ':yes:', ':yes:', 'no', 'no');

-- --------------------------------------------------------

--
-- Структура таблицы `sitelog`
--

CREATE TABLE `sitelog` (
  `id` int UNSIGNED NOT NULL,
  `added` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `color` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'transparent',
  `txt` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `type` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'tracker'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `sitelog`
--

INSERT INTO `sitelog` (`id`, `added`, `color`, `txt`, `type`) VALUES
(17, '2025-10-06 11:00:09', '5DDB6E', 'Торрент №2 (Матрица) залит пользователем admin', 'torrent'),
(18, '2025-10-06 11:04:03', 'F25B61', 'Торрент \'Матрица\' был отредактирован пользователем admin', 'torrent'),
(19, '2025-10-06 11:10:30', 'F25B61', 'Торрент \'Матрица\' был отредактирован пользователем admin', 'torrent'),
(20, '2025-10-06 11:11:30', 'F25B61', 'Торрент \'Матрица / Matrix 2025\' был отредактирован пользователем admin', 'torrent'),
(21, '2025-10-06 11:25:05', 'FFFFFF', 'Зарегистрирован новый пользователь demon23', 'tracker'),
(22, '2025-10-07 11:26:02', 'F25B61', 'Торрент \'Матрица / Matrix 2025\' был отредактирован пользователем admin', 'torrent'),
(23, '2025-10-10 10:45:31', 'F25B61', 'Торрент \'Торнадо / Tornado 2025 PC\' был отредактирован пользователем admin', 'torrent');

-- --------------------------------------------------------

--
-- Структура таблицы `snatched`
--

CREATE TABLE `snatched` (
  `id` int UNSIGNED NOT NULL,
  `userid` int UNSIGNED NOT NULL DEFAULT '0',
  `torrent` int UNSIGNED NOT NULL DEFAULT '0',
  `port` smallint UNSIGNED NOT NULL DEFAULT '0',
  `uploaded` bigint UNSIGNED NOT NULL DEFAULT '0',
  `downloaded` bigint UNSIGNED NOT NULL DEFAULT '0',
  `to_go` bigint UNSIGNED NOT NULL DEFAULT '0',
  `seeder` enum('yes','no') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'no',
  `last_action` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `startdat` datetime DEFAULT NULL,
  `completedat` datetime DEFAULT NULL,
  `connectable` enum('yes','no') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'yes',
  `finished` enum('yes','no') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'no'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `stylesheets`
--

CREATE TABLE `stylesheets` (
  `id` int UNSIGNED NOT NULL,
  `uri` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `name` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `stylesheets`
--

INSERT INTO `stylesheets` (`id`, `uri`, `name`) VALUES
(1, 'Light', 'Floxide');

-- --------------------------------------------------------

--
-- Структура таблицы `super_loto_tickets`
--

CREATE TABLE `super_loto_tickets` (
  `ticket_id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `combination` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `price` int NOT NULL,
  `date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `game_date` date DEFAULT NULL,
  `active` tinyint NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `super_loto_tickets`
--

INSERT INTO `super_loto_tickets` (`ticket_id`, `user_id`, `combination`, `price`, `date`, `game_date`, `active`) VALUES
(1, 2, '22.10.34.27.26', 1, '2025-10-01 14:11:58', NULL, 1),
(2, 1, '9.28.11.14.32', 1, '2025-10-02 14:46:31', NULL, 1),
(3, 1, '17.27.20.9.22', 5, '2025-10-02 14:46:37', NULL, 1),
(4, 1, '21.3.20.17.29', 1, '2025-10-02 11:53:31', NULL, 1),
(5, 1, '5.8.21.27.25', 1, '2025-10-02 11:53:37', NULL, 1),
(6, 2, '2.14.11.29.10', 1, '2025-10-02 12:04:04', NULL, 1),
(7, 2, '9.28.32.34.35', 1, '2025-10-02 15:07:14', '2025-10-02', 1),
(8, 1, '36.35.34.33.32', 2, '2025-10-02 15:07:45', '2025-10-02', 1),
(9, 1, '4.9.27.23.30', 10, '2025-10-02 15:16:16', '2025-10-03', 1),
(10, 1, '14.26.29.17.8', 1, '2025-10-02 15:16:24', '2025-10-03', 1),
(11, 12, '17.33.9.22.11', 1, '2025-10-02 16:12:05', '2025-10-03', 1),
(12, 1, '16.27.19.14.32', 1, '2025-10-03 10:52:13', '2025-10-03', 1);

-- --------------------------------------------------------

--
-- Структура таблицы `super_loto_winners`
--

CREATE TABLE `super_loto_winners` (
  `winner_id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `price` int NOT NULL,
  `combination` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `win_num` tinyint NOT NULL,
  `numbers` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `jackpot` tinyint NOT NULL,
  `win_combination` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `date` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `super_loto_winners`
--

INSERT INTO `super_loto_winners` (`winner_id`, `user_id`, `price`, `combination`, `win_num`, `numbers`, `jackpot`, `win_combination`, `date`) VALUES
(1, 2, 1, '9.28.32.34.35', 1, '28', 0, '36.17.28.11.7', '2025-10-02'),
(2, 1, 2, '36.35.34.33.32', 1, '36', 0, '36.17.28.11.7', '2025-10-02'),
(3, 1, 1, '14.26.29.17.8', 1, '26', 0, '26.15.33.5.6', '2025-10-03'),
(4, 12, 1, '17.33.9.22.11', 1, '33', 0, '26.15.33.5.6', '2025-10-03');

-- --------------------------------------------------------

--
-- Структура таблицы `tags`
--

CREATE TABLE `tags` (
  `id` int UNSIGNED NOT NULL,
  `category` int NOT NULL DEFAULT '0',
  `name` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `howmuch` int NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `tags`
--

INSERT INTO `tags` (`id`, `category`, `name`, `howmuch`) VALUES
(1, 5, 'игра', 1),
(2, 5, 'проверка', 1),
(3, 5, 'сидеры', 1),
(4, 5, 'тест', 1),
(5, 5, 'работаем', 1),
(6, 11, 'игра', 1),
(7, 11, 'проверка', 1),
(8, 11, 'сидеры', 1),
(9, 11, 'тест', 1),
(10, 11, 'работаем', 1);

-- --------------------------------------------------------

--
-- Структура таблицы `topics`
--

CREATE TABLE `topics` (
  `id` int UNSIGNED NOT NULL,
  `userid` int UNSIGNED NOT NULL DEFAULT '0',
  `subject` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `t_com` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `locked` enum('yes','no') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'no',
  `forumid` int UNSIGNED NOT NULL DEFAULT '0',
  `lastdate` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'время последней активности',
  `lastpost` int UNSIGNED NOT NULL DEFAULT '0',
  `sticky` enum('yes','no') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'no',
  `visible` enum('yes','no') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'yes',
  `views` int UNSIGNED NOT NULL DEFAULT '0',
  `polls` enum('yes','no') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'no'
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `topics`
--

INSERT INTO `topics` (`id`, `userid`, `subject`, `t_com`, `locked`, `forumid`, `lastdate`, `lastpost`, `sticky`, `visible`, `views`, `polls`) VALUES
(1, 1, 'rtyertыфваф', '', 'no', 1, '2025-10-22 13:41:24', 0, 'no', 'yes', 0, 'no'),
(2, 1, 'ывфыфв', '', 'no', 1, '2025-10-22 13:44:43', 0, 'no', 'yes', 0, 'no'),
(3, 1, 'ывфыфв', '', 'no', 1, '2025-10-22 13:45:39', 1, 'no', 'yes', 0, 'no'),
(4, 1, 'ывфыфв', '', 'no', 1, '2025-10-22 13:46:59', 2, 'no', 'yes', 6, 'no');

-- --------------------------------------------------------

--
-- Структура таблицы `torrents`
--

CREATE TABLE `torrents` (
  `id` int UNSIGNED NOT NULL,
  `info_hash` varbinary(40) NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `filename` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `save_as` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `search_text` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `descr` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ori_descr` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `image1` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `image2` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `image3` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `image4` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `image5` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `category` int UNSIGNED NOT NULL DEFAULT '0',
  `size` bigint UNSIGNED NOT NULL DEFAULT '0',
  `added` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `type` enum('single','multi') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'single',
  `numfiles` int UNSIGNED NOT NULL DEFAULT '0',
  `comments` int UNSIGNED NOT NULL DEFAULT '0',
  `views` int UNSIGNED NOT NULL DEFAULT '0',
  `hits` int UNSIGNED NOT NULL DEFAULT '0',
  `times_completed` int UNSIGNED NOT NULL DEFAULT '0',
  `leechers` int UNSIGNED NOT NULL DEFAULT '0',
  `seeders` int UNSIGNED NOT NULL DEFAULT '0',
  `last_action` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_reseed` datetime DEFAULT NULL,
  `visible` enum('yes','no') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'yes',
  `visible_lock` tinyint(1) NOT NULL DEFAULT '0',
  `banned` enum('yes','no') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'no',
  `owner` int UNSIGNED NOT NULL DEFAULT '0',
  `numratings` int UNSIGNED NOT NULL DEFAULT '0',
  `ratingsum` int UNSIGNED NOT NULL DEFAULT '0',
  `sticky` enum('yes','no') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'no',
  `poster` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `moderated` enum('yes','no') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'no',
  `moderatedby` int UNSIGNED NOT NULL DEFAULT '0',
  `free` tinyint NOT NULL DEFAULT '0',
  `ratio` int UNSIGNED NOT NULL DEFAULT '0',
  `karma` int DEFAULT '0',
  `modded` enum('yes','no') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'no',
  `modby` int UNSIGNED NOT NULL DEFAULT '0',
  `modname` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `modtime` datetime DEFAULT NULL,
  `tags` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `torrents`
--

INSERT INTO `torrents` (`id`, `info_hash`, `name`, `filename`, `save_as`, `search_text`, `descr`, `ori_descr`, `image1`, `image2`, `image3`, `image4`, `image5`, `category`, `size`, `added`, `type`, `numfiles`, `comments`, `views`, `hits`, `times_completed`, `leechers`, `seeders`, `last_action`, `last_reseed`, `visible`, `visible_lock`, `banned`, `owner`, `numratings`, `ratingsum`, `sticky`, `poster`, `moderated`, `moderatedby`, `free`, `ratio`, `karma`, `modded`, `modby`, `modname`, `modtime`, `tags`) VALUES
(1, 0x32393832656263366139646663393863656634363132363764613638343136393334663235326631, 'Торнадо / Tornado 2025 PC', '[kinozal.tv]id2108182.torrent', 'Tornado 2025 BDRip.1080p.HEVC.10bit.DUB.mikos.mkv', '[kinozal.tv]id2108182 Tornado 2025 BDRip.1080p.HEVC.10bit.DUB.mikos.mkv Торнадо / Tornado 2025 PC', '[b]Название:[/b] Хэллоуин. Ночной кошмар\r\n[b]Оригинальное название[/b]: Night of the Reaper\r\n[b]Год выпуска:[/b] 2025\r\n[b]Жанр[/b]: Ужасы\r\n[b]Выпущено:[/b] США, Not the Funeral Home, Superchill\r\n[b]Режиссер[/b]: Брэндон Кристенсен\r\n[b]В ролях:[/b] Джессика Клемент, Райан Роббинс, Саммер Х. Хауэлл, Киган Коннор Трэйси, Мэтти Финочио, Макс Кристенсен, Бен Коккелл, Дэвид Фихэн, Брин Сэмюэл, Саванна Миллер, Бред Питт\r\n\r\n[b][u]О фильме:[/u][/b] Ровно через год после того, как была убита её сестра, Ди возвращается в родной город. Она соглашается посидеть с сыном местного шерифа в его большом загородном доме. Во время игры в прятки Ди находит там комнату расследования, где на доске сделана попытка найти взаимосвязь между загадочными убийствами. Девушка понимает, что дело её сестры еще не закончено.\r\n\r\nКачество: WEB-DL (1080p)\r\nВидео: MPEG-4 AVC, 8575 Кбит/с, 1920x800\r\nАудио: Русский (AC3, 2 ch, 192 Кбит/с), английский (E-AC3, 6 ch, 640 Кбит/с)\r\nРазмер: 6.14 ГБ\r\nПродолжительность: 01:33:24\r\nПеревод: Любительский многоголосый\r\nСубтитры: Английские\r\n\r\n', '[b]Название:[/b] Хэллоуин. Ночной кошмар\r\n[b]Оригинальное название[/b]: Night of the Reaper\r\n[b]Год выпуска:[/b] 2025\r\n[b]Жанр[/b]: Ужасы\r\n[b]Выпущено:[/b] США, Not the Funeral Home, Superchill\r\n[b]Режиссер[/b]: Брэндон Кристенсен\r\n[b]В ролях:[/b] Джессика Клемент, Райан Роббинс, Саммер Х. Хауэлл, Киган Коннор Трэйси, Мэтти Финочио, Макс Кристенсен, Бен Коккелл, Дэвид Фихэн, Брин Сэмюэл, Саванна Миллер, Бред Питт\r\n\r\n[b][u]О фильме:[/u][/b] Ровно через год после того, как была убита её сестра, Ди возвращается в родной город. Она соглашается посидеть с сыном местного шерифа в его большом загородном доме. Во время игры в прятки Ди находит там комнату расследования, где на доске сделана попытка найти взаимосвязь между загадочными убийствами. Девушка понимает, что дело её сестры еще не закончено.\r\n\r\nКачество: WEB-DL (1080p)\r\nВидео: MPEG-4 AVC, 8575 Кбит/с, 1920x800\r\nАудио: Русский (AC3, 2 ch, 192 Кбит/с), английский (E-AC3, 6 ch, 640 Кбит/с)\r\nРазмер: 6.14 ГБ\r\nПродолжительность: 01:33:24\r\nПеревод: Любительский многоголосый\r\nСубтитры: Английские\r\n\r\n', 'https://i8.imageban.ru/out/2025/06/10/5821076e3d472c61903f0531035f6dce.jpg', 'https://i126.fastpic.org/thumb/2025/1009/4a/_19d098a2a003c65ea417da8176e7024a.jpeg', 'https://i126.fastpic.org/thumb/2025/1009/4a/_19d098a2a003c65ea417da8176e7024a.jpeg', 'https://i126.fastpic.org/thumb/2025/1009/4a/_19d098a2a003c65ea417da8176e7024a.jpeg', 'https://i126.fastpic.org/thumb/2025/1009/4a/_19d098a2a003c65ea417da8176e7024a.jpeg', 5, 8243720321, '2025-09-23 10:09:49', 'single', 0, 22, 0, 7, 0, 0, 0, '2025-09-23 10:09:49', NULL, 'yes', 1, 'no', 1, 2, 6, 'yes', '1', 'yes', 1, 80, 0, 4, 'yes', 2, '1', '2025-09-30 12:34:08', 'игра,проверка,сидеры,тест,работаем'),
(2, 0x66396462646161646231376662343131623531363439353265396632646239343234333166396661, 'Матрица / Matrix 2025', 'УД-25-35783_5 согласование от Метрополитен.pdf.torrent', 'УД-25-35783_5 согласование от Метрополитен.pdf', 'УД-25-35783_5 согласование от Метрополитен.pdf УД-25-35783_5 согласование от Метрополитен.pdf Матрица / Matrix 2025', '[b]Название:[/b] Матрица\r\n[b]Оригинальное название:[/b] Matrix\r\n[b]Год выхода:[/b] 2025\r\n[b]Жанр:[/b] fds\r\n[b]Режиссер:[/b] sdf\r\n[b]В ролях:[/b] Бред Питт, Демсон Идрис, Керри Кондон, Хавьер Бардем, Тобайас Мензис, Ким Бодния, Сара Найлз, Уилл Меррик, Пепе Балдеррама, Абдул Сэлис, Калли Кук, Самсон Каё, Саймон Кунц, Лиз Кингсман, Симон Эшли\r\n[b]Перевод:[/b] Профессиональный (Дублированный)\r\n[b]О фильме:[/b]\r\nоапорпаоапроапроапропароап\r\n\r\n[b]Продолжительность:[/b] 2\r\n[b]Издатель:[/b] 23\r\n\r\n[b]Качество:[/b] DVD5\r\n[b]Формат:[/b] AVI\r\n[b]Видео:[/b] 546, 456, 45\r\n[b]Аудио:[/b] 456, 4\r\n\r\n', '[b]Название:[/b] Матрица\r\n[b]Оригинальное название:[/b] Matrix\r\n[b]Год выхода:[/b] 2025\r\n[b]Жанр:[/b] fds\r\n[b]Режиссер:[/b] sdf\r\n[b]В ролях:[/b] Бред Питт, Демсон Идрис, Керри Кондон, Хавьер Бардем, Тобайас Мензис, Ким Бодния, Сара Найлз, Уилл Меррик, Пепе Балдеррама, Абдул Сэлис, Калли Кук, Самсон Каё, Саймон Кунц, Лиз Кингсман, Симон Эшли\r\n[b]Перевод:[/b] Профессиональный (Дублированный)\r\n[b]О фильме:[/b]\r\nоапорпаоапроапроапропароап\r\n\r\n[b]Продолжительность:[/b] 2\r\n[b]Издатель:[/b] 23\r\n\r\n[b]Качество:[/b] DVD5\r\n[b]Формат:[/b] AVI\r\n[b]Видео:[/b] 546, 456, 45\r\n[b]Аудио:[/b] 456, 4\r\n\r\n', 'https://i126.fastpic.org/big/2025/1005/b6/f144c3ded0dd34a998aca431c9af20b6.jpeg', '', '', '', '', 11, 17938467, '2025-10-06 11:00:09', 'single', 0, 0, 0, 2, 0, 0, 0, '2025-10-06 11:00:09', NULL, 'yes', 1, 'no', 1, 0, 0, 'no', '1', 'yes', 1, 50, 0, 0, 'no', 0, 'admin', NULL, 'игра,проверка,сидеры,тест,работаем');

-- --------------------------------------------------------

--
-- Структура таблицы `users`
--

CREATE TABLE `users` (
  `id` int UNSIGNED NOT NULL,
  `username` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `old_password` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `passhash` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `secret` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `email` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `status` enum('pending','confirmed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `added` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_login` datetime DEFAULT NULL,
  `editsecret` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `privacy` enum('strong','normal','low') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'normal',
  `stylesheet` int DEFAULT '1',
  `info` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `acceptpms` enum('yes','friends','no') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'yes',
  `ip` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `class` tinyint UNSIGNED NOT NULL DEFAULT '0',
  `override_class` tinyint UNSIGNED NOT NULL DEFAULT '255',
  `support` enum('no','yes') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'no',
  `supportfor` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `avatar` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'https://torrentside.ru/pic/default_avatar.gif',
  `telegram` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `skype` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `website` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `uploaded` bigint UNSIGNED NOT NULL DEFAULT '0',
  `downloaded` bigint UNSIGNED NOT NULL DEFAULT '0',
  `title` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `country` int UNSIGNED NOT NULL DEFAULT '0',
  `tzoffset` varchar(6) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `notifs` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `modcomment` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `enabled` enum('yes','no') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'yes',
  `parked` enum('yes','no') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'no',
  `avatars` enum('yes','no') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'yes',
  `donor` enum('yes','no') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'no',
  `simpaty` int UNSIGNED NOT NULL DEFAULT '0',
  `warned` enum('yes','no') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'no',
  `warneduntil` datetime DEFAULT NULL,
  `torrentsperpage` int UNSIGNED NOT NULL DEFAULT '0',
  `topicsperpage` int UNSIGNED NOT NULL DEFAULT '0',
  `postsperpage` int UNSIGNED NOT NULL DEFAULT '0',
  `deletepms` enum('yes','no') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'yes',
  `savepms` enum('yes','no') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'no',
  `gender` enum('1','2','3') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '1',
  `birthday` date DEFAULT NULL,
  `passkey` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `language` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'russian',
  `invites` int NOT NULL DEFAULT '0',
  `invitedby` int NOT NULL DEFAULT '0',
  `invitedroot` int NOT NULL DEFAULT '0',
  `passkey_ip` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `page` tinyint UNSIGNED NOT NULL DEFAULT '0',
  `hiderating` enum('yes','no') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'no',
  `bjwins` int NOT NULL DEFAULT '0',
  `bjlosses` int NOT NULL DEFAULT '0',
  `hidebid` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `last_access` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_post` datetime DEFAULT NULL,
  `forum_access` datetime DEFAULT NULL,
  `forum_com` datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
  `schoutboxpos` enum('yes','no') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'yes',
  `bot_pos` enum('yes','no') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'yes',
  `rangclass` int UNSIGNED NOT NULL DEFAULT '0',
  `bonus` decimal(10,2) NOT NULL DEFAULT '100.00',
  `groups` enum('yes','no') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'no',
  `karma` int DEFAULT '0',
  `moderated` int NOT NULL DEFAULT '0',
  `pss` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `signature` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `signatrue` enum('yes','no') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'yes'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `users`
--

INSERT INTO `users` (`id`, `username`, `old_password`, `passhash`, `secret`, `email`, `status`, `added`, `last_login`, `editsecret`, `privacy`, `stylesheet`, `info`, `acceptpms`, `ip`, `class`, `override_class`, `support`, `supportfor`, `avatar`, `telegram`, `skype`, `website`, `uploaded`, `downloaded`, `title`, `country`, `tzoffset`, `notifs`, `modcomment`, `enabled`, `parked`, `avatars`, `donor`, `simpaty`, `warned`, `warneduntil`, `torrentsperpage`, `topicsperpage`, `postsperpage`, `deletepms`, `savepms`, `gender`, `birthday`, `passkey`, `language`, `invites`, `invitedby`, `invitedroot`, `passkey_ip`, `page`, `hiderating`, `bjwins`, `bjlosses`, `hidebid`, `last_access`, `last_post`, `forum_access`, `forum_com`, `schoutboxpos`, `bot_pos`, `rangclass`, `bonus`, `groups`, `karma`, `moderated`, `pss`, `signature`, `signatrue`) VALUES
(1, 'admin', '', '5039eac9baa615e3cc29d7b0ba612ef7', 'CWAJn2TGUk8j6HYUZQHH', 'nickmsk9@icloud.com', 'confirmed', '2025-07-15 15:55:38', '2025-10-22 10:24:34', '', 'normal', 1, '[b]Привет как дела?[/b]\r\n[url=http://torrentside.ru][img]http://torrentside.ru/torrentbar/bar.php?id=1[/img][/url]', 'yes', '127.0.0.1', 6, 255, 'no', NULL, 'pic/admin.gif', 'nswbt', '', 'http://torrentside.ru', 82678120448, 0, 'давайте укажим статус для проверки', 20, '-12', '', '2025-10-10 - Обменял 1000 бонусов на traffic.\n2025-10-10 - Обменял 1000 бонусов на traffic.\n2025-10-10 - Обменял 1000 бонусов на traffic.\n2025-09-24 - Обменял 250 бонусов на invite.\n2025-09-24 - Обменял 1000 бонусов на traffic.\n2025-09-24 - Обменял 1000 бонусов на traffic.\n2025-09-24 - Обменял 7200 бонусов на traffic.\n2025-09-24 - Обменял 7200 бонусов на traffic.\n2025-09-24 - Обменял 7200 бонусов на traffic.\n2025-09-24 - Обменял 7200 бонусов на traffic.\n2025-09-24 - Обменял 7200 бонусов на traffic.\n2025-09-24 - Обменял 7200 бонусов на traffic.\n2025-09-24 - Обменял 7200 бонусов на traffic.\n2025-09-24 - Обменял 7200 бонусов на traffic.\n2025-09-24 - Обменял 7200 бонусов на traffic.\n2025-09-24 - Обменял 1000 бонусов на traffic.\n', 'yes', 'no', 'no', 'no', 0, 'no', NULL, 0, 0, 0, 'no', 'no', '1', '1965-03-16', '824a93849ed6a25272bd722e6f9889f2', 'russian', 2, 0, 0, '', 1, 'no', 0, 0, '0', '2025-12-08 12:21:52', NULL, '2025-12-05 09:59:20', '1970-01-01 00:00:00', 'yes', 'yes', 0, '16630.00', 'no', 2, 0, '123456', '', 'yes'),
(2, '1', '', '26d25d64eb55efdd0521793a2e702081', 'snSc9Y1czpJltB3ZYTt8', '123@mail.ru', 'confirmed', '2025-07-16 13:13:24', '2025-10-16 13:14:33', 'ZLLn9zgGsNpCYCSlQcIO', 'normal', 1, 'привет\r\nsdfasdfas\r\n\r\n\r\n\r\n[url=http://torrentside.ru][img]http://torrentside.ru/torrentbar/bar.php?id=2[/img][/url]', 'yes', '127.0.0.1', 5, 255, 'no', 'всего', 'https://images.animelayer.ru/DqS5ivpoCLDvPd2EZ6umMQ/100x100/1/crop/10/8d/56c1b840e1cf68fb0e8b4e4c_56c1b841c53d7.gif', '1934812', '', '', 1319628701696, 12582912, 'проверяеюаываываыв', 8, '-12', '', '2025-09-30 - Повышен до класса \"Администратор\" пользователем admin.\n2025-09-30 - Пользователь admin добавил 12 MB к скачаному.\n2025-09-30 - Пользователь admin добавил 1231 GB к раздаче.\n', 'yes', 'no', 'no', 'no', 0, 'no', NULL, 0, 0, 0, 'yes', 'no', '1', NULL, '2df81ce120b78abd97ed505ddbfe2fe8', 'russian', 0, 0, 0, '', 1, 'no', 0, 0, '0', '2025-10-22 10:21:41', NULL, '2025-10-22 10:24:19', '1970-01-01 00:00:00', 'yes', 'yes', 1, '5.00', 'no', 2, 1, '', '', 'yes'),
(4, 'tester', '', '99ddb91fd4a83efdc4fe33d9b591dab2', 'iI7YiimIMQD1DaP41Mca', '22@mail.ru', 'confirmed', '2025-07-16 13:20:16', '2025-09-23 11:57:49', '', 'normal', 1, NULL, 'yes', '127.0.0.1', 2, 255, 'yes', '', 'https://images.animelayer.ru/hSRWTwisUOvYOGDPeoqdpw/100x100/1/crop/ef/61/56c1b5d3e1cf68fb0e8b46b1_56c1b5d3c9ec1.gif', '', '', '', 132202314596352, 138412032, '', 0, '0', '', '2025-10-16 - Предупреждение снято системой.\n2025-10-06 - Предупрежден на 1 неделю пользователем admin.\nПричина: проверяем\n2025-10-03 - Предупреждение снято системой.\r\n2025-10-02 - Без ограниченный рейтинг отключил 1.\r\n2025-10-02 - Понижен до класса &quot;VIP&quot; пользователем 1.\r\n2025-10-01 - Без ограниченный рейтинг включил 1.\r\n2025-10-01 - Без ограниченный рейтинг отключил 1.\r\n2025-09-26 - Заметка от admin: тестик\r\n2025-09-26 - Предупрежден на 1 неделю пользователем admin.\r\nПричина: наказан\r\n2025-09-26 - Понижен до класса &quot;Модератор&quot; пользователем admin.\r\n2025-09-23 - Без ограниченный рейтинг включил admin.\r\n2025-09-23 - Повышен до класса &amp;quot;Администратор&amp;quot; пользователем admin.\r\n2025-09-23 - Пользователь admin добавил 132 MB к скачаному.\r\n2025-09-23 - Пользователь admin добавил 123123 GB к раздаче.\r\n', 'yes', 'no', 'yes', 'yes', 0, 'no', NULL, 0, 0, 0, 'yes', 'no', '1', NULL, NULL, 'russian', 0, 0, 0, '', 0, 'no', 0, 0, '0', '2025-07-16 13:20:16', NULL, '2025-07-16 13:20:16', '1970-01-01 00:00:00', 'yes', 'yes', 6, '1035.00', 'no', 1, 0, '', '', 'yes'),
(12, '777', '', '02e40f16169f2407746fa469c7838c35', 'jmFCKMke6BlVt77hPSXJ', '777123@mail.ru', 'confirmed', '2025-10-02 15:50:05', '2025-10-06 11:36:40', '', 'normal', 1, '', 'yes', '127.0.0.1', 3, 255, 'yes', 'всего', 'https://torrentside.ru/pic/default_avatar.gif', 'durov', '', '', 2455764992, 24117248, '', 8, '0', '', '2025-10-02 - Пользователь admin добавил 23 MB к скачаному.\r\n2025-10-02 - Пользователь admin добавил 2342 MB к раздаче.\r\n2025-10-02 - Повышен до класса &amp;quot;Аплоадер&amp;quot; пользователем admin.\r\n', 'yes', 'no', 'no', 'no', 0, 'no', NULL, 0, 0, 0, 'yes', 'no', '1', NULL, 'edabf11de463ce7eaea3aeb2e5174bd0', 'russian', 0, 0, 0, '127.0.0.1', 1, 'no', 0, 0, '0', '2025-10-06 11:47:18', NULL, '2025-10-06 11:47:18', '1970-01-01 00:00:00', 'yes', 'yes', 0, '15.00', 'no', 0, 0, '', '', 'yes'),
(13, '777_vki', '', '76b9517b05488f8f68442ba0e68370d6', 'cdzDlH9o6BedjFuCYIaT', '1777723@mail.ru', 'confirmed', '2025-10-02 15:53:24', NULL, '', 'normal', 1, NULL, 'yes', '127.0.0.1', 0, 255, 'no', NULL, 'https://torrentside.ru/pic/default_avatar.gif', '', '', '', 0, 0, '', 0, '0', '', NULL, 'yes', 'no', 'yes', 'no', 0, 'no', NULL, 0, 0, 0, 'yes', 'no', '1', NULL, 'f5ff2a545679ad7d3b92c7bcef82b56c', 'russian', 0, 0, 0, '127.0.0.1', 0, 'no', 0, 0, '0', '2025-10-02 15:53:24', NULL, '2025-10-02 15:53:24', '1970-01-01 00:00:00', 'yes', 'yes', 0, '100.00', 'no', 0, 0, '', '', 'yes'),
(14, '777_f9w', '', '0c49675fc42d0b9b320718b17ab4de71', 'MLsNkZPWbHydFF3mMjLm', '124234sfa@mail.ru', 'confirmed', '2025-10-02 15:55:50', NULL, '', 'normal', 1, NULL, 'yes', '127.0.0.1', 0, 255, 'no', NULL, 'https://torrentside.ru/pic/default_avatar.gif', '', '', '', 0, 0, '', 0, '0', '', NULL, 'yes', 'no', 'yes', 'no', 0, 'no', NULL, 0, 0, 0, 'yes', 'no', '1', NULL, '1b1e68607c5a2b7b28b51a6b8a51c115', 'russian', 0, 0, 0, '127.0.0.1', 0, 'no', 0, 0, '0', '2025-10-02 15:55:50', NULL, '2025-10-02 15:55:50', '1970-01-01 00:00:00', 'yes', 'yes', 0, '100.00', 'no', 0, 0, '', '', 'yes'),
(15, '777_jio', '', '73270674bfcd17b1de753112d147246c', 'kCmMUGUC4JBtX5qkMNZZ', '124234sfa+5rc@mail.ru', 'confirmed', '2025-10-02 15:58:09', NULL, '', 'normal', 1, NULL, 'yes', '127.0.0.1', 0, 255, 'no', NULL, 'https://torrentside.ru/pic/default_avatar.gif', '', '', '', 0, 0, '', 0, '0', '', NULL, 'yes', 'no', 'yes', 'no', 0, 'no', NULL, 0, 0, 0, 'yes', 'no', '1', NULL, '85577d2176d3e45adf724bf651baf61e', 'russian', 0, 0, 0, '127.0.0.1', 0, 'no', 0, 0, '0', '2025-10-02 15:58:09', NULL, '2025-10-02 15:58:09', '1970-01-01 00:00:00', 'yes', 'yes', 0, '100.00', 'no', 0, 0, '', '', 'yes'),
(17, 'demon23', '', 'db3ff5c3044e2c3b26f30d889c0b4d7c', 'gNfgx9pRjQDnwDOmXb6D', 'demon@mail.ru', 'confirmed', '2025-10-06 11:25:05', '2025-10-06 11:25:05', 'DLM2WrSwPz5WHBgkIJSL', 'normal', 1, NULL, 'yes', '127.0.0.1', 1, 255, 'no', '', 'https://torrentside.ru/pic/default_avatar.gif', '234232', '', '', 0, 0, '', 80, '0', '', '2025-10-10 - Повышен до класса \"Продвинутый\" пользователем admin.\n', 'yes', 'no', 'yes', 'no', 0, 'no', NULL, 0, 0, 0, 'yes', 'no', '1', '1955-02-18', 'c5380ee727ccdb027e6d57e175bae20b', 'russian', 0, 0, 0, '', 1, 'no', 0, 0, '0', '2025-10-06 11:25:05', NULL, '2025-10-06 11:25:05', '1970-01-01 00:00:00', 'yes', 'yes', 0, '100.00', 'no', 0, 0, '', '', 'yes');

-- --------------------------------------------------------

--
-- Структура таблицы `visitor_history`
--

CREATE TABLE `visitor_history` (
  `id` int UNSIGNED NOT NULL,
  `url` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `uid` int UNSIGNED NOT NULL,
  `uname` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `url_hash` binary(16) GENERATED ALWAYS AS (unhex(md5(`url`))) STORED
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `visitor_history`
--

INSERT INTO `visitor_history` (`id`, `url`, `uid`, `uname`, `time`) VALUES
(249, '/userdetails.php?1', 1, 'admin', '2025-12-05 09:58:52'),
(293, '/userdetails.php?17', 1, 'admin', '2025-12-05 09:58:59');

-- --------------------------------------------------------

--
-- Структура таблицы `wall`
--

CREATE TABLE `wall` (
  `id` int UNSIGNED NOT NULL,
  `user` int UNSIGNED NOT NULL DEFAULT '0',
  `owner` int UNSIGNED NOT NULL DEFAULT '0',
  `added` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `wall`
--

INSERT INTO `wall` (`id`, `user`, `owner`, `added`, `text`) VALUES
(3, 1, 1, '2025-09-23 11:49:54', 'fsadfas'),
(4, 1, 4, '2025-09-23 11:53:02', 'sdfasd'),
(5, 1, 4, '2025-09-23 11:53:06', 'ghjdsasdas'),
(6, 1, 4, '2025-09-23 11:53:09', 'проверяем'),
(9, 1, 1, '2025-09-30 12:20:47', 'dsfsadf'),
(14, 2, 1, '2025-09-30 14:27:22', 'привет тебе ! :angel:'),
(15, 2, 2, '2025-10-01 12:21:28', 'даже работает:)'),
(16, 1, 1, '2025-10-02 11:27:20', 'стена'),
(17, 2, 4, '2025-10-02 14:56:43', 'привеееет'),
(18, 12, 12, '2025-10-02 16:04:37', 'апвапв'),
(19, 12, 12, '2025-10-02 16:06:37', 'ываыфвафы'),
(20, 12, 13, '2025-10-02 16:09:13', 'ты кто?'),
(21, 1, 1, '2025-10-06 13:12:01', 'fsdfasd');

-- --------------------------------------------------------

--
-- Структура таблицы `winners`
--

CREATE TABLE `winners` (
  `id_winner` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `premium_id` int UNSIGNED DEFAULT NULL,
  `date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Индексы сохранённых таблиц
--

--
-- Индексы таблицы `avps`
--
ALTER TABLE `avps`
  ADD PRIMARY KEY (`arg`);

--
-- Индексы таблицы `bans`
--
ALTER TABLE `bans`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_first_last` (`first`,`last`),
  ADD KEY `idx_until` (`until`);

--
-- Индексы таблицы `bj`
--
ALTER TABLE `bj`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_placeholder` (`placeholder`),
  ADD KEY `idx_gamer` (`gamer`),
  ADD KEY `idx_gamewithid` (`gamewithid`),
  ADD KEY `idx_plstat` (`plstat`),
  ADD KEY `idx_date` (`date`);

--
-- Индексы таблицы `cards`
--
ALTER TABLE `cards`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_points` (`points`);

--
-- Индексы таблицы `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sort` (`sort`);

--
-- Индексы таблицы `checkcomm`
--
ALTER TABLE `checkcomm`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_checkid` (`checkid`),
  ADD KEY `idx_userid` (`userid`);

--
-- Индексы таблицы `comments`
--
ALTER TABLE `comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_torrent_added` (`torrent`,`added`),
  ADD KEY `idx_user` (`user`),
  ADD KEY `idx_comments_user` (`user`);

--
-- Индексы таблицы `config`
--
ALTER TABLE `config`
  ADD PRIMARY KEY (`config_id`),
  ADD UNIQUE KEY `uk_config` (`config`),
  ADD UNIQUE KEY `ux_config_config` (`config`);

--
-- Индексы таблицы `countries`
--
ALTER TABLE `countries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_name` (`name`);

--
-- Индексы таблицы `faq`
--
ALTER TABLE `faq`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_type_categ` (`type`,`categ`),
  ADD KEY `idx_flag` (`flag`),
  ADD KEY `idx_order` (`order`);

--
-- Индексы таблицы `files`
--
ALTER TABLE `files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_torrent` (`torrent`),
  ADD KEY `idx_torrent_filename` (`torrent`,`filename`);

--
-- Индексы таблицы `forums`
--
ALTER TABLE `forums`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_forums_sort_name` (`sort`,`name`),
  ADD KEY `idx_forums_visible` (`visible`),
  ADD KEY `idx_forums_minread` (`minclassread`);

--
-- Индексы таблицы `friends`
--
ALTER TABLE `friends`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_pair` (`userid`,`friendid`),
  ADD UNIQUE KEY `ux_user_friend` (`userid`,`friendid`),
  ADD KEY `idx_friendid` (`friendid`),
  ADD KEY `ix_friends_user` (`userid`),
  ADD KEY `idx_friend_user` (`friendid`,`userid`);

--
-- Индексы таблицы `futurerls`
--
ALTER TABLE `futurerls`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_userid` (`userid`),
  ADD KEY `idx_cat_added` (`cat`,`added`);

--
-- Индексы таблицы `groups`
--
ALTER TABLE `groups`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_name` (`name`);

--
-- Индексы таблицы `hackers`
--
ALTER TABLE `hackers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_added` (`added`),
  ADD KEY `idx_ip` (`ip`);

--
-- Индексы таблицы `invites`
--
ALTER TABLE `invites`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_invite` (`invite`),
  ADD KEY `idx_inviter` (`inviter`),
  ADD KEY `idx_inviteid` (`inviteid`),
  ADD KEY `idx_confirmed` (`confirmed`);

--
-- Индексы таблицы `karma`
--
ALTER TABLE `karma`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_vote_daily` (`type`,`value`,`user`,`voted_on`),
  ADD KEY `idx_type_user` (`type`,`user`),
  ADD KEY `idx_added` (`added`);

--
-- Индексы таблицы `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_receiver_unread` (`receiver`,`unread`,`added`),
  ADD KEY `idx_sender_added` (`sender`,`added`),
  ADD KEY `idx_saved` (`saved`);

--
-- Индексы таблицы `mybonus`
--
ALTER TABLE `mybonus`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_position` (`bonus_position`),
  ADD KEY `idx_art_position` (`bonus_art`,`bonus_position`);

--
-- Индексы таблицы `news`
--
ALTER TABLE `news`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_added` (`added`),
  ADD KEY `idx_userid_added` (`userid`,`added`);

--
-- Индексы таблицы `notconnectablepmlog`
--
ALTER TABLE `notconnectablepmlog`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_date` (`user`,`date`);

--
-- Индексы таблицы `pages`
--
ALTER TABLE `pages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_name` (`name`(191));

--
-- Индексы таблицы `peers`
--
ALTER TABLE `peers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_torrent_peer` (`torrent`,`peer_id`),
  ADD KEY `idx_userid` (`userid`),
  ADD KEY `idx_torrent_last` (`torrent`,`last_action`),
  ADD KEY `idx_connectable` (`connectable`),
  ADD KEY `idx_passkey` (`passkey`),
  ADD KEY `ix_peers_user_seeder` (`userid`,`seeder`);

--
-- Индексы таблицы `pollanswers`
--
ALTER TABLE `pollanswers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_vote_once` (`pollid`,`userid`),
  ADD KEY `idx_pollid_selection` (`pollid`,`selection`);

--
-- Индексы таблицы `polls`
--
ALTER TABLE `polls`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_added` (`added`);

--
-- Индексы таблицы `posts`
--
ALTER TABLE `posts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_topic_added` (`topicid`,`added`),
  ADD KEY `idx_forum_added` (`forumid`,`added`),
  ADD KEY `idx_user_added` (`userid`,`added`);

--
-- Индексы таблицы `rangclass`
--
ALTER TABLE `rangclass`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_name` (`name`);

--
-- Индексы таблицы `ratings`
--
ALTER TABLE `ratings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_user_torrent` (`user`,`torrent`),
  ADD KEY `idx_torrent` (`torrent`),
  ADD KEY `idx_added` (`added`);

--
-- Индексы таблицы `readposts`
--
ALTER TABLE `readposts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_user_topic` (`userid`,`topicid`),
  ADD KEY `idx_topicid` (`topicid`),
  ADD KEY `idx_userid` (`userid`);

--
-- Индексы таблицы `sendbonus`
--
ALTER TABLE `sendbonus`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_owner_added` (`owner`,`added`),
  ADD KEY `idx_receiver_added` (`receiver`,`added`);

--
-- Индексы таблицы `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`sid`),
  ADD KEY `idx_uid_time` (`uid`,`time`),
  ADD KEY `idx_time` (`time`),
  ADD KEY `idx_ip` (`ip`);

--
-- Индексы таблицы `shoutbox`
--
ALTER TABLE `shoutbox`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_date` (`date`),
  ADD KEY `idx_userid_date` (`userid`,`date`);

--
-- Индексы таблицы `sitelog`
--
ALTER TABLE `sitelog`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_added_type` (`added`,`type`);

--
-- Индексы таблицы `snatched`
--
ALTER TABLE `snatched`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_torrent` (`userid`,`torrent`),
  ADD KEY `idx_torrent_last` (`torrent`,`last_action`),
  ADD KEY `idx_finished` (`finished`),
  ADD KEY `idx_connectable` (`connectable`),
  ADD KEY `ix_snatched_user_finished` (`userid`,`finished`);

--
-- Индексы таблицы `stylesheets`
--
ALTER TABLE `stylesheets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_name` (`name`),
  ADD KEY `idx_uri` (`uri`(191));

--
-- Индексы таблицы `super_loto_tickets`
--
ALTER TABLE `super_loto_tickets`
  ADD PRIMARY KEY (`ticket_id`),
  ADD KEY `idx_user_gamedate` (`user_id`,`game_date`),
  ADD KEY `idx_active` (`active`);

--
-- Индексы таблицы `super_loto_winners`
--
ALTER TABLE `super_loto_winners`
  ADD PRIMARY KEY (`winner_id`),
  ADD UNIQUE KEY `ux_winners_date_user` (`date`,`user_id`),
  ADD KEY `idx_user_date` (`user_id`,`date`),
  ADD KEY `idx_jackpot` (`jackpot`);

--
-- Индексы таблицы `tags`
--
ALTER TABLE `tags`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_cat_name` (`category`,`name`),
  ADD KEY `idx_howmuch` (`howmuch`);

--
-- Индексы таблицы `topics`
--
ALTER TABLE `topics`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_forum_lastdate` (`forumid`,`lastdate`),
  ADD KEY `idx_lastdate` (`lastdate`);

--
-- Индексы таблицы `torrents`
--
ALTER TABLE `torrents`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_info_hash` (`info_hash`),
  ADD KEY `idx_category` (`category`),
  ADD KEY `idx_owner` (`owner`),
  ADD KEY `idx_visible_banned` (`visible`,`banned`),
  ADD KEY `idx_added` (`added`),
  ADD KEY `ix_torrents_owner` (`owner`),
  ADD KEY `idx_torrents_feed` (`visible`,`moderated`,`category`,`id`),
  ADD KEY `idx_torrents_cleanup` (`visible`,`last_action`,`visible_lock`);
ALTER TABLE `torrents` ADD FULLTEXT KEY `ft_name_descr` (`name`,`descr`,`tags`);

--
-- Индексы таблицы `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_username` (`username`),
  ADD UNIQUE KEY `uk_email` (`email`),
  ADD UNIQUE KEY `uk_passkey` (`passkey`),
  ADD KEY `idx_class` (`class`),
  ADD KEY `idx_country` (`country`),
  ADD KEY `idx_last_access` (`last_access`),
  ADD KEY `idx_enabled` (`enabled`),
  ADD KEY `idx_users_id` (`id`),
  ADD KEY `ix_users_bonus_desc` (`bonus` DESC,`id`),
  ADD KEY `ix_users_karma_desc` (`karma` DESC,`id`),
  ADD KEY `idx_users_bonus` (`bonus`),
  ADD KEY `idx_users_karma` (`karma`),
  ADD KEY `idx_users_forum_access` (`forum_access`);

--
-- Индексы таблицы `visitor_history`
--
ALTER TABLE `visitor_history`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_hash_uid` (`url_hash`,`uid`),
  ADD KEY `idx_uid` (`uid`),
  ADD KEY `idx_hash_time` (`url_hash`,`time`);

--
-- Индексы таблицы `wall`
--
ALTER TABLE `wall`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_owner` (`owner`),
  ADD KEY `idx_user` (`user`),
  ADD KEY `idx_owner_added` (`owner`,`added` DESC),
  ADD KEY `idx_owner_id` (`owner`,`id`);

--
-- Индексы таблицы `winners`
--
ALTER TABLE `winners`
  ADD PRIMARY KEY (`id_winner`),
  ADD KEY `idx_user` (`user_id`);

--
-- AUTO_INCREMENT для сохранённых таблиц
--

--
-- AUTO_INCREMENT для таблицы `bans`
--
ALTER TABLE `bans`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `bj`
--
ALTER TABLE `bj`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT для таблицы `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT для таблицы `checkcomm`
--
ALTER TABLE `checkcomm`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT для таблицы `comments`
--
ALTER TABLE `comments`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT для таблицы `config`
--
ALTER TABLE `config`
  MODIFY `config_id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT для таблицы `countries`
--
ALTER TABLE `countries`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=103;

--
-- AUTO_INCREMENT для таблицы `faq`
--
ALTER TABLE `faq`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT для таблицы `files`
--
ALTER TABLE `files`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `forums`
--
ALTER TABLE `forums`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT для таблицы `friends`
--
ALTER TABLE `friends`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT для таблицы `futurerls`
--
ALTER TABLE `futurerls`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `groups`
--
ALTER TABLE `groups`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT для таблицы `hackers`
--
ALTER TABLE `hackers`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `invites`
--
ALTER TABLE `invites`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT для таблицы `karma`
--
ALTER TABLE `karma`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT для таблицы `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=52;

--
-- AUTO_INCREMENT для таблицы `mybonus`
--
ALTER TABLE `mybonus`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT для таблицы `news`
--
ALTER TABLE `news`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT для таблицы `notconnectablepmlog`
--
ALTER TABLE `notconnectablepmlog`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT для таблицы `pages`
--
ALTER TABLE `pages`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT для таблицы `peers`
--
ALTER TABLE `peers`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `pollanswers`
--
ALTER TABLE `pollanswers`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT для таблицы `polls`
--
ALTER TABLE `polls`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT для таблицы `posts`
--
ALTER TABLE `posts`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT для таблицы `rangclass`
--
ALTER TABLE `rangclass`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT для таблицы `ratings`
--
ALTER TABLE `ratings`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- AUTO_INCREMENT для таблицы `readposts`
--
ALTER TABLE `readposts`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT для таблицы `sendbonus`
--
ALTER TABLE `sendbonus`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `shoutbox`
--
ALTER TABLE `shoutbox`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT для таблицы `sitelog`
--
ALTER TABLE `sitelog`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT для таблицы `snatched`
--
ALTER TABLE `snatched`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `stylesheets`
--
ALTER TABLE `stylesheets`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT для таблицы `super_loto_tickets`
--
ALTER TABLE `super_loto_tickets`
  MODIFY `ticket_id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT для таблицы `super_loto_winners`
--
ALTER TABLE `super_loto_winners`
  MODIFY `winner_id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT для таблицы `tags`
--
ALTER TABLE `tags`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT для таблицы `topics`
--
ALTER TABLE `topics`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT для таблицы `torrents`
--
ALTER TABLE `torrents`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT для таблицы `users`
--
ALTER TABLE `users`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT для таблицы `visitor_history`
--
ALTER TABLE `visitor_history`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=294;

--
-- AUTO_INCREMENT для таблицы `wall`
--
ALTER TABLE `wall`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT для таблицы `winners`
--
ALTER TABLE `winners`
  MODIFY `id_winner` int UNSIGNED NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
