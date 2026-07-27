param(
    [string]$DestinationDirectory = "backups"
)

$ErrorActionPreference = "Stop"
Set-Location (Split-Path -Parent $PSScriptRoot)
. (Join-Path $PSScriptRoot "backup-common.ps1")

Require-Command "php"

$destination = Get-AbsolutePath $DestinationDirectory
New-Item -ItemType Directory -Path $destination -Force | Out-Null
$timestamp = Get-Date -Format "yyyyMMdd-HHmmss"
$archivePath = Join-Path $destination ("StudioCommesse_Backup_{0}.zip" -f $timestamp)
$workspace = New-StudioTemporaryDirectory "backup-create"
$verificationWorkspace = $null

try {
    Remove-Item $workspace -Recurse -Force
    Invoke-Checked -Command "php" -Arguments @(
        "bin/console",
        "app:backup:create",
        $workspace,
        "--env=prod",
        "--no-debug"
    )

    Compress-StudioBackupDirectory -SourceDirectory $workspace -ArchivePath $archivePath

    $verificationWorkspace = New-StudioTemporaryDirectory "backup-verify"
    Remove-Item $verificationWorkspace -Recurse -Force
    Expand-StudioBackupArchive -ArchivePath $archivePath -DestinationDirectory $verificationWorkspace
    Invoke-Checked -Command "php" -Arguments @(
        "bin/console",
        "app:backup:verify",
        $verificationWorkspace,
        "--env=prod",
        "--no-debug"
    )

    Write-Host ("Backup creato e verificato: {0}" -f $archivePath) -ForegroundColor Green
}
finally {
    if (Test-Path $workspace) {
        Remove-Item $workspace -Recurse -Force
    }
    if ($null -ne $verificationWorkspace -and (Test-Path $verificationWorkspace)) {
        Remove-Item $verificationWorkspace -Recurse -Force
    }
}
