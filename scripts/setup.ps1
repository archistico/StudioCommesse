param(
    [string]$PartnerUsername = "",
    [string]$PartnerDisplayName = "",
    [switch]$SkipPartnerBootstrap
)

$ErrorActionPreference = "Stop"
Set-Location (Split-Path -Parent $PSScriptRoot)

function Require-Command([string]$Name) {
    if (-not (Get-Command $Name -ErrorAction SilentlyContinue)) {
        throw "Comando richiesto non trovato: $Name"
    }
}

function Invoke-Checked {
    param(
        [Parameter(Mandatory = $true)]
        [string]$Command,
        [Parameter(Mandatory = $true)]
        [string[]]$Arguments
    )

    & $Command @Arguments
    $exitCode = $LASTEXITCODE
    if ($exitCode -ne 0) {
        $displayCommand = ("{0} {1}" -f $Command, ($Arguments -join " ")).Trim()
        throw ("Comando fallito con codice {0}: {1}" -f $exitCode, $displayCommand)
    }
}

function Assert-PowerShellScriptSyntax {
    $scriptDirectory = Join-Path (Get-Location) "scripts"
    $scriptFiles = Get-ChildItem -Path $scriptDirectory -Filter "*.ps1" -File

    foreach ($scriptFile in $scriptFiles) {
        $tokens = $null
        $parseErrors = $null
        [System.Management.Automation.Language.Parser]::ParseFile(
            $scriptFile.FullName,
            [ref]$tokens,
            [ref]$parseErrors
        ) | Out-Null

        if (@($parseErrors).Count -gt 0) {
            $messages = $parseErrors | ForEach-Object { $_.Message }
            throw ("Errore di sintassi PowerShell in {0}: {1}" -f $scriptFile.Name, ($messages -join " | "))
        }
    }
}

Assert-PowerShellScriptSyntax

Require-Command "php"
Require-Command "composer"
Require-Command "node"
Require-Command "npm"

$phpVersion = & php -r "echo PHP_VERSION_ID;"
if ($LASTEXITCODE -ne 0) {
    throw "Impossibile rilevare la versione di PHP."
}
if ([int]$phpVersion -lt 80400) {
    throw "È richiesto PHP 8.4 o successivo."
}

$nodeMajor = & node -p "Number(process.versions.node.split('.')[0])"
if ($LASTEXITCODE -ne 0) {
    throw "Impossibile rilevare la versione di Node.js."
}
if ([int]$nodeMajor -lt 20) {
    throw "È richiesto Node.js 20 o successivo."
}

$requiredExtensions = @("ctype", "fileinfo", "iconv", "mbstring", "pdo", "pdo_sqlite")
foreach ($extension in $requiredExtensions) {
    & php -r "exit(extension_loaded('$extension') ? 0 : 1);"
    if ($LASTEXITCODE -ne 0) {
        throw "Estensione PHP richiesta non disponibile: $extension"
    }
}

if (-not (Test-Path ".env.local")) {
    $secret = & php -r "echo bin2hex(random_bytes(32));"
    if ($LASTEXITCODE -ne 0) {
        throw "Impossibile generare APP_SECRET."
    }

    $content = (Get-Content ".env.local.dist" -Raw).Replace("replace-with-a-long-random-secret", $secret)
    $utf8NoBom = New-Object System.Text.UTF8Encoding($false)
    [System.IO.File]::WriteAllText((Join-Path (Get-Location) ".env.local"), $content, $utf8NoBom)
    Write-Host "Creato .env.local con APP_SECRET casuale." -ForegroundColor Green
}

if (Test-Path "composer.lock") {
    Invoke-Checked -Command "composer" -Arguments @("install", "--no-interaction", "--prefer-dist", "--no-scripts")
} else {
    Write-Host "composer.lock assente: risoluzione iniziale delle dipendenze e creazione del lock file." -ForegroundColor Yellow
    Invoke-Checked -Command "composer" -Arguments @("update", "--no-interaction", "--prefer-dist", "--no-scripts")
}
Invoke-Checked -Command "composer" -Arguments @("check-platform-reqs")

if (Test-Path "package-lock.json") {
    Invoke-Checked -Command "npm" -Arguments @("ci")
} else {
    Write-Host "package-lock.json assente: installazione iniziale e creazione del lock file." -ForegroundColor Yellow
    Invoke-Checked -Command "npm" -Arguments @("install", "--package-lock")
}
Invoke-Checked -Command "npm" -Arguments @("run", "build")
Invoke-Checked -Command "npm" -Arguments @("test")

# Eseguiamo i controlli Symfony in modo esplicito dopo l’installazione Composer.
# In questo modo un errore di configurazione viene attribuito al comando corretto
# invece di apparire come errore generico di uno script post-install.
Invoke-Checked -Command "php" -Arguments @("scripts/doctrine-config-contract.php")
Invoke-Checked -Command "php" -Arguments @("scripts/symfony-api-contract.php")
Invoke-Checked -Command "php" -Arguments @("scripts/attachment-storage-contract.php")
Invoke-Checked -Command "php" -Arguments @("scripts/monthly-report-contract.php")
Invoke-Checked -Command "php" -Arguments @("scripts/backup-contract.php")
Invoke-Checked -Command "php" -Arguments @("bin/console", "lint:yaml", "config")
Invoke-Checked -Command "php" -Arguments @("bin/console", "lint:twig", "templates")
Invoke-Checked -Command "php" -Arguments @("bin/console", "cache:clear", "--env=dev")
Invoke-Checked -Command "php" -Arguments @("bin/console", "doctrine:migrations:migrate", "--no-interaction")
Invoke-Checked -Command "php" -Arguments @("bin/console", "doctrine:migrations:up-to-date", "--no-interaction")
Invoke-Checked -Command "php" -Arguments @("bin/console", "doctrine:schema:validate")

if ($PartnerUsername -xor $PartnerDisplayName) {
    throw "Specificare insieme -PartnerUsername e -PartnerDisplayName, oppure ometterli entrambi."
}

if ($PartnerUsername -and $PartnerDisplayName) {
    Invoke-Checked -Command "php" -Arguments @("bin/console", "app:user:create", $PartnerUsername, $PartnerDisplayName, "--role=partner")
} elseif (-not $SkipPartnerBootstrap) {
    Write-Host "Verifica del primo socio per l'accesso..." -ForegroundColor Cyan
    Invoke-Checked -Command "php" -Arguments @("bin/console", "app:user:create", "--role=partner", "--skip-if-active-partner-exists")
} else {
    Write-Host "Creazione guidata del primo socio ignorata su richiesta." -ForegroundColor Yellow
}

New-Item -ItemType Directory -Path "backups" -Force | Out-Null
New-Item -ItemType Directory -Path "var/locks" -Force | Out-Null
if (-not (Test-Path "backups" -PathType Container) -or -not (Test-Path "var/locks" -PathType Container)) {
    throw "Impossibile preparare le directory operative di backup e lock."
}

Write-Host "Installazione completata correttamente." -ForegroundColor Green
