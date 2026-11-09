# Apteka API — Полная документация (сложная, детальная)

Собрано из разделов:
- 01-overview.md — обзор и архитектура
- 02-installation.md — установка и запуск
- 03-api.md — справочник API
- 04-database.md — база данных
- 05-environment.md — переменные окружения
- 06-testing.md — тестирование
- 07-contributing.md — вклад в проект

---

# 1. Обзор и архитектура

## Назначение проекта

**Apteka API** — бэкенд системы лояльности сети аптек. Основная сущность — **бонусная карта** клиента, к которой привязан бонусный баланс. Баланс карты хранится в разрезе **юридических лиц** (таблица `bonus_balances`), а все движения средств фиксируются в **операциях по карте** (`card_operations`). Карты привязаны к конкретным **аптекам** (`pharmacies`), которые, в свою очередь, относятся к юридическим лицам.

## Доменная модель

| Сущность | Ключевое поле | Назначение |
|----------|--------------|------------|
| Бонусная карта (`cards`) | `short_code` | Карта клиента с персональными данными, статусом и балансом |
| Юридическое лицо (`legal_entities`) | `inn` | Организация, к которой относится баланс и аптека |
| Аптека (`pharmacies`) | `number` | Торговая точка, привязанная к юридическому лицу |
| Операция по карте (`card_operations`) | `document_number` + `document_type` | Начисление, списание, возврат, отмена или перевод бонусов |
| Бонусный баланс (`bonus_balances`) | `card_id` + `legal_entity_id` | Остаток бонусов карты по конкретному юридическому лицу |

## Архитектура пакета `Solt\Bonus`

API выделено в отдельный Laravel-пакет `packages/Solt/Bonus`, зарегистрированный через `BonusServiceProvider`. Все классы пакета находятся в пространстве имён `Solt\Bonus\` и загружаются через PSR-4 в `composer.json`.

Слои пакета:

1. **Маршруты** (`src/routes/api.php`) — все API-маршруты имеют префикс `/api/v1` и защищены middleware `client` (Laravel Passport, проверка client-credentials токена).
2. **Контроллеры** (`src/Http/Controllers/`) — принимают HTTP-запросы и делегируют работу сервисам. Возвращают JSON-ответы со статус-кодами (200, 201, 202, 204).
3. **Формы запросов** (`src/Http/Requests/`) — валидация входящих данных (правила Laravel FormRequest).
4. **Ресурсы** (`src/Http/Resources/`) — преобразование Eloquent-моделей в массивы/JSON-ответы (CardResource, PharmacyResource и т.д.).
5. **Модели** (`src/Models/`) — Eloquent-модели с мягким удалением (`SoftDeletes`): `Card`, `Pharmacy`, `LegalEntity`, `CardOperation`, `BonusBalance`.
6. **Сервисы** (`src/Services/`) — бизнес-логика: `CardService`, `CardOperationService`, `PharmacyService`, `LegalEntityService`, `BonusBalanceService`.
7. **Интерфейсы** (`src/Interfaces/`) — контракты сервисов (`CardInterface`, `CardOperationInterface` и т.д.), реализуются соответствующими сервисами.

## Аутентификация

Веб-часть приложения (Inertia.js + Breeze) использует классическую сессионную аутентификацию. API-маршруты защищены middleware `client` (`\Laravel\Passport\Http\Middleware\CheckClientCredentials`), поэтому каждый запрос к API должен содержать заголовок:

```
Authorization: Bearer <oauth_access_token>
```

Токен получается через OAuth 2.0 Client Credentials grant Laravel Passport.

## Статус-коды ответов (особенность реализации)

В проекте используется нестандартная трактовка некоторых кодов:

- `200 OK` — успешное получение/обновление данных.
- `201 Created` — успешное создание ресурса.
- `202 Accepted` — ресурс не найден или операция не выполнена (например, «Карта не найдена», «Нулевой баланс»).
- `204 No Content` — успешное удаление ресурса.

Все ответы возвращаются в формате JSON.

## Технологии

- **Laravel 9** — маршрутизация, контейнер DI, миграции, ORM Eloquent.
- **Laravel Passport** — OAuth 2.0 сервер для защиты API.
- **Inertia.js + Vue 3** — монолитный веб-интерфейс без отдельного SPA-бэкенда.
- **Tailwind CSS** — оформление веб-части.
- **PHPUnit** — функциональные и модульные тесты.

---

# 2. Установка и запуск

## Требования

| Требование | Версия |
|------------|--------|
| PHP | ^8.0.2 |
| Composer | последней стабильной |
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
# Генерирует ключи шифрования и создаёт personal access / password клиенты
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

---

# 3. Справочник API

## Базовая информация

| Параметр | Значение |
|----------|----------|
| Базовый URL | `http://<домен>/api/v1` |
| Префикс всех маршрутов | `/api/v1` |
| Middleware защиты | `client` (Laravel Passport, OAuth 2.0 client credentials) |
| Формат данных | JSON |
| Заголовок авторизации | `Authorization: Bearer <access_token>` |
| Тип контента запроса | `application/json` |

