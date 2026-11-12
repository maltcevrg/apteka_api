<#
.SYNOPSIS
    Разворачивает Apteka API на Windows с нуля.

.DESCRIPTION
    Скрипт:
      1. Ставит через winget то, чего нет: PHP 8.3, Composer, MariaDB (совместима с MySQL).
      2. Настраивает php.ini: включает нужные расширения.
      3. Создаёт базы aptekaapi и aptekaapitesting.
      4. Создаёт .env и .env.testing, ставит зависимости, генерирует ключи,
         применяет миграции, создаёт клиента API.
      5. Записывает Client ID / Secret в scripts\.smoke-test.env для scripts\smoke-test.sh.
      6. По желанию запускает сервер и открывает проверку в браузере.

    Скрипт можно запускать повторно: уже сделанные шаги пропускаются.

.PARAMETER DbPassword
    Пароль root для MariaDB. При первой установке задаётся этот пароль.
    Если MariaDB/MySQL уже стоит, укажите её настоящий пароль root.

.PARAMETER DbPort
    Порт базы данных. По умолчанию 3306.

.PARAMETER NoServe
    Не запускать php artisan serve в конце.

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File scripts\setup-windows.ps1

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File scripts\setup-windows.ps1 -DbPassword "мойпароль"
#>
[CmdletBinding()]
param(
    [string]$DbPassword = "secret",
    [int]$DbPort = 3306,
    [switch]$NoServe
)

$ErrorActionPreference = "Stop"
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8

$ProjectRoot = Split-Path -Parent $PSScriptRoot
Set-Location $ProjectRoot

# ---------------------------------------------------------------- утилиты

function Step($text) { Write-Host "`n==> $text" -ForegroundColor Cyan }
function Ok($text)   { Write-Host "    OK  $text" -ForegroundColor Green }
function Warn($text) { Write-Host "    !!  $text" -ForegroundColor Yellow }
function Fail($text) { Write-Host "`nОШИБКА: $text" -ForegroundColor Red; exit 1 }

function Refresh-Path {
    $machine = [Environment]::GetEnvironmentVariable("Path", "Machine")
    $user    = [Environment]::GetEnvironmentVariable("Path", "User")
    $env:Path = "$machine;$user;$env:Path"
}

function Has-Command($name) {
    return [bool](Get-Command $name -ErrorAction SilentlyContinue)
}

function Is-Admin {
    $id = [Security.Principal.WindowsIdentity]::GetCurrent()
    return (New-Object Security.Principal.WindowsPrincipal $id).IsInRole(
        [Security.Principal.WindowsBuiltInRole]::Administrator)
}

function Winget-Install($id, $extra = @()) {
    if (-not (Has-Command winget)) {
        Fail "Не найден winget. Обновите Windows или установите 'App Installer' из Microsoft Store, затем запустите скрипт снова."
    }
    $args = @("install", "--id", $id, "-e", "--accept-source-agreements", "--accept-package-agreements", "--silent") + $extra
    & winget @args
    if ($LASTEXITCODE -ne 0 -and $LASTEXITCODE -ne -1978335189) {
        # -1978335189 = уже установлено
        Fail "winget не смог установить $id (код $LASTEXITCODE)."
    }
    Refresh-Path
}

function Find-MysqlClient {
    foreach ($name in @("mysql", "mariadb")) {
        if (Has-Command $name) { return (Get-Command $name).Source }
    }
    $candidates = @(
        "$env:ProgramFiles\MariaDB*\bin\mysql.exe",
        "$env:ProgramFiles\MariaDB*\bin\mariadb.exe",
        "$env:ProgramFiles\MySQL\MySQL Server*\bin\mysql.exe"
    )
    foreach ($pattern in $candidates) {
        $found = Get-Item $pattern -ErrorAction SilentlyContinue | Select-Object -First 1
        if ($found) { return $found.FullName }
    }
    return $null
}

function Invoke-Sql($sql) {
    & $script:MysqlExe -h 127.0.0.1 -P $DbPort -u root "-p$DbPassword" -e $sql 2>&1
    return $LASTEXITCODE
}

# ---------------------------------------------------------------- проверки

Write-Host "Apteka API — установка на Windows" -ForegroundColor White
Write-Host "Проект: $ProjectRoot"

if (-not (Is-Admin)) {
    Warn "Скрипт запущен без прав администратора. Установка MariaDB потребует их."
    Warn "Если что-то не установится — запустите PowerShell «от имени администратора»."
}

# ---------------------------------------------------------------- PHP

