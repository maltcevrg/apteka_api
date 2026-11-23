#!/usr/bin/env bash
#
# Сквозная ручная проверка API бонусных карт.
#
# Создаёт юрлицо, аптеку и карту с уникальными номерами, начисляет и списывает
# бонусы, проверяет отказ при недостатке баланса, отмену операции, ошибки
# авторизации и валидации, затем удаляет тестовые данные.
#
# Использование:
#   scripts/smoke-test.sh
#
# Переменные окружения (или файл scripts/.smoke-test.env рядом со скриптом):
#   API_URL        адрес сервера, по умолчанию http://localhost:8000
#   CLIENT_ID      Client ID из `php artisan passport:client --client`
#   CLIENT_SECRET  Client Secret оттуда же
#   TOKEN          готовый токен; если задан, CLIENT_ID/CLIENT_SECRET не нужны
#   KEEP_DATA=1    не удалять тестовые данные в конце
#
# Требуются curl и python3 (для разбора JSON).

set -u

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
[ -f "$SCRIPT_DIR/.smoke-test.env" ] && . "$SCRIPT_DIR/.smoke-test.env"

API_URL="${API_URL:-http://localhost:8000}"
BASE="$API_URL/api/v1"

PASS=0
FAIL=0
BODY=""
STATUS=""

# ---------- вспомогательные функции ----------

command -v curl >/dev/null || { echo "Нужен curl"; exit 2; }
command -v python3 >/dev/null || { echo "Нужен python3"; exit 2; }

json_get() { # json_get <ключ> — достаёт поле верхнего уровня из $BODY
    python3 -c 'import json,sys
try:
    v = json.load(sys.stdin).get(sys.argv[1])
    if isinstance(v, float) and v.is_integer():
        v = int(v)
    print("" if v is None else v)
except Exception:
    print("")' "$1" <<<"$BODY"
}

request() { # request <метод> <путь> [json-тело] [без-токена]
    local method="$1" path="$2" data="${3:-}" noauth="${4:-}"
    local args=(-s -o /tmp/smoke_body.$$ -w '%{http_code}' -X "$method"
                -H "Accept: application/json" -H "Content-Type: application/json")
    [ -z "$noauth" ] && args+=(-H "Authorization: Bearer $TOKEN")
    [ -n "$data" ] && args+=(-d "$data")
    STATUS=$(curl "${args[@]}" "$BASE$path")
    BODY=$(cat /tmp/smoke_body.$$ 2>/dev/null)
    rm -f /tmp/smoke_body.$$
}

check() { # check <описание> <ожидаемый статус> [ключ] [ожидаемое значение]
    local name="$1" want="$2" key="${3:-}" value="${4:-}"
    local ok=1 got=""
    [ "$STATUS" = "$want" ] || ok=0
    if [ -n "$key" ]; then
        got=$(json_get "$key")
        [ "$got" = "$value" ] || ok=0
    fi
    if [ $ok = 1 ]; then
        PASS=$((PASS+1))
        printf '  \033[32mOK\033[0m   %s (%s%s)\n' "$name" "$STATUS" "${key:+, $key=$got}"
    else
        FAIL=$((FAIL+1))
        printf '  \033[31mFAIL\033[0m %s\n' "$name"
        printf '       ожидалось: %s%s\n' "$want" "${key:+, $key=$value}"
        printf '       получено:  %s%s\n' "$STATUS" "${key:+, $key=$got}"
        printf '       ответ:     %s\n' "${BODY:0:300}"
    fi
}

# ---------- токен ----------

echo "Сервер: $API_URL"

if [ -z "${TOKEN:-}" ]; then
    : "${CLIENT_ID:?Задайте CLIENT_ID и CLIENT_SECRET или TOKEN}"
    : "${CLIENT_SECRET:?Задайте CLIENT_SECRET}"
    BODY=$(curl -s -X POST "$API_URL/oauth/token" -H "Accept: application/json" \
        -d "grant_type=client_credentials" -d "client_id=$CLIENT_ID" -d "client_secret=$CLIENT_SECRET")
    TOKEN=$(json_get access_token)
    if [ -z "$TOKEN" ]; then
        echo "Не удалось получить токен: ${BODY:0:300}"
        exit 2
    fi
    echo "Токен получен"
fi

# ---------- тестовые данные ----------

STAMP=$(date +%s)
INN="99${STAMP: -8}"                 # 10 цифр, уникальный ИНН
SHORT_CODE="9${STAMP: -9}00"         # 12 цифр, уникальный штрих-код
PHONE="+79${STAMP: -9}"
DOC_SALE="S${STAMP: -8}"
DOC_SPEND="D${STAMP: -8}"
DOC_OVER="O${STAMP: -8}"
TODAY=$(date +%Y-%m-%d)