Пример проверки работоспособности:

```bash
curl -H "Authorization: Bearer <токен>" \
  https://api.example.com/api/v1/check
```

```json
{"message": "API работает!"}
```

## Группа `/bonus`

Все методы ниже находятся под префиксом `/api/v1/bonus`.

### 1. Бонусные карты (`/bonus/card`)

#### Получить карту
```http
GET /api/v1/bonus/card/{short_code}
```

| Параметр | Тип | Описание |
|----------|-----|----------|
| `short_code` | string, путь | Штрих-код карты (9–13 символов) |

**Ответ 200** — карта найдена:

```json
{
  "id": 1,
  "short_code": "1234567890",
  "firstname": "Иван",
  "name": "Иванов",
  "lastname": "Иванович",
  "birthdate": "01.05.1985",
  "email": "ivan@example.com",
  "phone": "+79001234567",
  "city": "Москва",
  "gender": "м",
  "newsletter_agree": true,
  "guid": null,
  "status": "действительна"
}
```

**Ответ 202** — карта не найдена: `{"message": "Карта не найдена"}`.

#### Создать карту
```http
POST /api/v1/bonus/card
```

**Тело запроса** (валидация `StoreRequest`):

| Поле | Тип | Обяз. | Ограничения | Описание |
|------|-----|-------|-------------|----------|
| `short_code` | string | да | min 9, max 13, уникален | Штрих-код карты |
| `firstname` | string | да | max 255 | Имя |
| `name` | string | да | max 255 | Фамилия |
| `lastname` | string | нет | max 255 | Отчество |
| `birthdate` | date | да | дата | Дата рождения |
| `email` | string | нет | email, max 255 | Email |
| `phone` | string | нет | max 12, regex `^\+7[0-9]{10}$`, уникален | Телефон |
| `city` | string | нет | max 255 | Город |
| `gender` | integer | да | between 0,1 | 0 = м, 1 = ж |
| `newsletter_agree` | integer | да | between 0,1 | Согласие на рассылку |
| `guid` | string | нет | max 255 | Внешний идентификатор |
| `status` | integer | да | between 0,2 | 0/1/2 (действительна/аннулирована/заменена) |

**Ответ 201** — созданная карта (в формате `CardResource`, см. пример выше).

**Пример запроса:**

```bash
curl -X POST https://api.example.com/api/v1/bonus/card \
  -H "Authorization: Bearer <токен>" \
  -H "Content-Type: application/json" \
  -d '{
    "short_code": "1234567890",
    "firstname": "Иван",
    "name": "Иванов",
    "birthdate": "1985-05-01",
    "gender": 0,
    "newsletter_agree": 1,
    "status": 0
  }'
```

#### Обновить карту
```http
PUT /api/v1/bonus/card/{short_code}
```

