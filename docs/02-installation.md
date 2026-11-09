# Установка и запуск

## Требования

| Требование | Версия |
|------------|--------|
| PHP | ^8.0.2 |
| Composer | последнией стабильной |
| Node.js + npm | для сборки фронтенда (опционально) |
| MySQL | 5.7 / 8.0 |
| Расширения PHP | bcmath, ctype, fileinfo, json, mbstring, openssl, pdo_mysql, tokenizer, xml |

## Пошаговая установка

```bash
# 1. Клонировать репозиторий
git clone <url-репозитория> apteka_api
cd apteka_api

# 2. Скопировать файл окружения
cp .env.example .env

# 3. Отредактировать .env — указать подключение к базе данных:
#    DB_CONNECTION=mysql
#    DB_HOST=127.0.0.1
#    DB_PORT=3306
#    DB_DATABASE=apteka_api
#    DB_USERNAME=...
#    DB_PASSWORD=...

# 4. Установить PHP-зависимости
composer install

# 5. Сгенерировать ключ приложения
php artisan key:generate

# 6. Выполнить миграции базы данных
php artisan migrate

# 7. (Опционально) Запустить сидеры для заполнения тестовыми данными
php artisan db:seed

# 8. (Опционально) Установить и собрать фронтенд
npm install
npm run dev          # для разработки
# или
npm run build        # для продакшена

# 9. Запустить локальный сервер разработки
php artisan serve
```

После запуска API доступно по адресу `http://localhost:8000/api/v1`.

## Настройка Passport (аутентификация API)

API защищено через Laravel Passport (middleware `client`). Перед первым использованием необходимо выполнить:

```bash
# Генерирует клюши шифрования и создаёт personal access / password клиенты
php artisan passport:install
```

Затем получить access-токен через Client Credentials grant:

```bash
curl -X POST http://localhost:8000/oauth/token \
  -d "grant_type=client_credentials&client_id=<ID>&client_secret=<SECRET>&scope=*"
```

Полученный `access_token` передавать в заголовке каждого запроса к API:

```bash
curl -H "Authorization: Bearer <access_token>" \
  http://localhost:8000/api/v1/check
```

## Проверка работоспособности

```bash
curl -H "Authorization: Bearer <токен>" http://localhost:8000/api/v1/check
# Ожидаемый ответ: {"message":"API работает!"}
```

## Запуск через Docker (если применимо)

В репозитории нет готовых Docker-файлов, но можно развернуть в любом LEMP/LAMP-окружении с PHP 8.0+, Composer и MySQL, соблюдая шаги выше.
