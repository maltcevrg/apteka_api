#!/usr/bin/env bash
#
# Прогрев API для хостинга, где cron разрешён не чаще раза в час.
#
# Положите файл в корень проекта (рядом с artisan) и добавьте в cron
# одно задание, каждый час в минуту 0, команда без параметров:
#
#   /bin/bash www/lek-torg.ru/app/warmup.sh
#
# Скрипт сам:
#   - находит папку проекта по своему расположению;
#   - при первом запуске (и если кэши Laravel отсутствуют) находит PHP
#     и выполняет config:cache, route:cache, event:cache;
#   - 55 минут раз в 5 минут обращается к API без токена: сервер отвечает 401,
#     Laravel загружается целиком, базу не трогает — процесс PHP не засыпает;
#   - пишет журнал в warmup.log рядом с собой.
#
# Настройки при необходимости правьте прямо здесь:

SITE_URL="https://lek-torg.ru"   # адрес сайта
INTERVAL=300                     # пауза между запросами, сек
DURATION=3300                    # сколько работать за один запуск, сек (55 мин)
KEEP_LOG_LINES=500               # сколько последних строк журнала хранить

# ----------------------------------------------------------------------------

# Папка, где лежит сам скрипт = корень проекта
DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" 2>/dev/null && pwd)"
[ -n "$DIR" ] || DIR="$(pwd)"
cd "$DIR" || exit 1

LOG="$DIR/warmup.log"
URL="${SITE_URL%/}/api/v1/check"

log() { echo "$(date '+%Y-%m-%d %H:%M:%S') $*" >> "$LOG"; }

# Подрезать журнал, чтобы не рос бесконечно
if [ -f "$LOG" ] && [ "$(wc -l < "$LOG")" -gt "$KEEP_LOG_LINES" ]; then
    tail -n "$KEEP_LOG_LINES" "$LOG" > "$LOG.tmp" && mv "$LOG.tmp" "$LOG"
fi

# Не запускать второй экземпляр
LOCK="$DIR/warmup.lock"
if [ -e "$LOCK" ] && kill -0 "$(cat "$LOCK" 2>/dev/null)" 2>/dev/null; then
    exit 0
fi
echo $$ > "$LOCK"
trap 'rm -f "$LOCK"' EXIT

log "старт: папка=$DIR пользователь=$(whoami 2>/dev/null) url=$URL"

# ---------- поиск PHP 8.3+ ----------
find_php() {
    local c v
    for c in php php8.3 php83 php8.4 php84 php8.5 \
             /usr/bin/php8.3 /usr/bin/php8.4 /usr/local/bin/php \
             /opt/php83/bin/php /opt/php84/bin/php /opt/php/83/bin/php /opt/php/84/bin/php \
             /opt/alt/php83/usr/bin/php /opt/alt/php84/usr/bin/php \
             /usr/local/php83/bin/php /usr/local/php84/bin/php; do
        command -v "$c" >/dev/null 2>&1 || [ -x "$c" ] || continue
        v=$("$c" -r 'echo PHP_MAJOR_VERSION*100+PHP_MINOR_VERSION;' 2>/dev/null) || continue
        if [ "${v:-0}" -ge 803 ]; then echo "$c"; return 0; fi
    done
    return 1
}

# ---------- кэши Laravel (только если их нет) ----------
if [ -f artisan ]; then
    if [ ! -f bootstrap/cache/config.php ] || [ ! -f bootstrap/cache/routes-v7.php ]; then
        PHP=$(find_php)
        if [ -n "$PHP" ]; then
            log "кэши Laravel отсутствуют, собираю через $PHP ($("$PHP" -r 'echo PHP_VERSION;'))"
            for cmd in config:cache route:cache event:cache; do
                OUT=$("$PHP" artisan "$cmd" --no-ansi 2>&1 | tr '\n' ' ')
                log "artisan $cmd: $OUT"
            done
        else
            log "ВНИМАНИЕ: PHP 8.3+ не найден, кэши Laravel не собраны. Впишите путь к PHP в find_php()"
        fi
    fi
else
    log "ВНИМАНИЕ: рядом со скриптом нет файла artisan — скрипт лежит не в корне проекта"
fi

# ---------- прогрев ----------
fetch() {
    if command -v curl >/dev/null 2>&1; then
        curl -s -o /dev/null -w '%{http_code} %{time_total}s' --max-time 20 "$URL"
    elif command -v wget >/dev/null 2>&1; then
        wget -q -O /dev/null --timeout=20 --server-response "$URL" 2>&1 | awk '/HTTP\//{c=$2} END{printf "%s", c}'
    else
        echo "нет curl и wget"
    fi
}

START=$(date +%s)
while :; do
    log "запрос: $(fetch)"
    NOW=$(date +%s)
    [ $((NOW - START + INTERVAL)) -ge "$DURATION" ] && break
    sleep "$INTERVAL"
done
log "конец"