**Параметры пути:** `short_code` — штрих-код карты.

**Тело запроса** (валидация `UpdateRequest`): все поля опциональны (`sometimes`), те же ограничения, что при создании. `short_code` тоже может быть обновлён и должен оставаться уникальным.

**Ответ 200** — обновлённая карта.

**Ответ 202** — карта не найдена или не удалось обновить: `{"message": "Не удалось обновить данные карты"}`.

#### Удалить карту
```http
DELETE /api/v1/bonus/card/{short_code}
```

Удаление логическое: карта помечается как аннулированная (`status = 1`) и проходит мягкое удаление (`softDeletes`).

**Ответ 204** — `{"message": "Карта успешно удалена"}`.

**Ответ 202** — не удалось удалить: `{"message": "Не удалось удалить карту"}`.

#### Получить баланс карты
```http
GET /api/v1/bonus/balance/{short_code}
```

| Параметр | Тип | Описание |
|----------|-----|----------|
| `short_code` | string, путь | Штрих-код карты |

**Ответ 200:**

```json
{"balance": 1250.50}
```

**Ответ 202** — карта не найдена: `{"message": "Карта не найдена"}`.

#### Начислить бонусы
```http
POST /api/v1/bonus/increase
```

**Тело запроса** (валидация `IncreaseRequest`):

| Поле | Тип | Обяз. | Ограничения | Описание |
|------|-----|-------|-------------|----------|
| `short_code` | string | да | min 9, max 13, карта должна существовать | Штрих-код карты |
| `pharmacy_number` | integer | да | between 1,99, аптека должна существовать | Номер аптеки |
| `inn` | string | да | regex `^[0-9]{10,20}$`, юр. лицо должно существовать | ИНН |
| `document_date` | date | да | дата документа | Дата документа |
| `document_number` | string | да | max 12, уникален | Номер документа |
| `document_type` | string | да | max 40 | Тип документа |
| `amount` | numeric | да | min 0.01 | Сумма начисления |

**Ответ 201** — созданная операция (в формате `CardOperationResource`).

**Ответ 202** — `{"message": "Нулевой баланс"}` (если итоговый баланс стал бы отрицательным).

#### Вернуть начисление
```http
POST /api/v1/bonus/increase/return
```

**Тело запроса** (валидация `IncreaseReturnRequest`): те же поля, что при начислении, но `amount` должен быть `max: -0.01` (отрицательная сумма возврата).

**Ответ 201** — операция возврата.

**Ответ 202** — `{"message": "Нулевой баланс"}`.

#### Списание бонусов
```http
POST /api/v1/bonus/decrease
```

**Тело запроса** (валидация `DecreaseRequest`): как при начислении, но `amount` должен быть `min: 0.01` (положительная сумма, которая вычитается).

**Ответ 201** — операция списания.

**Ответ 202** — `{"message": "Нулевой баланс"}`.

#### Вернуть списание
```http
POST /api/v1/bonus/decrease/return
```

**Тело запроса** (валидация `DecreaseReturnRequest`): `amount` должен быть `max: -0.01`.

**Ответ 201** — операция возврата списания.

**Ответ 202** — `{"message": "Нулевой баланс"}`.

#### Отменить операцию
```http
POST /api/v1/bonus/increase/cancel
# или
POST /api/v1/bonus/decrease/cancel
```

**Тело запроса** (валидация `CancelRequest`):

| Поле | Тип | Обяз. | Ограничения | Описание |
|------|-----|-------|-------------|----------|
| `short_code` | string | да | min 9, max 13, карта должна существовать | Штрих-код карты |
| `document_date` | date | да | дата документа | Дата документа |
| `document_number` | string | да | max 12, операция с таким номером должна существовать | Номер документа |
| `document_type` | string | да | max 40, тип должен совпадать с операцией | Тип документа |

Отмена возможна, только если баланс карты после отмены не станет отрицательным.

