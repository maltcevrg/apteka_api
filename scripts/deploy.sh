#!/usr/bin/env bash
#
# Выкладка/обновление Apteka API на боевом сервере.
#
# Запускать из корня проекта после `git pull` или загрузки новых файлов:
#   bash scripts/deploy.sh
#
# Делает: зависимости без dev-пакетов, миграции, пересборку кэшей Laravel,
# сброс OPcache (если доступен) и проверку, что приложение отвечает.
# Все шаги безопасны для повторного запуска.

set -euo pipefail
cd "$(dirname "$0")/.."

PHP="${PHP:-php}"
COMPOSER="${COMPOSER:-composer}"

step() { printf '\n==> %s\n' "$*"; }

step "Проверка окружения"
[ -f .env ] || { echo "Нет файла .env — создайте его из .env.example"; exit 1; }
grep -q '^APP_KEY=.\+' .env || { echo "В .env пустой APP_KEY — выполните: php artisan key:generate"; exit 1; }
if grep -q '^APP_DEBUG=true' .env; then
    echo "ВНИМАНИЕ: в .env стоит APP_DEBUG=true. На боевом сервере должно быть false."
fi
"$PHP" -v | head -1

step "Режим обслуживания"
"$PHP" artisan down --retry=10 || true
trap '"$PHP" artisan up >/dev/null 2>&1 || true' EXIT

step "Зависимости"
"$COMPOSER" install --no-dev --optimize-autoloader --no-interaction --prefer-dist

step "Миграции"
"$PHP" artisan migrate --force --no-interaction

step "Ключи Passport"
if [ ! -f storage/oauth-private.key ]; then
    "$PHP" artisan passport:keys --no-interaction
    echo "Ключи созданы впервые. Не забудьте создать клиента: php artisan passport:client --client"
else
    echo "уже есть"
fi

step "Кэши Laravel"
"$PHP" artisan optimize:clear
"$PHP" artisan config:cache
"$PHP" artisan route:cache
"$PHP" artisan event:cache
"$PHP" artisan view:clear

step "Права на каталоги"
chmod -R ug+rwX storage bootstrap/cache 2>/dev/null || true

step "Выход из режима обслуживания"
"$PHP" artisan up
trap - EXIT

step "Проверка"
APP_URL=$(grep '^APP_URL=' .env | cut -d= -f2- | tr -d '"' | tr -d "'")
if [ -n "$APP_URL" ] && command -v curl >/dev/null; then
    # Без токена API отвечает 401 — значит, Laravel поднялся и маршруты работают
    CODE=$(curl -s -o /dev/null -w '%{http_code}' "$APP_URL/api/v1/check" || echo "000")
    if [ "$CODE" = "401" ]; then
        echo "GET $APP_URL/api/v1/check → 401 (без токена), приложение отвечает"
    else
        echo "GET $APP_URL/api/v1/check → $CODE. Ожидался 401. Проверьте веб-сервер и APP_URL в .env"
    fi
else
    echo "Проверьте вручную: curl -i $APP_URL/api/v1/check  (ожидается 401)"
fi

cat <<MSG

Готово. Напоминание:
  - После любого изменения .env снова: php artisan config:cache
  - Прогрев против холодного старта:
      cron каждые 5 минут:  */5 * * * * curl -s -o /dev/null ${APP_URL:-https://lek-torg.ru}/api/v1/check
      cron только раз в час: скопировать scripts/warmup.sh в корень проекта, задание 0 * * * * /bin/bash www/lek-torg.ru/app/warmup.sh
    Подробнее: docs/02-installation.md, раздел «Медленный первый запрос»
MSG
