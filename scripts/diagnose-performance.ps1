param(
    [string]$BaseUrl = "http://127.0.0.1:8000",
    [string]$Username = "",
    [ValidateRange(1, 20)]
    [int]$Iterations = 3,
    [switch]$SkipHttp
)

$ErrorActionPreference = "Stop"
Set-Location (Split-Path -Parent $PSScriptRoot)

function Get-PhpValue([string]$Expression) {
    $value = & php -r ("echo {0};" -f $Expression)
    if ($LASTEXITCODE -ne 0) {
        throw "Impossibile leggere la configurazione PHP."
    }
    return [string]$value
}

function Get-Median([double[]]$Values) {
    $ordered = @($Values | Sort-Object)
    $count = $ordered.Count
    if ($count -eq 0) { return 0.0 }
    if ($count % 2 -eq 1) { return [double]$ordered[[int][Math]::Floor($count / 2)] }
    return ([double]$ordered[$count / 2 - 1] + [double]$ordered[$count / 2]) / 2.0
}

Write-Host "=== Ambiente PHP/Symfony ===" -ForegroundColor Cyan
Write-Host ("PHP: {0}" -f (Get-PhpValue "PHP_VERSION"))
Write-Host ("SAPI: {0}" -f (Get-PhpValue "PHP_SAPI"))
Write-Host ("OPcache caricato: {0}" -f (Get-PhpValue "extension_loaded('Zend OPcache') ? 'sì' : 'no'"))
Write-Host ("opcache.enable_cli: {0}" -f (Get-PhpValue "ini_get('opcache.enable_cli') ?: '0'"))
Write-Host ("Xdebug caricato: {0}" -f (Get-PhpValue "extension_loaded('xdebug') ? 'sì' : 'no'"))
Write-Host ("php.ini: {0}" -f (Get-PhpValue "php_ini_loaded_file() ?: 'nessuno'"))

$dbPath = Join-Path (Get-Location) "var/studio_commesse.db"
if (Test-Path $dbPath) {
    $database = Get-Item $dbPath
    Write-Host ("Database SQLite: {0:N2} MB" -f ($database.Length / 1MB))

    $counts = & php -r @'
$db = new PDO('sqlite:var/studio_commesse.db');
foreach (['app_user','client','project','activity','time_entry','expense','payment','audit_log'] as $table) {
    try { echo $table.'='.$db->query('SELECT COUNT(*) FROM '.$table)->fetchColumn().PHP_EOL; }
    catch (Throwable) { echo $table.'=n/d'.PHP_EOL; }
}
'@
    if ($LASTEXITCODE -eq 0) {
        $counts | ForEach-Object { Write-Host ("  {0}" -f $_) }
    }
}

Write-Host "`n=== Bootstrap Symfony ===" -ForegroundColor Cyan
foreach ($environment in @('dev', 'prod')) {
    $samples = @()
    for ($i = 0; $i -lt $Iterations; $i++) {
        $elapsed = Measure-Command { & php bin/console about ("--env={0}" -f $environment) --no-debug *> $null }
        if ($LASTEXITCODE -ne 0) {
            throw "Bootstrap Symfony fallito per l'ambiente $environment."
        }
        $samples += $elapsed.TotalMilliseconds
    }
    Write-Host ("{0}: mediana {1:N0} ms · media {2:N0} ms" -f $environment, (Get-Median $samples), (($samples | Measure-Object -Average).Average))
}

if ($SkipHttp) {
    exit 0
}

Write-Host "`n=== Richieste HTTP autenticate ===" -ForegroundColor Cyan
try {
    $session = [Microsoft.PowerShell.Commands.WebRequestSession]::new()
    $loginPage = Invoke-WebRequest -Uri ("{0}/login" -f $BaseUrl.TrimEnd('/')) -WebSession $session
} catch {
    Write-Warning "Server non raggiungibile su $BaseUrl. Avvialo con .\scripts\start-server.ps1 e rilancia la diagnosi."
    exit 0
}

if ([string]::IsNullOrWhiteSpace($Username)) {
    $Username = Read-Host "Nome utente per il benchmark"
}
$password = Read-Host "Password" -AsSecureString
$plainPassword = [System.Net.NetworkCredential]::new('', $password).Password

$csrfMatch = [regex]::Match($loginPage.Content, 'name="_csrf_token"\s+value="([^"]+)"')
if (-not $csrfMatch.Success) {
    throw "Token CSRF di login non trovato."
}

$loginResponse = Invoke-WebRequest -Uri ("{0}/login" -f $BaseUrl.TrimEnd('/')) -Method Post -WebSession $session -Body @{
    _username = $Username
    _password = $plainPassword
    _csrf_token = $csrfMatch.Groups[1].Value
}
$plainPassword = $null

$finalLoginPath = $null
if ($loginResponse.BaseResponse.RequestMessage -and $loginResponse.BaseResponse.RequestMessage.RequestUri) {
    $finalLoginPath = $loginResponse.BaseResponse.RequestMessage.RequestUri.AbsolutePath
} elseif ($loginResponse.BaseResponse.ResponseUri) {
    $finalLoginPath = $loginResponse.BaseResponse.ResponseUri.AbsolutePath
}
if ($finalLoginPath -eq '/login') {
    throw "Accesso non riuscito: verificare le credenziali."
}

$routes = @('/dashboard', '/commesse', '/attivita', '/ore', '/controllo', '/economia', '/commesse/2')
foreach ($route in $routes) {
    $samples = @()
    $statusCode = 0
    for ($i = 0; $i -lt $Iterations; $i++) {
        $watch = [System.Diagnostics.Stopwatch]::StartNew()
        try {
            $response = Invoke-WebRequest -Uri ("{0}{1}" -f $BaseUrl.TrimEnd('/'), $route) -WebSession $session
            $statusCode = [int]$response.StatusCode
        } catch {
            if ($_.Exception.Response) {
                $statusCode = [int]$_.Exception.Response.StatusCode
            } else {
                throw
            }
        } finally {
            $watch.Stop()
        }
        $samples += $watch.Elapsed.TotalMilliseconds
    }

    Write-Host ("{0,-18} HTTP {1} · mediana {2:N0} ms · media {3:N0} ms" -f $route, $statusCode, (Get-Median $samples), (($samples | Measure-Object -Average).Average))
}

Write-Host "`nInterpretazione rapida:" -ForegroundColor Cyan
Write-Host "- tutte le rotte lente: ambiente PHP/dev, antivirus, Xdebug oppure OPcache;"
Write-Host "- solo /ore lenta: filtri, paginazione o volume registrazioni;"
Write-Host "- solo /controllo lenta: aggregazioni trasversali, filtri o periodo selezionato;"
Write-Host "- solo /economia lenta: aggregazioni economiche o volume dati;"
Write-Host "- solo /commesse/2 lenta: attività/ore della singola commessa;"
Write-Host "- prima richiesta lenta e successive rapide: riscaldamento cache/autoload."