**Ответ 204** — `{"message": "Операция успешно отменена"}`.

**Ответ 202** — `{"message": "Не удалось отменить операцию"}`.

#### Перевести баланс с карты на карту
```http
POST /api/v1/bonus/move
```

**Тело запроса** (валидация `MoveRequest`):

| Поле | Тип | Обяз. | Ограничения | Описание |
|------|-----|-------|-------------|----------|
| `short_code_from` | string | да | min 9, max 13, карта-источник должна существовать | Карта отправитель |
| `short_code_to` | string | да | min 9, max 13, карта-получатель должна существовать | Карта получатель |
| `document_date` | date | да | дата документа | Дата документа |
| `document_number` | string | да | max 12, уникален | Номер документа |
| `document_type` | string | да | max 40 | Тип документа |

Весь баланс карты-отправителя перечисляется на карту-получателя (баланс источника обнуляется). Перевод невозможен, если баланс источника равен 0.

**Ответ 200** — `{"message": "Баланс успешно перенесен"}`.

**Ответ 202** — `{"message": "Ошибка переноса баланса"}`.

#### Найти карту по полям
```http
POST /api/v1/bonus/find
```

**Тело запроса** (валидация `FindRequest`):

| Поле | Тип | Обяз. | Ограничения | Описание |
|------|-----|-------|-------------|----------|
| `phone` | string | да | max 12, regex `^\+7[0-9]{10}$`, карта с таким телефоном должна существовать | Телефон клиента |

Поиск выполняется по переданным полям (в текущей реализации — по `phone`) среди неудалённых карт со статусом «действительна».

**Ответ 200** — найденная карта (`CardResource`).

**Ответ 202** — `{"message": "Карта не найдена"}`.

### 2. Аптеки (`/bonus/pharmacy`)

#### Список аптек
```http
GET /api/v1/bonus/pharmacy
```

**Ответ 200** — массив аптек (`PharmacyResource`), каждая с вложенным юридическим лицом:

```json
[
  {
    "id": 1,
    "legal_entity": {
      "id": 1,
      "inn": "7701234567",
      "name": "ООО \"Аптека-сеть\""
    },
    "number": 5,
    "name": "Аптека №5"
  }
]
```

#### Получить аптеку
```http
GET /api/v1/bonus/pharmacy/{number}
```

| Параметр | Тип | Описание |
|----------|-----|----------|
| `number` | integer, путь | Номер аптеки (1–99) |

**Ответ 200** — аптека.

**Ответ 202** — `{"message": "Аптека не найдена"}`.

#### Создать аптеку
```http
POST /api/v1/bonus/pharmacy
```

**Тело запроса** (`StoreRequest`):

| Поле | Тип | Обяз. | Ограничения | Описание |
|------|-----|-------|-------------|----------|
| `legal_entity` | integer | да | существует в `legal_entities` | ID юридического лица |
| `number` | integer | да | between 1,99, уникален | Номер аптеки |
| `name` | string | да | max 255 | Наименование |

**Ответ 201** — созданная аптека.

#### Обновить аптеку
```http
PUT /api/v1/bonus/pharmacy/{number}
```

**Тело запроса** (`UpdateRequest`): `number` и/или `name` — опциональны.

**Ответ 200** — обновлённая аптека.

**Ответ 202** — аптека не найдена или не удалось обновить.

#### Изменить юридическое лицо аптеки
```http
PUT /api/v1/bonus/pharmacy/legal-entity/{number}
```

**Тело запроса** (`UpdateLegalEntityRequest`):

| Поле | Тип | Обяз. | Описание |
|------|-----|-------|----------|
| `legal_entity` | integer | да | ID нового юридического лица (должно существовать) |

**Ответ 200** — аптека с новым юр. лицом.

**Ответ 202** — не удалось обновить.

#### Удалить аптеку
```http
DELETE /api/v1/bonus/pharmacy/{number}
```

