param(
    [ValidateSet("dev", "test")]
    [string]$Environment = "dev"
)

$ErrorActionPreference = "Stop"
Set-Location (Split-Path -Parent $PSScriptRoot)

$hadAppEnv = Test-Path Env:APP_ENV
$previousAppEnv = $env:APP_ENV
$hadAppDebug = Test-Path Env:APP_DEBUG
$previousAppDebug = $env:APP_DEBUG

try {
    $env:APP_ENV = $Environment
    if ($Environment -eq "dev") {
        $env:APP_DEBUG = "1"
    } else {
        $env:APP_DEBUG = "0"
    }

    $environmentOption = "--env=$Environment"

    php bin/console doctrine:migrations:migrate --no-interaction $environmentOption
    if ($LASTEXITCODE -ne 0) {
        throw "Migrazioni non riuscite."
    }

    php bin/console app:fixtures:load $environmentOption
    if ($LASTEXITCODE -ne 0) {
        throw "Caricamento fixtures non riuscito."
    }

    Write-Host ("Fixtures dimostrative caricate correttamente in ambiente {0}." -f $Environment) -ForegroundColor Green
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
