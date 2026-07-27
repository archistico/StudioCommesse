param(
    [Parameter(Mandatory = $true)]
    [string]$Archive,
    [Parameter(Mandatory = $true)]
    [string]$Confirm,
    [string]$SafetyBackupDirectory = "backups"
)

$ErrorActionPreference = "Stop"
Set-Location (Split-Path -Parent $PSScriptRoot)
. (Join-Path $PSScriptRoot "backup-common.ps1")

Require-Command "php"

if ($Confirm -ne "RESTORE") {
    throw "Ripristino annullato. Specificare -Confirm RESTORE in modo esplicito."
}

$archivePath = Get-AbsolutePath $Archive
$safetyRoot = Get-AbsolutePath $SafetyBackupDirectory
New-Item -ItemType Directory -Path $safetyRoot -Force | Out-Null
$timestamp = Get-Date -Format "yyyyMMdd-HHmmss"
$safetyArchive = Join-Path $safetyRoot ("StudioCommesse_PreRestore_{0}.zip" -f $timestamp)
$workspace = New-StudioTemporaryDirectory "backup-restore-source"
$safetyWorkspace = New-StudioTemporaryDirectory "backup-prerestore"
$safetyCompressed = $false

try {
    Remove-Item $workspace -Recurse -Force
    Remove-Item $safetyWorkspace -Recurse -Force
    Expand-StudioBackupArchive -ArchivePath $archivePath -DestinationDirectory $workspace

    Invoke-Checked -Command "php" -Arguments @(
        "bin/console",
        "app:backup:verify",
        $workspace,
        "--env=prod",
        "--no-debug"
    )

    Invoke-Checked -Command "php" -Arguments @(
        "bin/console",
        "app:maintenance:enable",
        "--confirm=MAINTENANCE",
        "--message=Ripristino coordinato, migrazioni e verifica in corso.",
        "--env=prod",
        "--no-debug"
    )

    Invoke-Checked -Command "php" -Arguments @(
        "bin/console",
        "app:backup:restore",
        $workspace,
        ("--safety-backup-dir={0}" -f $safetyWorkspace),
        "--confirm=RESTORE",
        "--env=prod",
        "--no-debug"
    )

    Invoke-Checked -Command "php" -Arguments @(
        "bin/console",
        "doctrine:migrations:migrate",
        "--no-interaction",
        "--env=prod",
        "--no-debug"
    )
    Invoke-Checked -Command "php" -Arguments @(
        "bin/console",
        "doctrine:schema:validate",
        "--env=prod",
        "--no-debug"
    )

    Invoke-Checked -Command "php" -Arguments @(
        "bin/console",
        "app:backup:verify",
        $safetyWorkspace,
        "--env=prod",
        "--no-debug"
    )
    Compress-StudioBackupDirectory -SourceDirectory $safetyWorkspace -ArchivePath $safetyArchive
    $safetyCompressed = $true

    Invoke-Checked -Command "php" -Arguments @(
        "bin/console",
        "app:maintenance:disable",
        "--confirm=CLEAR",
        "--env=prod",
        "--no-debug"
    )

    Write-Host "Ripristino completato e verificato." -ForegroundColor Green
    Write-Host ("Backup automatico precedente al ripristino: {0}" -f $safetyArchive) -ForegroundColor Green
}
finally {
    if (Test-Path $workspace) {
        Remove-Item $workspace -Recurse -Force
    }
    if ($safetyCompressed -and (Test-Path $safetyWorkspace)) {
        Remove-Item $safetyWorkspace -Recurse -Force
    }
    elseif (Test-Path $safetyWorkspace) {
        Write-Warning ("Il backup di sicurezza non è stato compresso. Non eliminare questa directory: {0}" -f $safetyWorkspace)
    }
}
