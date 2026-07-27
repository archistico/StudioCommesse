param(
    [Parameter(Mandatory = $true)]
    [string]$Confirm
)

$ErrorActionPreference = "Stop"
Set-Location (Split-Path -Parent $PSScriptRoot)
. (Join-Path $PSScriptRoot "backup-common.ps1")

Require-Command "php"

if ($Confirm -ne "CLEAR") {
    throw "Operazione annullata. Specificare -Confirm CLEAR dopo aver verificato database e allegati."
}

Invoke-Checked -Command "php" -Arguments @(
    "bin/console",
    "app:maintenance:disable",
    "--confirm=CLEAR",
    "--env=prod",
    "--no-debug"
)
