# Переменные окружения

Файл `.env` в корне репозитория. Пример — `.env.example`, тестовое окружение — `.env.testing`.

## Основные переменные

| Переменная | Описание | Пример |
|------------|----------|--------|
| `APP_NAME` | Название приложения | `Laravel` |
| `APP_ENV` | Окружение | `local`, `production`, `testing` |
| `APP_KEY` | Ключ шифрования (генерируется `php artisan key:generate`) | `base64:...` |
| `APP_DEBUG` | Режим отладки | `true` / `false` |
| `APP_URL` | URL приложения | `http://localhost` |

## База данных

| Переменная | Описание |
|------------|----------|
| `DB_CONNECTION` | Драйвер БД (`mysql`) |
| `DB_HOST` | Хост базы данных |
| `DB_PORT` | Порт (обычно `3306`) |
| `DB_DATABASE` | Имя базы данных |
| `DB_USERNAME` | Пользователь БД |
| `DB_PASSWORD` | Пароль БД |

## Кеш, сессии, очереди, почта

| Переменная | Значение по умолчанию (из `.env.example`) |
|------------|-------------------------------------------|
| `BROADCAST_DRIVER` | `log` |
| `CACHE_DRIVER` | `file` |
| `FILESYSTEM_DISK` | `local` |
| `QUEUE_CONNECTION` | `sync` |
| `SESSION_DRIVER` | `file` |
| `SESSION_LIFETIME` | `120` |
| `MAIL_MAILER` | `smtp` |
| `MAIL_HOST` | `mailhog` |
| `MAIL_PORT` | `1025` |

## Тестовое окружение (`.env.testing`)

Используется при запуске тестов:

- `DB_DATABASE=aptekaapitesting`
- `DB_USERNAME=homestead`
- `DB_PASSWORD=secret`
- `APP_DEBUG=true`

Тесты запускаются командой:

```bash
vendor/bin/phpunit
```

Функциональные тесты пакета бонусов находятся в `packages/Solt/Bonus/src/Tests/`.

## Примечание

Реальный `.env` не попадает в репозиторий (добавлен в `.gitignore`). Создавайте его из `.env.example` локально.
