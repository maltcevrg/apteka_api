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

Для тестов используется отдельная база данных, указанная в `.env.testing` (`aptekaapitesting`). Убедитесь, что она существует и миграции применены перед запуском тестов.
