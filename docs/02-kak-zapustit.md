# Как запустить

## Что нужно

- PHP 8.0 или выше
- Composer
- MySQL
- Node.js (для веб-интерфейса, опционально)

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

### 5. Запустите сервер

```bash
php artisan serve
```

Откройте в браузере `http://localhost:8000`.

### 6. Проверьте API

Введите в терминале:

```bash
curl http://localhost:8000/api/v1/check
```

Вы должны увидеть ответ:

```json
{"message": "API работает!"}
```

## Защита API

Чтобы пользоваться остальными адресами API, нужен токен. Получите его через Laravel Passport и передавайте в каждом запросе:

```
Authorization: Bearer ВАШ_ТОКЕН
```
