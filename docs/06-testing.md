# Тестирование

## Инструмент

Тесты написаны на **PHPUnit 12**. Конфигурация — `phpunit.xml` в корне репозитория.

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
│   ├── Api/
│   │   └── ErrorFormatTest.php
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

Для тестов используется отдельная база данных, указанная в `.env.testing` (`aptekaapitesting`). Файл `.env.testing` не хранится в репозитории — создайте его из `.env.testing.example` и укажите доступ к базе. Убедитесь, что она существует и миграции применены перед запуском тестов.

## Непрерывная интеграция

При каждом pull request и пуше в `main` тесты автоматически запускаются в GitHub Actions (`.github/workflows/tests.yml`) на PHP 8.3 с MySQL 8.0.

## Ручная сквозная проверка (smoke-тест)

Скрипт `scripts/smoke-test.sh` прогоняет полный сценарий на запущенном сервере: получает токен, создаёт тестовые юрлицо, аптеку и карту, начисляет и списывает бонусы, проверяет отказ при недостатке баланса, отмену операции, ошибки авторизации и валидации, затем удаляет тестовые данные.

```bash
cp scripts/.smoke-test.env.example scripts/.smoke-test.env   # укажите CLIENT_ID и CLIENT_SECRET
scripts/smoke-test.sh
```

Вместо файла можно передать переменные окружения: `API_URL`, `CLIENT_ID`, `CLIENT_SECRET` или готовый `TOKEN`. `KEEP_DATA=1` оставляет тестовые данные в базе. Нужны `curl` и `python3`.

Скрипт можно запускать и против боевого сервера: он использует уникальные ИНН, штрих-код и телефон, а в конце помечает созданные записи удалёнными. Операции начисления и списания остаются в истории `card_operations`.
