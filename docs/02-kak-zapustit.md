# Как запустить

## Что нужно

- PHP 8.3 или выше
- Composer
- MySQL

## Шаги

### 1. Скопируйте настройки

```bash
cp .env.example .env
```

Откройте файл `.env` и укажите данные для базы данных:

```
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=apteka
DB_USERNAME=root
DB_PASSWORD=
```

### 2. Установите пакеты PHP

```bash
composer install
```

### 3. Создайте ключ приложения

```bash
php artisan key:generate
```

### 4. Создайте таблицы

```bash
php artisan migrate
```

### 5. Создайте ключи для токенов

```bash
php artisan passport:keys
```

### 6. Создайте клиента API

Клиент — это программа, которая будет обращаться к API, например касса.

```bash
php artisan passport:client --client
```

Команда спросит название и покажет два значения: `Client ID` и `Client Secret`. Сохраните оба. Секрет показывается только один раз.

### 7. Запустите сервер

```bash
php artisan serve
```

API будет доступно по адресу `http://localhost:8000/api/v1`.

Веб-страниц у проекта нет. Если открыть `http://localhost:8000` в браузере, появится страница «404». Это нормально.

### 8. Получите токен

Введите в терминале. Вместо `ВАШ_CLIENT_ID` и `ВАШ_CLIENT_SECRET` подставьте значения из шага 6:

```bash
curl -X POST http://localhost:8000/oauth/token \
  -d "grant_type=client_credentials" \
  -d "client_id=ВАШ_CLIENT_ID" \
  -d "client_secret=ВАШ_CLIENT_SECRET"
```

В ответе будет поле `access_token`. Это и есть токен. Он действует один год.

### 9. Проверьте API

```bash
curl -H "Authorization: Bearer ВАШ_ТОКЕН" http://localhost:8000/api/v1/check
```

Вы должны увидеть ответ:

```json
{"message": "API работает!"}
```

В терминале русские буквы могут выглядеть как `\u0440\u0430...`. Это тот же ответ.

Если токен не передать, ответ будет таким:

```json
{"message": "Unauthenticated."}
```

## Защита API

Токен нужен для всех адресов API. Передавайте его в каждом запросе:

```
Authorization: Bearer ВАШ_ТОКЕН
```

Не запрашивайте новый токен перед каждым запросом. Получите его один раз и используйте, пока он действует.

## Если проект уже работал на старой версии

Проект обновлён с Laravel 9 до Laravel 13. Как обновить работающую установку, написано в разделе «Обновление со старой версии» файла [02-installation.md](02-installation.md).
