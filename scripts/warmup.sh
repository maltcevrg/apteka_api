#!/usr/bin/env bash
#
# Прогрев API, когда cron на хостинге разрешён не чаще раза в час.
#
# Запускается cron'ом раз в час и сам делает запросы к API каждые
# INTERVAL секунд в течение DURATION секунд (по умолчанию каждые 5 минут
# в течение 55 минут). Процесс PHP на хостинге не успевает «заснуть».
#
# Строка в cron (в начале каждого часа):
#   0 * * * * /bin/bash /полный/путь/к/проекту/scripts/warmup.sh https://lek-torg.ru
#
# Параметры:
#   $1        адрес сайта (обязательно), например https://lek-torg.ru
#   INTERVAL  пауза между запросами, сек (по умолчанию 300)
#   DURATION  сколько всего работать, сек (по умолчанию 3300 = 55 минут,
#             чтобы закончить до следующего запуска)
#   LOG       файл журнала (по умолчанию не пишется)
#
# Запрос идёт без токена: сервер отвечает 401, но Laravel при этом
# загружается целиком, а в базу не ходит.

URL="${1:?Укажите адрес сайта, например: warmup.sh https://lek-torg.ru}"
URL="${URL%/}/api/v1/check"
INTERVAL="${INTERVAL:-300}"
DURATION="${DURATION:-3300}"
LOG="${LOG:-}"

# Не запускать второй экземпляр, если предыдущий ещё работает
LOCK="/tmp/apteka-warmup-$(echo "$URL" | md5sum | cut -c1-8).lock"
if [ -e "$LOCK" ] && kill -0 "$(cat "$LOCK" 2>/dev/null)" 2>/dev/null; then
    exit 0
fi
echo $$ > "$LOCK"
trap 'rm -f "$LOCK"' EXIT

fetch() {
    if command -v curl >/dev/null 2>&1; then
        curl -s -o /dev/null -w '%{http_code} %{time_total}' --max-time 20 "$URL"
    elif command -v wget >/dev/null 2>&1; then
        local t0 t1
        t0=$(date +%s.%N)
        wget -q -O /dev/null --timeout=20 --server-response "$URL" 2>&1 | awk '/HTTP\//{c=$2} END{printf "%s", c}'
        t1=$(date +%s.%N)
        printf ' %.3f' "$(echo "$t1 - $t0" | bc 2>/dev/null || echo 0)"
    else
        echo "000 0"
    fi
}

START=$(date +%s)
while :; do
    RESULT=$(fetch)
    [ -n "$LOG" ] && echo "$(date '+%Y-%m-%d %H:%M:%S') $RESULT" >> "$LOG"
    NOW=$(date +%s)
    [ $((NOW - START + INTERVAL)) -ge "$DURATION" ] && break
    sleep "$INTERVAL"
done