Step "PHP 8.3+"
Refresh-Path
$phpOk = $false
if (Has-Command php) {
    $ver = (& php -r "echo PHP_VERSION;")
    if ([version]$ver -ge [version]"8.3.0") { $phpOk = $true; Ok "PHP $ver уже установлен" }
    else { Warn "Найден PHP $ver, нужен 8.3 или новее" }
}
if (-not $phpOk) {
    Write-Host "    Устанавливаю PHP 8.3 через winget..."
    Winget-Install "PHP.PHP.8.3"
    if (-not (Has-Command php)) { Fail "PHP установлен, но не найден в PATH. Откройте новое окно PowerShell и запустите скрипт снова." }
    Ok "PHP $(& php -r 'echo PHP_VERSION;') установлен"
}

# php.ini
Step "Настройка php.ini"
$phpDir = Split-Path -Parent (Get-Command php).Source
$phpIni = Join-Path $phpDir "php.ini"
if (-not (Test-Path $phpIni)) {
    $template = Join-Path $phpDir "php.ini-development"
    if (-not (Test-Path $template)) { $template = Join-Path $phpDir "php.ini-production" }
    if (Test-Path $template) { Copy-Item $template $phpIni; Ok "Создан php.ini из $(Split-Path -Leaf $template)" }
    else { New-Item $phpIni -ItemType File | Out-Null; Warn "Шаблон php.ini не найден, создан пустой" }
}
$ini = Get-Content $phpIni -Raw
$changed = $false
$extDir = Join-Path $phpDir "ext"
if ($ini -notmatch '(?m)^\s*extension_dir\s*=') {
    $ini += "`nextension_dir = `"$extDir`"`n"; $changed = $true
}
foreach ($ext in @("pdo_mysql", "mbstring", "openssl", "fileinfo", "curl", "sodium", "zip")) {
    if ($ini -match "(?m)^\s*;\s*extension\s*=\s*$ext\s*$") {
        $ini = $ini -replace "(?m)^\s*;\s*extension\s*=\s*$ext\s*$", "extension=$ext"; $changed = $true
    } elseif ($ini -notmatch "(?m)^\s*extension\s*=\s*(php_)?$ext(\.dll)?\s*$") {
        $ini += "extension=$ext`n"; $changed = $true
    }
}
if ($changed) { Set-Content $phpIni $ini -Encoding ASCII; Ok "Расширения включены в $phpIni" }
else { Ok "php.ini уже настроен" }

$loaded = (& php -m) -join ","
foreach ($must in @("pdo_mysql", "mbstring", "openssl", "fileinfo")) {
    if ($loaded -notmatch "(?i)\b$must\b") { Fail "Расширение PHP '$must' не загрузилось. Проверьте $phpIni" }
}

# ---------------------------------------------------------------- Composer

Step "Composer"
if (Has-Command composer) { Ok "Composer уже установлен" }
else {
    Write-Host "    Устанавливаю Composer через winget..."
    Winget-Install "Composer.Composer"
    if (-not (Has-Command composer)) { Fail "Composer установлен, но не найден в PATH. Откройте новое окно PowerShell и запустите скрипт снова." }
    Ok "Composer установлен"
}

# ---------------------------------------------------------------- MariaDB / MySQL

Step "База данных (MariaDB / MySQL)"
$script:MysqlExe = Find-MysqlClient
if ($script:MysqlExe) {
    Ok "Найден клиент: $script:MysqlExe"
} else {
    Write-Host "    Устанавливаю MariaDB через winget (пароль root: '$DbPassword')..."
    if (-not (Is-Admin)) { Fail "Для установки MariaDB запустите PowerShell от имени администратора." }
    Winget-Install "MariaDB.Server" @("--override", "/qn SERVICENAME=MariaDB PASSWORD=$DbPassword PORT=$DbPort ADDLOCAL=ALL REMOVE=HeidiSQL")
    Start-Sleep -Seconds 5
    $script:MysqlExe = Find-MysqlClient
    if (-not $script:MysqlExe) { Fail "MariaDB установлена, но клиент mysql.exe не найден." }
    Ok "MariaDB установлена"
}

