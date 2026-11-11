# Установка и запуск

## Требования

| Требование | Версия |
|------------|--------|
| PHP | 8.3 или новее (проверено на 8.3, 8.4 и 8.5) |
| Composer | последнией стабильной |
| MySQL | 5.7 / 8.0 |
| Расширения PHP | ctype, dom, fileinfo, filter, hash, iconv, json, libxml, mbstring, openssl, pcre, pdo_mysql, session, sodium, tokenizer, xml |

Проверить расширения на сервере можно командой `composer check-platform-reqs --lock --no-dev`. Расширение `pdo_mysql` она не проверяет.

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

# 8. Запустить локальный сервер разработки
php artisan serve
```

После запуска API доступно по адресу `http://localhost:8000/api/v1`. Перед первым запросом нужно настроить Passport (следующий раздел).

## Настройка Passport (аутентификация API)

API защищено через Laravel Passport (middleware `client`). Перед первым использованием необходимо выполнить:

```bash
# Генерирует ключи, которыми подписываются токены:
# storage/oauth-private.key и storage/oauth-public.key
php artisan passport:keys

# Создаёт клиента для Client Credentials grant
php artisan passport:client --client
```

Вторая команда спрашивает название клиента и выводит `Client ID` и `Client Secret`. Секрет хранится в базе в виде хеша и показывается только один раз.

Команда `passport:install` для этого проекта не нужна: миграции и настройки Passport уже лежат в репозитории.

Файлы ключей должны быть доступны только владельцу: права `600` или `660`. Команда `passport:keys` выставляет их сама. Если права шире, например `644`, Passport отвечает ошибкой 500.

Затем получить access-токен через Client Credentials grant:

```bash
curl -X POST http://localhost:8000/oauth/token \
  -d "grant_type=client_credentials&client_id=<ID>&client_secret=<SECRET>&scope=*"
```

Токен действует год. Клиент должен запросить его один раз и использовать повторно. Выдача токена — самый дорогой запрос: около 85 мс процессорного времени против 5–15 мс у остальных.

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

В репозитории нет готовых Docker-файлов, но можно развернуть в любом LEMP/LAMP-окружении с PHP 8.3+, Composer и MySQL, соблюдая шаги выше.

## Установка на боевой сервер

Отличия от локальной установки:

```bash
# Зависимости без пакетов для разработки
composer install --no-dev --optimize-autoloader

# Миграции без вопроса о подтверждении
php artisan migrate --force

# Ключи и клиент API, как описано выше
php artisan passport:keys
php artisan passport:client --client

# Кеш настроек и маршрутов. Пересобирать после каждого изменения кода или .env
php artisan config:cache
php artisan route:cache
```

- В `.env` должны стоять `APP_ENV=production` и `APP_DEBUG=false`.
- Корень сайта — папка `public`.
- В PHP должен быть включён OPcache. Без него запрос обходится примерно в 12 раз дороже по процессорному времени (см. [08-hosting-report.md](08-hosting-report.md), раздел 10).

## Обновление со старой версии (Laravel 9, PHP 8.1)

Проект переведён на Laravel 13 и Passport 13. Если API уже работает на старой версии:

1. Сделайте резервную копию базы данных и файлов `storage/oauth-private.key` и `storage/oauth-public.key`.
2. Переключите сайт на PHP 8.3 или новее.
3. Обновите код и выполните:

```bash
composer install --no-dev --optimize-autoloader

# Passport 13 требует, чтобы файлы ключей были доступны только владельцу
chmod 600 storage/oauth-private.key storage/oauth-public.key

# Добавляет одну таблицу oauth_device_codes, остальные не меняет
php artisan migrate --force

# Секреты клиентов теперь хранятся в виде хеша. Сами секреты остаются прежними
php artisan passport:hash --force

# Кеш маршрутов от старой версии не подходит, его нужно пересобрать
php artisan config:cache
php artisan route:cache
```

Порядок проверен на базе, созданной старой версией.

**Что остаётся прежним:** данные в базе, выданные ранее токены, `Client ID` и `Client Secret` клиентов, все адреса API и их успешные ответы.

**Что изменилось:**

- Веб-страниц (главная, вход, регистрация) больше нет. Адрес `/` отвечает `404`.
- Ошибки API приходят в JSON, даже если клиент не прислал `Accept: application/json`. Раньше в этом случае запрос без токена получал перенаправление `302` на страницу входа, а ошибка валидации — перенаправление `302` на главную.
- `POST /oauth/token`: в ответах с ошибкой больше нет поля `message`, остались `error` и `error_description`. Запрос с `grant_type=password` получает `400 unsupported_grant_type` вместо `401`.
- Тексты поля `message` в ответах `404` и `405` другие.
- Выдача токена стала дороже по процессорному времени: около 85 мс вместо 13. Секрет клиента теперь сверяется с хешем. Клиент должен запрашивать токен один раз и использовать его повторно.
- Команда `passport:install` не нужна. Для новой установки используются `passport:keys` и `passport:client --client`.
