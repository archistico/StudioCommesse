param(
    [Parameter(Mandatory = $true)]
    [string]$Confirm,
    [ValidateSet("prod", "dev", "test")]
    [string]$Environment = "prod",
    [string]$BackupDirectory = "backups"
)

$ErrorActionPreference = "Stop"
Set-Location (Split-Path -Parent $PSScriptRoot)
. (Join-Path $PSScriptRoot "backup-common.ps1")

Require-Command "php"

if ($Confirm -ne "KEEP-USERS") {
    throw "Operazione annullata. Specificare -Confirm KEEP-USERS in modo esplicito."
}

$backupRoot = Get-AbsolutePath $BackupDirectory
New-Item -ItemType Directory -Path $backupRoot -Force | Out-Null
$timestamp = Get-Date -Format "yyyyMMdd-HHmmss"
$backupArchive = Join-Path $backupRoot ("StudioCommesse_PreClear_{0}_{1}.zip" -f $Environment, $timestamp)
$backupWorkspace = New-StudioTemporaryDirectory "database-clear-backup"
$verificationWorkspace = New-StudioTemporaryDirectory "database-clear-verify"
$environmentOption = "--env=$Environment"

try {
    Invoke-Checked -Command "php" -Arguments @(
        "bin/console",
        "cache:clear",
        $environmentOption,
        "--no-debug"
    )

    Remove-Item $backupWorkspace -Recurse -Force
    Remove-Item $verificationWorkspace -Recurse -Force

    Invoke-Checked -Command "php" -Arguments @(
        "bin/console",
        "app:backup:create",
        $backupWorkspace,
        $environmentOption,
        "--no-debug"
    )
    Invoke-Checked -Command "php" -Arguments @(
        "bin/console",
        "app:backup:verify",
        $backupWorkspace,
        $environmentOption,
        "--no-debug"
    )
    Compress-StudioBackupDirectory -SourceDirectory $backupWorkspace -ArchivePath $backupArchive
    Expand-StudioBackupArchive -ArchivePath $backupArchive -DestinationDirectory $verificationWorkspace
    Invoke-Checked -Command "php" -Arguments @(
        "bin/console",
        "app:backup:verify",
        $verificationWorkspace,
        $environmentOption,
        "--no-debug"
    )

    Write-Host ("Backup preventivo creato e verificato: {0}" -f $backupArchive) -ForegroundColor Green
    Write-Warning "La pulizia riguarda soltanto il database: i file fisici degli allegati resteranno sul disco."

    Invoke-Checked -Command "php" -Arguments @(
        "bin/console",
        "app:database:reset-keep-users",
        "--confirm=KEEP-USERS",
        $environmentOption,
        "--no-debug"
    )
}
finally {
    if (Test-Path $backupWorkspace) {
        Remove-Item $backupWorkspace -Recurse -Force
    }
    if (Test-Path $verificationWorkspace) {
        Remove-Item $verificationWorkspace -Recurse -Force
    }
}

Write-Host ("Database {0} azzerato; utenti e migrazioni conservati." -f $Environment) -ForegroundColor Green
