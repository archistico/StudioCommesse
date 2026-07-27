$ErrorActionPreference = "Stop"
Set-Location (Split-Path -Parent $PSScriptRoot)

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

function Invoke-SchemaValidation {
    & php bin/console doctrine:schema:validate --env=test
    $exitCode = $LASTEXITCODE

    if ($exitCode -ne 0) {
        Write-Host "Differenze SQL rilevate tra mapping e database di test:" -ForegroundColor Yellow
        & php bin/console doctrine:schema:update --dump-sql --env=test
        throw ("Schema Doctrine non sincronizzato; doctrine:schema:validate ha restituito {0}." -f $exitCode)
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

if (-not (Test-Path "composer.lock")) {
    throw "composer.lock mancante. Eseguire prima .\\scripts\\setup.ps1."
}
if (-not (Test-Path "package-lock.json")) {
    throw "package-lock.json mancante. Eseguire prima .\\scripts\\setup.ps1."
}

Invoke-Checked -Command "composer" -Arguments @("validate", "--strict")
Invoke-Checked -Command "composer" -Arguments @("install", "--no-interaction", "--prefer-dist", "--no-scripts")
Invoke-Checked -Command "composer" -Arguments @("check-platform-reqs")
Invoke-Checked -Command "composer" -Arguments @("audit", "--locked")

Invoke-Checked -Command "npm" -Arguments @("ci")
Invoke-Checked -Command "npm" -Arguments @("audit", "--audit-level=high")
Invoke-Checked -Command "npm" -Arguments @("run", "build")
Invoke-Checked -Command "npm" -Arguments @("test")

Invoke-Checked -Command "php" -Arguments @("scripts/php-lint.php")
Invoke-Checked -Command "php" -Arguments @("scripts/doctrine-config-contract.php")
Invoke-Checked -Command "php" -Arguments @("scripts/symfony-api-contract.php")
Invoke-Checked -Command "php" -Arguments @("scripts/attachment-storage-contract.php")
Invoke-Checked -Command "php" -Arguments @("scripts/monthly-report-contract.php")
Invoke-Checked -Command "php" -Arguments @("scripts/backup-contract.php")
Invoke-Checked -Command "php" -Arguments @("scripts/m91-hotfix1-contract.php")
Invoke-Checked -Command "php" -Arguments @("scripts/m91-hotfix2-contract.php")
Invoke-Checked -Command "php" -Arguments @("scripts/m92a-packaging-contract.php")
Invoke-Checked -Command "php" -Arguments @("scripts/m92a-hotfix1-contract.php")
Invoke-Checked -Command "php" -Arguments @("bin/console", "lint:yaml", "config")
Invoke-Checked -Command "php" -Arguments @("bin/console", "lint:twig", "templates")
Invoke-Checked -Command "php" -Arguments @("bin/console", "cache:clear", "--env=dev")

Remove-Item "var/studio_commesse_test.db" -Force -ErrorAction SilentlyContinue
Invoke-Checked -Command "php" -Arguments @("bin/console", "doctrine:migrations:migrate", "--env=test", "--no-interaction")
Invoke-Checked -Command "php" -Arguments @("bin/console", "doctrine:migrations:up-to-date", "--env=test", "--no-interaction")
Invoke-SchemaValidation

$backupSmokeDirectory = Join-Path (Get-Location) "var/backup-smoke-test"
Remove-Item $backupSmokeDirectory -Recurse -Force -ErrorAction SilentlyContinue
try {
    Invoke-Checked -Command "php" -Arguments @("bin/console", "app:backup:create", $backupSmokeDirectory, "--env=test")
    Invoke-Checked -Command "php" -Arguments @("bin/console", "app:backup:verify", $backupSmokeDirectory, "--env=test")
}
finally {
    Remove-Item $backupSmokeDirectory -Recurse -Force -ErrorAction SilentlyContinue
}


$packageSmokeDirectory = Join-Path (Get-Location) "var/package-smoke"
$packageSmokeArchive = Join-Path $packageSmokeDirectory "StudioCommesse_M9.2-A_Hotfix1_Smoke.zip"
Remove-Item $packageSmokeDirectory -Recurse -Force -ErrorAction SilentlyContinue
try {
    & .\scripts\package-release.ps1 -OutputPath $packageSmokeArchive -Force
    if (-not (Test-Path $packageSmokeArchive -PathType Leaf)) {
        throw "Il pacchetto smoke M9.2-A Hotfix 1 non è stato creato."
    }
}
finally {
    Remove-Item $packageSmokeDirectory -Recurse -Force -ErrorAction SilentlyContinue
}

Invoke-Checked -Command "php" -Arguments @("vendor/bin/phpstan", "analyse", "--memory-limit=1G")
Invoke-Checked -Command "php" -Arguments @("vendor/bin/phpunit")

Write-Host "M9.2-A HOTFIX 1 VALIDATION PASSED" -ForegroundColor Green