# ждём, пока сервер начнёт отвечать
$connected = $false
for ($i = 0; $i -lt 15; $i++) {
    $out = Invoke-Sql "SELECT 1"
    if ($out -eq 0) { $connected = $true; break }
    Start-Sleep -Seconds 2
}
if (-not $connected) {
    Fail ("Не удалось подключиться к базе на 127.0.0.1:$DbPort под root с паролем '$DbPassword'.`n" +
          "Если база уже стояла — передайте её пароль: -DbPassword `"...`". Если сервис не запущен — запустите его (services.msc).")
}
Ok "Подключение к базе работает"

foreach ($db in @("aptekaapi", "aptekaapitesting")) {
    if ((Invoke-Sql "CREATE DATABASE IF NOT EXISTS ``$db`` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci") -ne 0) {
        Fail "Не удалось создать базу $db"
    }
}
Ok "Базы aptekaapi и aptekaapitesting готовы"

# ---------------------------------------------------------------- .env

Step "Файлы окружения"
function Write-EnvFile($path, $example, $dbName, $appEnv) {
    if (Test-Path $path) { Ok "$(Split-Path -Leaf $path) уже есть, не трогаю"; return }
    $content = Get-Content $example -Raw
    $content = $content -replace '(?m)^APP_ENV=.*',      "APP_ENV=$appEnv"
    $content = $content -replace '(?m)^APP_URL=.*',      "APP_URL=http://localhost:8000"
    $content = $content -replace '(?m)^DB_HOST=.*',      "DB_HOST=127.0.0.1"
    $content = $content -replace '(?m)^DB_PORT=.*',      "DB_PORT=$DbPort"
    $content = $content -replace '(?m)^DB_DATABASE=.*',  "DB_DATABASE=$dbName"
    $content = $content -replace '(?m)^DB_USERNAME=.*',  "DB_USERNAME=root"
    $content = $content -replace '(?m)^DB_PASSWORD=.*',  "DB_PASSWORD=$DbPassword"
    Set-Content $path $content -Encoding ASCII
    Ok "Создан $(Split-Path -Leaf $path)"
}
Write-EnvFile ".env"         ".env.example"         "aptekaapi"        "local"
Write-EnvFile ".env.testing" ".env.testing.example" "aptekaapitesting" "testing"

# ---------------------------------------------------------------- Laravel

Step "Зависимости Composer"
& composer install --no-interaction --prefer-dist
if ($LASTEXITCODE -ne 0) { Fail "composer install завершился с ошибкой" }
Ok "Зависимости установлены"

Step "Ключи приложения"
if ((Get-Content .env -Raw) -match '(?m)^APP_KEY=\s*$') {
    & php artisan key:generate --ansi; Ok "APP_KEY создан (.env)"
} else { Ok "APP_KEY уже есть (.env)" }
if ((Get-Content .env.testing -Raw) -match '(?m)^APP_KEY=\s*$') {
    & php artisan key:generate --env=testing --ansi; Ok "APP_KEY создан (.env.testing)"
} else { Ok "APP_KEY уже есть (.env.testing)" }

Step "Миграции"
& php artisan migrate --force --no-interaction
if ($LASTEXITCODE -ne 0) { Fail "Миграции не применились" }
Ok "Таблицы созданы"

Step "Ключи Passport"
if (Test-Path "storage\oauth-private.key") { Ok "Ключи уже есть" }
else { & php artisan passport:keys --no-interaction; Ok "Ключи созданы" }

Step "Клиент API"
$smokeEnv = "scripts\.smoke-test.env"
if ((Test-Path $smokeEnv) -and ((Get-Content $smokeEnv -Raw) -match '(?m)^CLIENT_SECRET=\S+')) {
    Ok "Клиент уже создан ранее, данные в $smokeEnv"
} else {
    $out = & php artisan passport:client --client --name="Local dev" --no-interaction 2>&1 | Out-String
    Write-Host $out
    $clientId     = if ($out -match '(?i)client\s*id[^\S\r\n]*[:.\s]+\s*([0-9a-f-]{1,40})') { $Matches[1].Trim() } else { "" }
    $clientSecret = if ($out -match '(?i)client\s*secret[^\S\r\n]*[:.\s]+\s*([A-Za-z0-9]{20,})') { $Matches[1].Trim() } else { "" }
    if ($clientId -and $clientSecret) {
        @(
            "API_URL=http://localhost:8000",
            "CLIENT_ID=$clientId",
            "CLIENT_SECRET=$clientSecret"
        ) | Set-Content $smokeEnv -Encoding ASCII
        Ok "Client ID и Secret сохранены в $smokeEnv"
    } else {
        Warn "Не удалось разобрать вывод passport:client. Скопируйте Client ID и Secret выше вручную в $smokeEnv"
    }
}

# ---------------------------------------------------------------- итог

Write-Host ""
Write-Host "Готово!" -ForegroundColor Green
Write-Host ""
Write-Host "  Запуск сервера:   php artisan serve"
Write-Host "  Проверка:         http://localhost:8000/api/v1/check  (нужен токен, см. docs\02-kak-zapustit.md)"
Write-Host "  Автотесты:        vendor\bin\phpunit"
Write-Host "  Smoke-тест:       bash scripts/smoke-test.sh   (из Git Bash)"
Write-Host "  Доступы к API:    $smokeEnv"
Write-Host ""

if (-not $NoServe) {
    Step "Запускаю сервер (Ctrl+C — остановить)"
    & php artisan serve
}
