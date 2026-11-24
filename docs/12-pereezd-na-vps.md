# Переезд на VPS

Пошаговая инструкция: перенести Apteka API с виртуального хостинга на свой VPS так, чтобы кассы ничего не заметили. Готовые конфиги лежат в папке `deploy/`.

**Когда переезжать.** Если на виртуальном хостинге даже после переключения на PHP-FPM (см. [02-installation.md](02-installation.md)) пустой PHP-скрипт отвечает от 70 мс до 2,5 с — перегружен сам узел, и настройками это не лечится. На VPS процесс PHP живёт постоянно, соседей нет — ответ API стабильно 30–50 мс.

**Что понадобится.** VPS с Ubuntu 24.04, 1 CPU, 1–2 ГБ памяти, 10+ ГБ диска (для 40 аптек этого хватает с большим запасом — см. [08-hosting-report.md](08-hosting-report.md)). Доступ по SSH под root. Около двух часов времени. Переезд делается при работающем старом сервере, переключение — в конце, одной правкой DNS.

## Три вещи, которые нельзя потерять

1. **Ключи Passport** — файлы `storage/oauth-private.key` и `storage/oauth-public.key` на старом сервере. Если их не перенести, а сгенерировать новые, **все выданные кассам токены перестанут работать** и придётся перенастраивать каждую кассу. Переносим как есть.
2. **`APP_KEY` из `.env`** старого сервера. Переносим как есть.
3. **База данных** — дамп делаем в самом конце, перед переключением, чтобы не потерять операции за время переезда.

## Шаг 1. Подготовка VPS

Подключиться по SSH под root и выполнить:

```bash
apt update && apt upgrade -y
apt install -y nginx mysql-server php8.3-fpm php8.3-mysql php8.3-mbstring php8.3-xml \
    php8.3-curl php8.3-zip php8.3-bcmath php8.3-intl git unzip curl ufw certbot python3-certbot-nginx

# Composer
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Файрвол: только SSH и веб
ufw allow OpenSSH
ufw allow 'Nginx Full'
ufw --force enable

# Своп на случай нехватки памяти (для 1 ГБ VPS обязательно)
fallocate -l 1G /swapfile && chmod 600 /swapfile && mkswap /swapfile && swapon /swapfile
echo '/swapfile none swap sw 0 0' >> /etc/fstab

# Часовой пояс
timedatectl set-timezone Europe/Moscow
```

Проверка: `php -v` показывает 8.3, `nginx -v`, `mysql --version`.

## Шаг 2. База данных

```bash
mysql
```

В консоли MySQL (замените пароль на свой, длинный):

```sql
CREATE DATABASE aptekaapi CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'apteka'@'localhost' IDENTIFIED BY 'СЛОЖНЫЙ_ПАРОЛЬ';
GRANT ALL PRIVILEGES ON aptekaapi.* TO 'apteka'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

Настройки MySQL под маленький VPS:

```bash
cp /var/www/apteka_api/deploy/mysql-apteka.cnf /etc/mysql/mysql.conf.d/99-apteka.cnf   # после шага 3
systemctl restart mysql
```

## Шаг 3. Код проекта

```bash
mkdir -p /var/www && cd /var/www
git clone https://github.com/maltcevrg/apteka_api.git apteka_api
cd apteka_api
composer install --no-dev --optimize-autoloader --no-interaction
```

Если репозиторий приватный, а на VPS нет доступа к GitHub — загрузите файлы проекта архивом через `scp` и распакуйте в `/var/www/apteka_api`. Папку `vendor` можно не копировать, её создаст `composer install`.

## Шаг 4. Перенос секретов со старого сервера

На старом хостинге через файловый менеджер ISPmanager скачайте три файла:

- `~/www/lek-torg.ru/app/.env`
- `~/www/lek-torg.ru/app/storage/oauth-private.key`
- `~/www/lek-torg.ru/app/storage/oauth-public.key`

(в файловом менеджере скрытые файлы показываются через настройку «показывать скрытые»; ключи можно скачать и как папку `storage` архивом.)

Загрузите их на VPS (`scp`, WinSCP или вставкой через `nano`) в `/var/www/apteka_api/` и `/var/www/apteka_api/storage/` соответственно.

Откройте `.env` на VPS и поправьте только эти строки — остальное оставить как было, **особенно `APP_KEY`**:

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://lek-torg.ru
LOG_CHANNEL=daily

DB_HOST=127.0.0.1
DB_DATABASE=aptekaapi
DB_USERNAME=apteka
DB_PASSWORD=СЛОЖНЫЙ_ПАРОЛЬ
```

Права:

```bash
chown -R www-data:www-data /var/www/apteka_api
chmod 600 /var/www/apteka_api/storage/oauth-*.key
chmod -R ug+rwX /var/www/apteka_api/storage /var/www/apteka_api/bootstrap/cache
```

## Шаг 5. PHP-FPM и OPcache

Это сердце переезда — то, чего не было на хостинге:

```bash
cd /var/www/apteka_api
cp deploy/php-fpm-apteka.conf /etc/php/8.3/fpm/pool.d/www.conf
cp deploy/php-opcache.ini /etc/php/8.3/fpm/conf.d/99-apteka-opcache.ini
systemctl restart php8.3-fpm
systemctl status php8.3-fpm --no-pager | head -5
```