# Свободный номер аптеки 1..99 (номера заняты другими данными — берём первый свободный)
PH_NUM=""
for n in $(seq 99 -1 1); do
    request GET "/bonus/pharmacy/$n"
    if [ "$STATUS" != "200" ]; then PH_NUM=$n; break; fi
done
[ -n "$PH_NUM" ] || { echo "Нет свободного номера аптеки (1..99)"; exit 2; }

echo "Данные: ИНН=$INN, аптека №$PH_NUM, карта=$SHORT_CODE"
echo

# ---------- сценарий ----------

echo "1. Связь и авторизация"
request GET "/check"
check "GET /check" 200 message "API работает!"
request GET "/check" "" noauth
check "GET /check без токена" 401 message "Unauthenticated."

echo "2. Справочники"
request POST "/bonus/legal-entity" "{\"inn\":\"$INN\",\"name\":\"Тест ООО $STAMP\"}"
check "Создание юрлица" 201 inn "$INN"
LE_ID=$(json_get id)

request POST "/bonus/pharmacy" "{\"number\":$PH_NUM,\"name\":\"Тестовая аптека\",\"legal_entity\":$LE_ID}"
check "Создание аптеки" 201 number "$PH_NUM"

request GET "/bonus/legal-entity/$INN"
check "Получение юрлица по ИНН" 200 inn "$INN"

echo "3. Карта"
request POST "/bonus/card" "{\"short_code\":\"$SHORT_CODE\",\"firstname\":\"Тест\",\"name\":\"Тестов\",\"birthdate\":\"1990-01-01\",\"gender\":1,\"newsletter_agree\":1,\"status\":1,\"phone\":\"$PHONE\"}"
check "Создание карты" 201 short_code "$SHORT_CODE"

request POST "/bonus/card" "{}"
check "Создание карты без данных → ошибка валидации" 422

request GET "/bonus/card/$SHORT_CODE"
check "Получение карты" 200 short_code "$SHORT_CODE"

request GET "/bonus/balance/$SHORT_CODE"
check "Баланс новой карты" 200 balance "0"

OP="\"short_code\":\"$SHORT_CODE\",\"pharmacy_number\":$PH_NUM,\"inn\":\"$INN\",\"document_date\":\"$TODAY\",\"document_type\":\"sale\""

echo "4. Операции с бонусами"
request POST "/bonus/increase" "{$OP,\"document_number\":\"$DOC_SALE\",\"amount\":100}"
check "Начисление 100" 201
request GET "/bonus/balance/$SHORT_CODE"
check "Баланс после начисления" 200 balance "100"

request POST "/bonus/increase" "{$OP,\"document_number\":\"$DOC_SALE\",\"amount\":100}"
check "Повтор того же чека → отказ" 422

request POST "/bonus/decrease" "{$OP,\"document_number\":\"$DOC_SPEND\",\"amount\":-30}"
check "Списание 30" 201
request GET "/bonus/balance/$SHORT_CODE"
check "Баланс после списания" 200 balance "70"

request POST "/bonus/decrease" "{$OP,\"document_number\":\"$DOC_OVER\",\"amount\":-500}"
check "Списание больше баланса → отказ" 202 message "Нулевой баланс"
request GET "/bonus/balance/$SHORT_CODE"
check "Баланс не изменился" 200 balance "70"

request POST "/bonus/decrease/cancel" "{\"short_code\":\"$SHORT_CODE\",\"document_date\":\"$TODAY\",\"document_number\":\"$DOC_SPEND\",\"document_type\":\"sale\"}"
check "Отмена списания" 204
request GET "/bonus/balance/$SHORT_CODE"
check "Баланс после отмены" 200 balance "100"

request POST "/bonus/decrease/cancel" "{\"short_code\":\"$SHORT_CODE\",\"document_date\":\"$TODAY\",\"document_number\":\"$DOC_SPEND\",\"document_type\":\"sale\"}"
check "Повторная отмена → отказ" 202

request POST "/bonus/find" "{\"phone\":\"$PHONE\"}"
check "Поиск карты по телефону" 200

# ---------- очистка ----------

if [ "${KEEP_DATA:-0}" != "1" ]; then
    echo "5. Удаление тестовых данных"
    request DELETE "/bonus/card/$SHORT_CODE";      check "Удаление карты" 204
    request DELETE "/bonus/pharmacy/$PH_NUM";      check "Удаление аптеки" 204
    request DELETE "/bonus/legal-entity/$INN";     check "Удаление юрлица" 204
    echo "   (операции по карте остаются в истории card_operations — это нормально)"
fi

# ---------- итог ----------

echo
if [ $FAIL = 0 ]; then
    printf '\033[32mВсё в порядке: %d проверок пройдено\033[0m\n' "$PASS"
    exit 0
else
    printf '\033[31mОшибок: %d из %d проверок\033[0m\n' "$FAIL" $((PASS+FAIL))
    exit 1
fi