**Ответ 204** — `{"message": "Юо. лицо успешно удалено"}` (текст ответа оставлен как в исходном коде).

**Ответ 202** — `{"message": "Не удалось удалить юр. лицо"}`.

### 3. Юридические лица (`/bonus/legal-entity`)

#### Список юр. лиц
```http
GET /api/v1/bonus/legal-entity
```

**Ответ 200** — массив объектов:

```json
[
  {
    "id": 1,
    "inn": "7701234567",
    "name": "ООО \"Аптека-сеть\""
  }
]
```

#### Получить юр. лицо
```http
GET /api/v1/bonus/legal-entity/{inn}
```

| Параметр | Тип | Описание |
|----------|-----|----------|
| `inn` | string, путь | ИНН (10–20 цифр) |

**Ответ 200** — юр. лицо.

**Ответ 202** — `{"message": "Юр. лицо не найдено"}`.

#### Создать юр. лицо
```http
POST /api/v1/bonus/legal-entity
```

**Тело запроса** (`StoreRequest`):

| Поле | Тип | Обяз. | Ограничения | Описание |
|------|-----|-------|-------------|----------|
| `inn` | string | да | regex `^[0-9]{10,20}$`, уникален | ИНН |
| `name` | string | да | max 255 | Наименование |

**Ответ 201** — созданное юр. лицо.

#### Обновить юр. лицо
```http
PUT /api/v1/bonus/legal-entity/{inn}
```

**Тело запроса** (`UpdateRequest`): `inn` и/или `name` — опциональны.

**Ответ 200** — обновлённое юр. лицо.

**Ответ 202** — не найдено или не удалось обновить.

#### Удалить юр. лицо
```http
DELETE /api/v1/bonus/legal-entity/{inn}
```

**Ответ 204** — `{"message": "Юо. лицо успешно удалено"}`.

**Ответ 202** — `{"message": "Не удалось удалить юр. лицо"}`.

## Форматы ресурсов (ответы API)

### CardResource

```json
{
  "id": 1,
  "short_code": "1234567890",
  "firstname": "Иван",
  "name": "Иванов",
  "lastname": "Иванович",
  "birthdate": "01.05.1985",
  "email": "ivan@example.com",
  "phone": "+79001234567",
  "city": "Москва",
  "gender": "м",
  "newsletter_agree": true,
  "guid": null,
  "status": "действительна"
}
```

### CardOperationResource

```json
{
  "id": 10,
  "card": { …CardResource… },
  "legal_entity": { "id": 1, "inn": "…", "name": "…" },
  "pharmacy": { "id": 3, "legal_entity": {…}, "number": 5, "name": "Аптека №5" },
  "action": 0,
  "document_date": "2022-08-02",
  "document_number": "ORD-001",
  "document_type": "чек",
  "amount": 150.00
}
```

Значения `action`: `0` — начисление, `1` — списание, `2` — перевод, `3` — возврат начисления, `4` — возврат списания.

### PharmacyResource / LegalEntityResource / BonusBalanceResource

Описаны в соответствующих разделах выше.

## Коды ответов

| Код | Значение |
|-----|----------|
| 200 | Успешное получение или обновление данных |
| 201 | Ресурс успешно создан |
| 202 | Ресурс не найден / операция не выполнена (бизнес-ошибка) |
| 204 | Ресурс успешно удалён (нет контента) |

> **Примечание:** в проекте используется нестандартная трактовка кодов `202` (ошибки бизнеса/отсутствие ресурса) и `204` (успешное удаление). При интеграции ориентируйтесь на именно эту семантику.

## Ошибки валидации

При не прохождении валидации формы запроса Laravel возвращает `422 Unprocessable Entity` с массивом ошибок по полям.

---

# 4. База данных

Все таблицы используют мягкое удаление (`softDeletes`) и временные метки `created_at` / `updated_at`. Миграции пакета лежат в `packages/Solt/Bonus/src/Database/Migrations/`.

## Таблица `cards` — бонусные карты