`pm = static`, 4 процесса: они запускаются один раз и живут, пока работает сервер. `opcache.validate_timestamps=0`: код компилируется один раз. Обратная сторона — после обновления кода нужно выполнить `systemctl reload php8.3-fpm` (это делает `scripts/deploy.sh`, см. шаг 9).

## Шаг 6. Nginx

```bash
cp /var/www/apteka_api/deploy/nginx-lek-torg.ru.conf /etc/nginx/sites-available/lek-torg.ru
ln -s /etc/nginx/sites-available/lek-torg.ru /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default
nginx -t && systemctl reload nginx
```

## Шаг 7. Проверка до переключения DNS

Пока домен смотрит на старый сервер, проверяем VPS по IP, подставляя заголовок Host:

```bash
cd /var/www/apteka_api
sudo -u www-data php artisan migrate --force      # создаст таблицы в пустой базе
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan event:cache

curl -s -o /dev/null -w '%{http_code} %{time_total}s\n' -H 'Host: lek-torg.ru' http://127.0.0.1/api/v1/check
```

Ожидается `401 0.0xx s` — приложение работает, без токена закрыто, время — десятки миллисекунд. Повторите 10 раз: разброса в секунды быть не должно.

С вашего компьютера можно проверить и с токеном, добавив в `hosts` строку `IP_VPS lek-torg.ru` (на время теста), — тогда Postman и даже 1С будут ходить на новый сервер, не трогая DNS.

## Шаг 8. Перенос базы и переключение

Это единственный шаг с простоем — 5–10 минут. Лучше вечером, когда аптеки закрыты.

1. **На старом хостинге** — остановить приём операций. Проще всего в ISPmanager временно выключить сайт `lek-torg.ru` либо переименовать `app/public/index.php` в `index.php.off`. Кассы получат ошибку связи и продолжат работать без бонусов — это предусмотрено.
2. **Снять дамп** базы: в ISPmanager → «Базы данных» → выбрать базу → «Скачать дамп» (или через phpMyAdmin → Экспорт → SQL). Скачать файл `dump.sql`.
3. **Загрузить на VPS** и залить:
   ```bash
   mysql aptekaapi < dump.sql
   cd /var/www/apteka_api && sudo -u www-data php artisan migrate --force   # применит миграции, которых не было на старом сервере
   ```
4. **Проверить данные**: `mysql aptekaapi -e "SELECT COUNT(*) FROM cards; SELECT COUNT(*) FROM card_operations;"` — числа должны совпасть со старым сервером.
5. **DNS**: в панели регистратора домена сменить A-запись `lek-torg.ru` (и `www`) на IP VPS. TTL обычно 1 час; пока DNS обновляется, часть касс может ходить на старый (выключенный) сервер — поэтому выключать его на шаге 1 важно, иначе операции разъедутся по двум базам.
6. **HTTPS**, как только `ping lek-torg.ru` с вашего компьютера показывает IP VPS:
   ```bash
   certbot --nginx -d lek-torg.ru -d www.lek-torg.ru --redirect -m ваш@email -n --agree-tos
   ```
   Certbot сам допишет HTTPS-блок в конфиг nginx и настроит автопродление.
7. **Контроль**: запрос баланса из Postman и с кассы — `200`, десятки миллисекунд. Токены касс работают без перевыпуска, потому что ключи Passport перенесены.

## Шаг 9. После переезда

- **Обновление кода** в будущем: `cd /var/www/apteka_api && git pull && sudo -u www-data bash scripts/deploy.sh && systemctl reload php8.3-fpm`.
- **Бэкап базы** раз в сутки, cron root'а (`crontab -e`):
  ```
  30 3 * * * mysqldump aptekaapi | gzip > /var/backups/aptekaapi-$(date +\%F).sql.gz && find /var/backups -name 'aptekaapi-*.sql.gz' -mtime +14 -delete
  ```
- **Старый хостинг** не отключайте неделю: пусть лежит как запасная копия. Потом — удалить сайт и тестовые файлы `ping.php`, `oc.php`.
- **Мониторинг** (по желанию): UptimeRobot на `https://lek-torg.ru/api/v1/check` с ключевым словом `Unauthenticated` — уведомит, если сервер упадёт.

## Если что-то пошло не так

| Симптом | Что проверить |
|:--|:--|
| `502 Bad Gateway` | `systemctl status php8.3-fpm`; путь к сокету в nginx и в pool-конфиге совпадает |
| `500` и пусто | `tail -50 /var/www/apteka_api/storage/logs/laravel-*.log`; права на `storage` |
| `401` с валидным токеном касс | Ключи Passport не перенесены или перенесены не те; сравните `md5sum storage/oauth-public.key` на старом и новом |
| Ошибка подключения к базе | `DB_*` в `.env`; после правки `.env` — снова `php artisan config:cache` |
| Касса ходит на старый сервер | DNS ещё не обновился: `nslookup lek-torg.ru`; подождать TTL |

Откат: вернуть A-запись на старый IP и включить сайт в ISPmanager. Операции, проведённые на VPS за это время, нужно будет перенести дампом обратно.
