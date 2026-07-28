param(
    [Parameter(Mandatory = $true)]
    [string]$Archive
)

$ErrorActionPreference = 'Stop'
$projectRoot = [System.IO.Path]::GetFullPath((Split-Path -Parent $PSScriptRoot))

if (-not [System.IO.Path]::IsPathRooted($Archive)) {
    $Archive = Join-Path $projectRoot $Archive
}
$archivePath = [System.IO.Path]::GetFullPath($Archive)
if (-not (Test-Path -LiteralPath $archivePath -PathType Leaf)) {
    throw "Pacchetto non trovato: $archivePath"
}

& (Join-Path $PSScriptRoot 'verify-release-package.ps1') -Archive $archivePath -SourceRoot $projectRoot

$temporaryRoot = Join-Path ([System.IO.Path]::GetTempPath()) ('studio-commesse-install-smoke-' + [Guid]::NewGuid().ToString('N'))
New-Item -ItemType Directory -Path $temporaryRoot -Force | Out-Null

try {
    Add-Type -AssemblyName System.IO.Compression.FileSystem
    [System.IO.Compression.ZipFile]::ExtractToDirectory($archivePath, $temporaryRoot)

    & (Join-Path $temporaryRoot 'scripts/release-preflight.ps1') `
        -Mode Package `
        -ProjectRoot $temporaryRoot `
        -SkipToolChecks

    if (Test-Path -LiteralPath (Join-Path $temporaryRoot '.env.local')) {
        throw 'Lo smoke test ha trovato .env.local nel pacchetto estratto.'
    }
    if (Test-Path -LiteralPath (Join-Path $temporaryRoot 'var')) {
        throw 'Lo smoke test ha trovato la directory var nel pacchetto estratto.'
    }

    Write-Host 'Smoke test di installazione pulita superato.' -ForegroundColor Green
}
finally {
    if (Test-Path -LiteralPath $temporaryRoot) {
        Remove-Item -LiteralPath $temporaryRoot -Recurse -Force
    }
}
