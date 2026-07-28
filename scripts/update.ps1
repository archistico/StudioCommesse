param(
    [Parameter(Mandatory = $true)]
    [string]$TargetDirectory,
    [Parameter(Mandatory = $true)]
    [string]$Confirm,
    [switch]$StagedRelease
)

$ErrorActionPreference = 'Stop'
$releaseRoot = [System.IO.Path]::GetFullPath((Split-Path -Parent $PSScriptRoot))

if ($Confirm -ne 'UPDATE') {
    throw 'Aggiornamento annullato. Specificare -Confirm UPDATE in modo esplicito.'
}

if (-not [System.IO.Path]::IsPathRooted($TargetDirectory)) {
    $TargetDirectory = Join-Path (Get-Location) $TargetDirectory
}
$targetRoot = [System.IO.Path]::GetFullPath($TargetDirectory)
if (-not (Test-Path -LiteralPath $targetRoot -PathType Container)) {
    throw "Installazione di destinazione non trovata: $targetRoot"
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
        $displayCommand = ("{0} {1}" -f $Command, ($Arguments -join ' ')).Trim()
        throw ("Comando fallito con codice {0}: {1}" -f $exitCode, $displayCommand)
    }
}

function Test-ExcludedDeploymentPath {
    param([Parameter(Mandatory = $true)][string]$RelativePath)

    $normalized = $RelativePath.Replace('\', '/').TrimStart('/').ToLowerInvariant()
    if ([string]::IsNullOrWhiteSpace($normalized)) {
        return $true
    }

    $segments = $normalized.Split('/')
    $topLevel = $segments[0]
    if ($topLevel -in @(
        '.git', '.idea', '.vscode', '.phpunit.cache', '.phpstan.cache',
        'vendor', 'node_modules', 'var', 'backups', 'dist'
    )) {
        return $true
    }
    if ($normalized -eq 'public/vendor' -or $normalized.StartsWith('public/vendor/')) {
        return $true
    }

    $fileName = $segments[$segments.Length - 1]
    if ($fileName -eq '.env.local' -or $fileName -match '^\.env\..+\.local$') {
        return $true
    }
    if ($fileName -in @('.ds_store', 'thumbs.db', 'desktop.ini', '.phpunit.result.cache')) {
        return $true
    }
    if ($fileName -match '\.(db|sqlite|sqlite3|log|tmp|temp|bak|zip)$') {
        return $true
    }
    if ($fileName -match '-(wal|shm|journal)$') {
        return $true
    }

    return $false
}

function Get-DeployableFiles {
    param(
        [Parameter(Mandatory = $true)][string]$Root,
        [string]$RelativePrefix = ''
    )

    foreach ($item in (Get-ChildItem -LiteralPath $Root -Force)) {
        $relative = if ([string]::IsNullOrEmpty($RelativePrefix)) {
            $item.Name
        }
        else {
            $RelativePrefix + '/' + $item.Name
        }

        if (Test-ExcludedDeploymentPath -RelativePath $relative) {
            continue
        }
        if (($item.Attributes -band [System.IO.FileAttributes]::ReparsePoint) -ne 0) {
            throw "Collegamento o reparse point non consentito: $($item.FullName)"
        }

        if ($item.PSIsContainer) {
            Get-DeployableFiles -Root $item.FullName -RelativePrefix $relative
        }
        elseif ($item -is [System.IO.FileInfo]) {
            [PSCustomObject]@{
                File = $item
                Relative = $relative.Replace('\', '/')
            }
        }
    }
}

function Get-ZipFileEntries([string]$ArchivePath) {
    Add-Type -AssemblyName System.IO.Compression
    Add-Type -AssemblyName System.IO.Compression.FileSystem
    $archive = [System.IO.Compression.ZipFile]::OpenRead($ArchivePath)
    try {
        return @($archive.Entries | Where-Object { -not $_.FullName.EndsWith('/') } | ForEach-Object { $_.FullName })
    }
    finally {
        $archive.Dispose()
    }
}

function Remove-DeployableFiles {
    param(
        [Parameter(Mandatory = $true)][string]$Root,
        [Parameter(Mandatory = $true)]
        [AllowEmptyCollection()]
        [string[]]$RelativePaths
    )

    if ($null -eq $RelativePaths -or $RelativePaths.Count -eq 0) {
        return
    }

    foreach ($relativePath in ($RelativePaths | Sort-Object Length -Descending)) {
        if (Test-ExcludedDeploymentPath -RelativePath $relativePath) {
            continue
        }
        $fullPath = Join-Path $Root $relativePath
        if (Test-Path -LiteralPath $fullPath -PathType Leaf) {
            Remove-Item -LiteralPath $fullPath -Force
        }
    }

    $directories = @(Get-ChildItem -LiteralPath $Root -Recurse -Force -Directory | Sort-Object FullName -Descending)
    foreach ($directory in $directories) {
        $relative = [System.IO.Path]::GetRelativePath($Root, $directory.FullName).Replace('\', '/')
        if (Test-ExcludedDeploymentPath -RelativePath $relative) {
            continue
        }
        if (@(Get-ChildItem -LiteralPath $directory.FullName -Force).Count -eq 0) {
            Remove-Item -LiteralPath $directory.FullName -Force
        }
    }
}

function Copy-ReleaseFiles {
    param(
        [Parameter(Mandatory = $true)][string]$Source,
        [Parameter(Mandatory = $true)][string]$Destination,
        [Parameter(Mandatory = $true)][object[]]$Files
    )

    foreach ($entry in $Files) {
        $destinationPath = Join-Path $Destination $entry.Relative
        $destinationDirectory = Split-Path -Parent $destinationPath
        New-Item -ItemType Directory -Path $destinationDirectory -Force | Out-Null
        Copy-Item -LiteralPath $entry.File.FullName -Destination $destinationPath -Force
    }
}

function Invoke-SelfStagedUpdate {
    param(
        [Parameter(Mandatory = $true)][string]$Source,
        [Parameter(Mandatory = $true)][string]$Destination
    )

    $stagingRoot = Join-Path ([System.IO.Path]::GetTempPath()) ('studio-commesse-update-release-' + [Guid]::NewGuid().ToString('N'))
    New-Item -ItemType Directory -Path $stagingRoot -Force | Out-Null
    try {
        $stagedFiles = @(Get-DeployableFiles -Root $Source | Sort-Object Relative)
        Copy-ReleaseFiles -Source $Source -Destination $stagingRoot -Files $stagedFiles
        $stagedScript = Join-Path $stagingRoot 'scripts/update.ps1'
        if (-not (Test-Path -LiteralPath $stagedScript -PathType Leaf)) {
            throw 'Impossibile creare lo staging temporaneo della release.'
        }

        Write-Host ("Release e destinazione coincidono; staging automatico creato in {0}." -f $stagingRoot) -ForegroundColor Yellow
        & $stagedScript -TargetDirectory $Destination -Confirm UPDATE -StagedRelease
    }
    finally {
        Remove-Item -LiteralPath $stagingRoot -Recurse -Force -ErrorAction SilentlyContinue
    }
}

function Restore-CodeArchive {
    param(
        [Parameter(Mandatory = $true)][string]$ArchivePath,
        [Parameter(Mandatory = $true)][string]$Destination,
        [Parameter(Mandatory = $true)][string[]]$CurrentReleaseFiles
    )

    Remove-DeployableFiles -Root $Destination -RelativePaths $CurrentReleaseFiles
    $restoreDirectory = Join-Path ([System.IO.Path]::GetTempPath()) ('studio-commesse-code-rollback-' + [Guid]::NewGuid().ToString('N'))
    New-Item -ItemType Directory -Path $restoreDirectory -Force | Out-Null
    try {
        [System.IO.Compression.ZipFile]::ExtractToDirectory($ArchivePath, $restoreDirectory)
        $previousFiles = @(Get-DeployableFiles -Root $restoreDirectory)
        Copy-ReleaseFiles -Source $restoreDirectory -Destination $Destination -Files $previousFiles
    }
    finally {
        Remove-Item -LiteralPath $restoreDirectory -Recurse -Force -ErrorAction SilentlyContinue
    }
}

if ($releaseRoot.Equals($targetRoot, [System.StringComparison]::OrdinalIgnoreCase)) {
    if ($StagedRelease) {
        throw 'Lo staging interno coincide ancora con la destinazione; aggiornamento interrotto.'
    }
    Invoke-SelfStagedUpdate -Source $releaseRoot -Destination $targetRoot
    return
}

& (Join-Path $releaseRoot 'scripts/release-preflight.ps1') -Mode Package -ProjectRoot $releaseRoot -SkipToolChecks
& (Join-Path $releaseRoot 'scripts/release-preflight.ps1') -Mode Update -ProjectRoot $targetRoot

$releaseFiles = @(Get-DeployableFiles -Root $releaseRoot | Sort-Object Relative)
$releaseNames = @($releaseFiles | ForEach-Object { $_.Relative })
$timestamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$safetyRoot = Join-Path $targetRoot ("backups/update-{0}" -f $timestamp)
New-Item -ItemType Directory -Path $safetyRoot -Force | Out-Null
$previousCodeArchive = Join-Path $safetyRoot 'previous-code.zip'
$rollbackInstructions = Join-Path $safetyRoot 'ROLLBACK.txt'
$dataBackup = $null
$maintenanceEnabled = $false

try {
    & (Join-Path $targetRoot 'scripts/backup.ps1') -DestinationDirectory $safetyRoot
    $dataBackup = Get-ChildItem -LiteralPath $safetyRoot -Filter 'StudioCommesse_Backup_*.zip' -File |
        Sort-Object LastWriteTimeUtc -Descending |
        Select-Object -First 1
    if ($null -eq $dataBackup) {
        throw 'Il backup dati pre-aggiornamento non è stato creato.'
    }
    & (Join-Path $targetRoot 'scripts/verify-backup.ps1') -Archive $dataBackup.FullName
    & (Join-Path $targetRoot 'scripts/package-release.ps1') -OutputPath $previousCodeArchive -Force

    $previousCodeEntries = @(Get-ZipFileEntries -ArchivePath $previousCodeArchive)
    $staleEntries = @($previousCodeEntries | Where-Object { $releaseNames -cnotcontains $_ })

    Push-Location $targetRoot
    try {
        Invoke-Checked -Command 'php' -Arguments @(
            'bin/console',
            'app:maintenance:enable',
            '--confirm=MAINTENANCE',
            '--message=Aggiornamento applicativo e verifica in corso.',
            '--env=prod',
            '--no-debug'
        )
        $maintenanceEnabled = $true
    }
    finally {
        Pop-Location
    }

    if ($staleEntries.Count -gt 0) {
        Remove-DeployableFiles -Root $targetRoot -RelativePaths $staleEntries
    }
    Copy-ReleaseFiles -Source $releaseRoot -Destination $targetRoot -Files $releaseFiles

    & (Join-Path $targetRoot 'scripts/release-preflight.ps1') -Mode Update -ProjectRoot $targetRoot
    & (Join-Path $targetRoot 'scripts/setup.ps1') -SkipPartnerBootstrap

    Push-Location $targetRoot
    try {
        Invoke-Checked -Command 'php' -Arguments @('bin/console', 'cache:clear', '--env=prod', '--no-debug')
        Invoke-Checked -Command 'php' -Arguments @('bin/console', 'doctrine:migrations:up-to-date', '--env=prod', '--no-interaction', '--no-debug')
        Invoke-Checked -Command 'php' -Arguments @('bin/console', 'doctrine:schema:validate', '--env=prod', '--no-debug')
        Invoke-Checked -Command 'php' -Arguments @('bin/console', 'app:maintenance:disable', '--confirm=CLEAR', '--env=prod', '--no-debug')
        $maintenanceEnabled = $false
    }
    finally {
        Pop-Location
    }

    @(
        'Aggiornamento completato correttamente.',
        ("Backup dati precedente: {0}" -f $dataBackup.FullName),
        ("Archivio codice precedente: {0}" -f $previousCodeArchive),
        'Questi file possono essere eliminati solo dopo il collaudo della nuova versione.'
    ) | Set-Content -LiteralPath $rollbackInstructions -Encoding UTF8

    Write-Host 'Aggiornamento completato e verificato.' -ForegroundColor Green
    Write-Host ("Backup e materiale di rollback: {0}" -f $safetyRoot) -ForegroundColor Green
}
catch {
    $updateError = $_
    Write-Warning ("Aggiornamento fallito: {0}" -f $updateError.Exception.Message)
    Write-Warning 'Avvio del rollback automatico di codice e dati.'

    $rollbackSucceeded = $false
    $rollbackFailure = $null
    try {
        if (-not (Test-Path -LiteralPath $previousCodeArchive -PathType Leaf)) {
            throw 'Archivio del codice precedente non disponibile.'
        }
        Restore-CodeArchive -ArchivePath $previousCodeArchive -Destination $targetRoot -CurrentReleaseFiles $releaseNames

        & (Join-Path $targetRoot 'scripts/setup.ps1') -SkipPartnerBootstrap

        if ($null -eq $dataBackup -or -not (Test-Path -LiteralPath $dataBackup.FullName -PathType Leaf)) {
            throw 'Backup dati pre-aggiornamento non disponibile.'
        }

        Push-Location $targetRoot
        try {
            . (Join-Path $targetRoot 'scripts/backup-common.ps1')
            $restoreWorkspace = New-StudioTemporaryDirectory 'update-rollback-data'
            Remove-Item $restoreWorkspace -Recurse -Force
            try {
                Expand-StudioBackupArchive -ArchivePath $dataBackup.FullName -DestinationDirectory $restoreWorkspace
                Invoke-Checked -Command 'php' -Arguments @('bin/console', 'app:backup:verify', $restoreWorkspace, '--env=prod', '--no-debug')
                Invoke-Checked -Command 'php' -Arguments @(
                    'bin/console',
                    'app:backup:restore',
                    $restoreWorkspace,
                    ("--safety-backup-dir={0}" -f (Join-Path $safetyRoot 'failed-release-data')),
                    '--confirm=RESTORE',
                    '--env=prod',
                    '--no-debug'
                )
                Invoke-Checked -Command 'php' -Arguments @('bin/console', 'doctrine:migrations:migrate', '--no-interaction', '--env=prod', '--no-debug')
                Invoke-Checked -Command 'php' -Arguments @('bin/console', 'doctrine:schema:validate', '--env=prod', '--no-debug')
                Invoke-Checked -Command 'php' -Arguments @('bin/console', 'app:maintenance:disable', '--confirm=CLEAR', '--env=prod', '--no-debug')
                $maintenanceEnabled = $false
            }
            finally {
                if (Test-Path -LiteralPath $restoreWorkspace) {
                    Remove-Item -LiteralPath $restoreWorkspace -Recurse -Force
                }
            }
        }
        finally {
            Pop-Location
        }

        @(
            'Rollback automatico completato.',
            ("Errore aggiornamento: {0}" -f $updateError.Exception.Message),
            ("Backup dati ripristinato: {0}" -f $dataBackup.FullName),
            ("Codice ripristinato da: {0}" -f $previousCodeArchive)
        ) | Set-Content -LiteralPath $rollbackInstructions -Encoding UTF8
        $rollbackSucceeded = $true
    }
    catch {
        $rollbackFailure = $_
    }

    if ($rollbackSucceeded) {
        throw ("Aggiornamento annullato; rollback completato. Errore originale: {0}" -f $updateError.Exception.Message)
    }

    @(
        'ROLLBACK AUTOMATICO NON COMPLETATO.',
        ("Errore aggiornamento: {0}" -f $updateError.Exception.Message),
        ("Errore rollback: {0}" -f $rollbackFailure.Exception.Message),
        ("Backup dati: {0}" -f $(if ($null -ne $dataBackup) { $dataBackup.FullName } else { '(non disponibile)' })),
        ("Archivio codice: {0}" -f $previousCodeArchive),
        'Non disattivare la manutenzione finché codice, database e allegati non sono stati verificati.'
    ) | Set-Content -LiteralPath $rollbackInstructions -Encoding UTF8

    if ($maintenanceEnabled) {
        Write-Warning "La modalità manutenzione resta attiva per proteggere l'installazione."
    }
    throw ("Aggiornamento e rollback non completati. Consultare {0}. Dettaglio: {1}" -f $rollbackInstructions, $rollbackFailure.Exception.Message)
}
