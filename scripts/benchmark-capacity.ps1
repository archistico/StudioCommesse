param(
    [ValidateSet(30, 200, 600)]
    [int[]]$Profiles = @(30, 200, 600),
    [ValidateRange(1, 10)]
    [int]$Iterations = 2,
    [switch]$SkipBackup,
    [switch]$KeepDatabases
)

$ErrorActionPreference = "Stop"
Set-Location (Split-Path -Parent $PSScriptRoot)

function Invoke-Checked {
    param(
        [Parameter(Mandatory = $true)][string]$Command,
        [Parameter(Mandatory = $true)][string[]]$Arguments
    )

    & $Command @Arguments
    $exitCode = $LASTEXITCODE
    if ($exitCode -ne 0) {
        throw ("Comando fallito con codice {0}: {1} {2}" -f $exitCode, $Command, ($Arguments -join " "))
    }
}

$performanceRoot = Join-Path (Get-Location) "var/performance"
$resultRoot = Join-Path $performanceRoot "results"
New-Item -ItemType Directory -Path $performanceRoot -Force | Out-Null
New-Item -ItemType Directory -Path $resultRoot -Force | Out-Null

$previousEnvironment = @{
    APP_ENV = $env:APP_ENV
    APP_DEBUG = $env:APP_DEBUG
    DATABASE_URL = $env:DATABASE_URL
}

try {
    $env:APP_ENV = "test"
    $env:APP_DEBUG = "0"

    foreach ($profile in $Profiles) {
        Write-Host ("`n=== Profilo {0} commesse ===" -f $profile) -ForegroundColor Cyan
        $databasePath = Join-Path $performanceRoot ("studio_commesse_{0}.db" -f $profile)
        $databaseUrlPath = ([System.IO.Path]::GetFullPath($databasePath)).Replace('\', '/')
        $env:DATABASE_URL = "sqlite:///$databaseUrlPath"

        Remove-Item -LiteralPath $databasePath -Force -ErrorAction SilentlyContinue
        Remove-Item -LiteralPath (Join-Path (Get-Location) "var/test-attachments") -Recurse -Force -ErrorAction SilentlyContinue
        Remove-Item -LiteralPath (Join-Path (Get-Location) "var/test-locks") -Recurse -Force -ErrorAction SilentlyContinue
        Remove-Item -LiteralPath (Join-Path (Get-Location) "var/test-maintenance.lock") -Force -ErrorAction SilentlyContinue
        New-Item -ItemType Directory -Path (Split-Path -Parent $databasePath) -Force | Out-Null

        # SQLite crea il file al primo collegamento; il comando di creazione database non e supportato dalla piattaforma.
        Invoke-Checked -Command "php" -Arguments @("bin/console", "doctrine:migrations:migrate", "--env=test", "--no-debug", "--no-interaction")
        Invoke-Checked -Command "php" -Arguments @("bin/console", "app:performance:seed", ("--projects={0}" -f $profile), "--reset", "--confirm=BENCHMARK", "--env=test", "--no-debug")

        $jsonPath = Join-Path $resultRoot ("capacity-{0}.json" -f $profile)
        $arguments = @(
            "bin/console",
            "app:performance:benchmark",
            ("--projects={0}" -f $profile),
            ("--iterations={0}" -f $Iterations),
            ("--json={0}" -f $jsonPath),
            "--enforce",
            "--env=test",
            "--no-debug"
        )
        if ($SkipBackup) {
            $arguments += "--skip-backup"
        }
        Invoke-Checked -Command "php" -Arguments $arguments

        if (-not $KeepDatabases) {
            Remove-Item -LiteralPath $databasePath -Force -ErrorAction SilentlyContinue
        }
    }
}
finally {
    if ($null -eq $previousEnvironment.APP_ENV) { Remove-Item Env:APP_ENV -ErrorAction SilentlyContinue } else { $env:APP_ENV = $previousEnvironment.APP_ENV }
    if ($null -eq $previousEnvironment.APP_DEBUG) { Remove-Item Env:APP_DEBUG -ErrorAction SilentlyContinue } else { $env:APP_DEBUG = $previousEnvironment.APP_DEBUG }
    if ($null -eq $previousEnvironment.DATABASE_URL) { Remove-Item Env:DATABASE_URL -ErrorAction SilentlyContinue } else { $env:DATABASE_URL = $previousEnvironment.DATABASE_URL }
    Remove-Item -LiteralPath (Join-Path (Get-Location) "var/test-attachments") -Recurse -Force -ErrorAction SilentlyContinue
    Remove-Item -LiteralPath (Join-Path (Get-Location) "var/test-locks") -Recurse -Force -ErrorAction SilentlyContinue
    Remove-Item -LiteralPath (Join-Path (Get-Location) "var/test-maintenance.lock") -Force -ErrorAction SilentlyContinue
}

Write-Host "`nM9.2-G CAPACITY BENCHMARK PASSED" -ForegroundColor Green
Write-Host ("Rapporti JSON: {0}" -f $resultRoot)
