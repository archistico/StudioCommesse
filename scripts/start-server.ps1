param(
    [ValidateSet("dev", "fast")]
    [string]$Mode = "dev",
    [ValidateRange(1, 65535)]
    [int]$Port = 8000
)

$ErrorActionPreference = "Stop"
Set-Location (Split-Path -Parent $PSScriptRoot)

$hadAppEnv = Test-Path Env:APP_ENV
$previousAppEnv = $env:APP_ENV
$hadAppDebug = Test-Path Env:APP_DEBUG
$previousAppDebug = $env:APP_DEBUG

try {
    if ($Mode -eq "fast") {
        $env:APP_ENV = "prod"
        $env:APP_DEBUG = "0"
        php bin/console cache:clear --env=prod --no-debug
        if ($LASTEXITCODE -ne 0) {
            throw "Impossibile preparare la cache Symfony di produzione."
        }

        Write-Host "Avvio rapido: APP_ENV=prod, APP_DEBUG=0." -ForegroundColor Green
    } else {
        $env:APP_ENV = "dev"
        $env:APP_DEBUG = "1"
        Write-Host "Avvio sviluppo: APP_ENV=dev, APP_DEBUG=1." -ForegroundColor Cyan
    }

    $opcacheCli = & php -r "echo ini_get('opcache.enable_cli') ?: '0';"
    $xdebugLoaded = & php -r "echo extension_loaded('xdebug') ? '1' : '0';"
    Write-Host ("OPcache CLI: {0} · Xdebug: {1}" -f ($(if ($opcacheCli -eq '1') {'attivo'} else {'disattivo'}), $(if ($xdebugLoaded -eq '1') {'attivo'} else {'non caricato'})))

    if (Get-Command symfony -ErrorAction SilentlyContinue) {
        symfony server:start --no-tls --port=$Port
    } else {
        Write-Host "Symfony CLI non disponibile: uso il server PHP integrato, adatto solo allo sviluppo e a singolo processo." -ForegroundColor Yellow
        Write-Host "Il processo server richiede OPcache CLI; validate_timestamps resta attivo per rilevare le modifiche." -ForegroundColor Cyan
        php -d opcache.enable_cli=1 -d upload_max_filesize=10M -d post_max_size=12M -S ("127.0.0.1:{0}" -f $Port) -t public public/router.php
    }
} finally {
    if ($hadAppEnv) {
        $env:APP_ENV = $previousAppEnv
    } else {
        Remove-Item Env:APP_ENV -ErrorAction SilentlyContinue
    }

    if ($hadAppDebug) {
        $env:APP_DEBUG = $previousAppDebug
    } else {
        Remove-Item Env:APP_DEBUG -ErrorAction SilentlyContinue
    }
}
