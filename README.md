
# Torrentside

Современная переработка классического TBDev-движка для частных торрент-трекеров. Проект ориентирован на актуальные версии PHP, MySQL и современные практики безопасности, сохраняя простоту и скорость оригинального TBDev.

---

## 🚀 Возможности проекта
*   Полностью переработанный и оптимизированный код TBDev
*   Поддержка PHP 8.1+
*   Поддержка MySQL 8.0+, корректные индексы и улучшенные SQL-запросы
*   Кэширование через Memcached
*   Новая структура шаблонов и CSS (чистый минималистичный интерфейс)
*   **Современные механики безопасности:**
    *   `password_hash`
    *   CSRF-защита
    *   PDO / Prepared Statements
*   Улучшенная работа с профилями пользователей
*   Подготовка к интеграции Passkeys / WebAuthn
*   Улучшенная система загрузки/добавления торрентов
*   Поддержка локальной разработки на Windows/macOS/Docker

---

## 📦 Требования

| Компонент | Минимальная версия / Примечания |
| :--- | :--- |
| PHP | 8.1+ (рекомендовано 8.2–8.3) |
| MySQL | 8.0.30+ |
| Web-сервер | Apache или Nginx |
| Расширения PHP | `pdo`, `pdo_mysql`, `mbstring`, `curl`, `openssl`, `json` |
| Memcached (опционально) | Для кэширования |

---

## 🛠 Установка

### 1. Клонирование репозитория
```bash
git clone https://github.com/nickmsk9/torrentside.git
cd torrentside
```

### 2. Настройка окружения
Создайте файл конфигурации `/include/config.php`.

**Минимальный пример:**
```php
<?php
$mysql_user = "root";
$mysql_pass = "password";
$mysql_db   = "torrentside";
$mysql_host = "localhost";

$site_config = [
    "SITENAME" => "Torrentside",
    "SITEURL" => "http://localhost",
    "MEMCACHE_HOST" => "127.0.0.1",
    "MEMCACHE_PORT" => 11211,
];
```

### Social login
Для быстрого входа без регистрации можно включить Apple ID и Telegram через переменные окружения:

```bash
SOCIAL_TELEGRAM_ENABLED=1
SOCIAL_TELEGRAM_BOT_USERNAME=your_bot_name
SOCIAL_TELEGRAM_BOT_TOKEN=123456:ABCDEF

SOCIAL_APPLE_ENABLED=1
SOCIAL_APPLE_CLIENT_ID=com.example.web
SOCIAL_APPLE_TEAM_ID=TEAMID1234
SOCIAL_APPLE_KEY_ID=KEYID1234
SOCIAL_APPLE_PRIVATE_KEY_PATH=/absolute/path/AuthKey_KEYID1234.p8
```

Примечания:
* Telegram Login Widget требует бота и домен, привязанный через `@BotFather` (`/setdomain`).
* Apple Sign in для веба требует HTTPS и заранее настроенный `redirect_uri`.
* При первом social login движок создаёт обычного пользователя в `users` и связь в `social_accounts`.

### 3. Импорт базы данных
Файл SQL находится в основной директории: `torrent2.sql`.
```bash
mysql -u root -p torrentside < torrent2.sql
```

### 4. Настройка прав
```bash
chmod -R 0777 torrents/
chmod -R 0777 cache/
```

### 5. Запуск
Откройте сайт в браузере: [http://localhost/](http://localhost/)

---

## 🧩 Структура проекта
*   `/include` — ядро трекера, функции, конфиги
*   `/templates` — шаблоны HTML + CSS
*   `/sql` — структура базы данных
*   `/torrents` — загруженные torrent-файлы
*   `/cache` — временные файлы (при Memcached не критично)

---

## 💡 Преимущества Torrentside
*   **Актуальный код**, избавленный от устаревших конструкций TBDev
*   **Высокая скорость работы** благодаря кешированию
*   **Обновлённый UI**, который легко кастомизировать
*   **Масштабируемость** — репозиторий подходит как база для своих модулей
*   **Поддержка современного стека** разработки
*   **Чистая структура** для переноса на:
    *   Laravel API
    *   Vue/React frontend
    *   Docker-контейнеризацию

---

## 📌 Планы по развитию
*   Добавление Passkeys / WebAuthn
*   REST API для мобильных приложений
*   Новая панель администратора
*   Система ролей и прав
*   Улучшенное полнотекстовое поиск-ядро
*   Статистика и отчёты
*   Полная миграция на Laravel (опциональная ветка)

---

## 🤝 Контрибьютинг
Pull-request’ы приветствуются.  
Если хотите предложить улучшения — создавайте Issue.

---

## 📄 Лицензия
MIT — свободная для личных и коммерческих проектов.
