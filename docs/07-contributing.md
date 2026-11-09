# Как внести вклад

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
