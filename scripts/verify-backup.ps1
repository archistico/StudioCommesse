param(
    [string]$Archive = ""
)

$ErrorActionPreference = "Stop"
Set-Location (Split-Path -Parent $PSScriptRoot)
. (Join-Path $PSScriptRoot "backup-common.ps1")

Require-Command "php"

$archivePath = Resolve-StudioBackupArchive -ArchivePath $Archive
$workspace = New-StudioTemporaryDirectory "backup-verify"

try {
    Remove-Item $workspace -Recurse -Force
    Expand-StudioBackupArchive -ArchivePath $archivePath -DestinationDirectory $workspace
    Invoke-Checked -Command "php" -Arguments @(
        "bin/console",
        "app:backup:verify",
        $workspace,
        "--env=prod",
        "--no-debug"
    )

    Write-Host ("Backup verificato correttamente: {0}" -f $archivePath) -ForegroundColor Green
}
finally {
    if (Test-Path $workspace) {
        Remove-Item $workspace -Recurse -Force
    }
}
