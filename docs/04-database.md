# База данных

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