| Поле | Тип | Описание |
|------|-----|----------|
| `id` | bigIncrements | Первичный ключ |
| `short_code` | string(255), индекс | Штрих-код карты (уникальный в логике валидации) |
| `firstname` | string(255) | Имя |
| `name` | string(255) | Фамилия |
| `lastname` | string(255), nullable | Отчество |
| `birthdate` | date | Дата рождения |
| `email` | string(255), nullable | Email |
| `phone` | string(255), nullable | Телефон (формат +7XXXXXXXXXX) |
| `city` | string(255), nullable | Город |
| `gender` | unsignedTinyInteger | Пол: 0 = м, 1 = ж |
| `newsletter_agree` | boolean, default false | Согласие на рассылку |
| `guid` | string(255), nullable | Внешний идентификатор |
| `status` | unsignedTinyInteger | Статус: 0 = действительна, 1 = аннулирована, 2 = заменена |

Статусы и пол определены в модели `Card`:
- `Card::$genders = [0 => 'м', 1 => 'ж']`
- `Card::$statuses = [0 => 'действительна', 1 => 'аннулирована', 2 => 'заменена']`

## Таблица `legal_entities` — юридические лица

| Поле | Тип | Описание |
|------|-----|----------|
| `id` | bigIncrements | Первичный ключ |
| `inn` | string(30) | ИНН (в начальной миграции уникален, позже индекс уникальности удалён миграцией `2022_09_07_094441`) |
| `name` | text | Наименование |

## Таблица `pharmacies` — аптеки

| Поле | Тип | Описание |
|------|-----|----------|
| `id` | bigIncrements | Первичный ключ |
| `legal_entity_id` | unsignedBigInteger | FK → `legal_entities.id` |
| `number` | unsignedInteger, индекс | Номер аптеки (1–99) |
| `name` | text | Наименование аптеки |

## Таблица `card_operations` — операции по картам

| Поле | Тип | Описание |
|------|-----|----------|
| `id` | bigIncrements | Первичный ключ |
| `legal_entity_id` | unsignedBigInteger, nullable | FK → `legal_entities.id` |
| `pharmacy_id` | unsignedBigInteger, nullable | FK → `pharmacies.id` |
| `card_id` | unsignedBigInteger | FK → `cards.id` |
| `action` | unsignedSmallInteger, nullable | Тип действия (см. ниже) |
| `document_date` | date | Дата документа |
| `document_number` | string(255) | Номер документа |
| `document_type` | string(255) | Тип документа |
| `amount` | decimal | Сумма операции |

Типы действий (`CardOperation`):
- `0` — `increase_action` — начисление
- `1` — `decreser_action` — списание
- `2` — `move_action` — перевод баланса
- `3` — `increase_return_action` — возврат начисления
- `4` — `decreser_return_action` — возврат списания

## Таблица `bonus_balances` — бонусные остатки

| Поле | Тип | Описание |
|------|-----|----------|
| `id` | bigIncrements | Первичный ключ |
| `legal_entity_id` | unsignedBigInteger | FK → `legal_entities.id` |
| `card_id` | unsignedBigInteger | FK → `cards.id` |
| `sum` | decimal, default 0.0 | Остаток бонусов карты по юридическому лицу |

## Связи моделей

- `Card` → `operations` (hasMany `CardOperation`)
- `Card` → `balances` (hasMany `BonusBalance`)
- `CardOperation` → `card` (belongsTo), `legal_entity` (belongsTo), `pharmacy` (belongsTo)
- `LegalEntity` → `operations` (hasMany), `pharmacies` (hasMany), `bonusBalances` (hasMany)
- `Pharmacy` → `operations` (hasMany), `legal_entity` (belongsTo)
- `BonusBalance` → `card` (belongsTo), `legal_entity` (belongsTo)

## Применение миграций

```bash
php artisan migrate
```

Откат:

```bash
php artisan migrate:rollback
```

---

# 5. Переменные окружения

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

---

# 6. Тестирование

## Инструмент

Тесты написаны на **PHPUnit 9**. Конфигурация — `phpunit.xml` в корне репозитория.

## Запуск всех тестов

```bash
vendor/bin/phpunit
```

## Тесты пакета бонусов

Функциональные тесты пакета `Solt\Bonus` находятся в:

```
packages/Solt/Bonus/src/Tests/
├── TestCase.php
├── Feature/
│   ├── Bonus/
│   │   └── BonusTest.php
│   ├── Card/
│   │   ├── CreateTest.php
│   │   ├── DeleteTest.php
│   │   ├── FindTest.php
│   │   ├── GetBalanceTest.php
│   │   ├── GetByPhoneTest.php
│   │   ├── GetTest.php
│   │   ├── MoveBalancesTest.php
│   │   └── UpdateTest.php
│   ├── CardOperation/
│   │   ├── DecreaseCancelTest.php
│   │   ├── DecreaseTest.php
│   │   ├── DecreaseReturnTest.php
│   │   ├── IncreaseCancelTest.php
│   │   ├── IncreaseReturnTest.php
│   │   └── IncreaseTest.php
│   ├── LegalEntity/
│   │   ├── CreateTest.php
│   │   ├── DeleteTest.php
│   │   ├── GetTest.php
│   │   └── UpdateTest.php
│   └── Pharmacy/
│       ├── CreateTest.php
│       ├── DeleteTest.php
│       ├── GetTest.php
│       └── UpdateTest.php
```

Запустить только тесты пакета бонусов:

```bash
vendor/bin/phpunit packages/Solt/Bonus/src/Tests
```

## Написание новых тестов

- Функциональные тесты добавляются в `packages/Solt/Bonus/src/Tests/Feature/` с пространством имён `Solt\Bonus\Tests\`.
- Базовый класс — `packages/Solt/Bonus/src/Tests/TestCase.php`.
- Автозагрузка тестов настроена в `composer.json` (`autoload-dev`).

## Тестовая база данных

Для тестов используется отдельная база данных, указанная в `.env.testing` (`aptekaapitesting`). Убедитесь, что она существует и миграции применены перед запуском тестов.

---

# 7. Как внести вклад

## Общие рекомендации

1. Создайте ветку от `main` (или рабочей ветки) с décrivтельным именем, например `feature/card-export`.
2. Внесите изменения и добавьте тесты для нового функционала.
3. Убедитесь, что все тесты проходят: `vendor/bin/phpunit`.
4. Проверьте стиль кода. В проекте подключён Laravel Pint:

```bash
./vendor/bin/pint
```

5. Создайте Pull Request с описанием изменений.

## Структура изменений

При добавлении нового endpoints API рекомендуется следовать существующей архитектуре пакета `Solt\Bonus`:

1. Добавить миграцию в `packages/Solt/Bonus/src/Database/Migrations/` (при необходимости).
2. Обновить модель в `packages/Solt/Bonus/src/Models/`.
3. Добавить форму запроса с валидацией в `packages/Solt/Bonus/src/Http/Requests/`.
4. Добавить/обновить ресурс в `packages/Solt/Bonus/src/Http/Resources/`.
5. Реализовать логику в сервисе `packages/Solt/Bonus/src/Services/` и интерфейсе `packages/Solt/Bonus/src/Interfaces/`.
6. Добавить контроллер в `packages/Solt/Bonus/src/Http/Controllers/`.
7. Зарегистрировать маршрут в `packages/Solt/Bonus/src/routes/api.php`.
8. Добавить функциональные тесты в `packages/Solt/Bonus/src/Tests/Feature/`.

## Сообщения коммитов

Используйте ясные сообщения на русском или английском, например:

```
feat(bonus): добавить экспорт баланса карт
fix(api): исправить статус-код при удалении карты
```

## Лицензия

MIT. При внесении изменений вы соглашаетесь с условиями лицензии.