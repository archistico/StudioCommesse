$ErrorActionPreference = "Stop"

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

function Require-Command([string]$Name) {
    if (-not (Get-Command $Name -ErrorAction SilentlyContinue)) {
        throw "Comando richiesto non trovato: $Name"
    }
}

function Get-AbsolutePath([string]$Path) {
    if ([System.IO.Path]::IsPathRooted($Path)) {
        return [System.IO.Path]::GetFullPath($Path)
    }

    return [System.IO.Path]::GetFullPath((Join-Path (Get-Location) $Path))
}

function Resolve-StudioBackupArchive {
    param(
        [string]$ArchivePath = "",
        [string]$DefaultDirectory = "backups"
    )

    if ([string]::IsNullOrWhiteSpace($ArchivePath)) {
        $searchDirectory = Get-AbsolutePath $DefaultDirectory
        $matches = @(Get-ChildItem -Path $searchDirectory -Filter "StudioCommesse_Backup_*.zip" -File -ErrorAction SilentlyContinue |
            Sort-Object LastWriteTimeUtc -Descending)
        if ($matches.Count -eq 0) {
            throw "Nessun backup trovato in $searchDirectory. Eseguire prima .\scripts\backup.ps1."
        }

        return $matches[0].FullName
    }

    if ([System.Management.Automation.WildcardPattern]::ContainsWildcardCharacters($ArchivePath)) {
        $matches = @(Get-ChildItem -Path $ArchivePath -File -ErrorAction SilentlyContinue |
            Sort-Object LastWriteTimeUtc -Descending)
        if ($matches.Count -eq 0) {
            throw "Nessun archivio corrisponde al percorso: $ArchivePath"
        }

        return $matches[0].FullName
    }

    $absolute = Get-AbsolutePath $ArchivePath
    if (Test-Path $absolute -PathType Leaf) {
        return $absolute
    }

    $parent = Split-Path -Parent $absolute
    $available = @(Get-ChildItem -Path $parent -Filter "StudioCommesse_Backup_*.zip" -File -ErrorAction SilentlyContinue |
        Sort-Object LastWriteTimeUtc -Descending |
        Select-Object -First 5)
    if ($available.Count -gt 0) {
        $suggestions = ($available | ForEach-Object { $_.FullName }) -join [Environment]::NewLine
        throw ("Archivio di backup non trovato: {0}{1}Archivi disponibili:{1}{2}" -f $absolute, [Environment]::NewLine, $suggestions)
    }

    throw "Archivio di backup non trovato: $absolute. Eseguire prima .\scripts\backup.ps1."
}

function New-StudioTemporaryDirectory([string]$Prefix) {
    $root = Join-Path (Get-Location) "var"
    New-Item -ItemType Directory -Path $root -Force | Out-Null
    $path = Join-Path $root ("{0}-{1}" -f $Prefix, [Guid]::NewGuid().ToString("N"))
    New-Item -ItemType Directory -Path $path | Out-Null

    return $path
}

function Compress-StudioBackupDirectory {
    param(
        [Parameter(Mandatory = $true)]
        [string]$SourceDirectory,
        [Parameter(Mandatory = $true)]
        [string]$ArchivePath
    )

    Add-Type -AssemblyName System.IO.Compression.FileSystem
    if (Test-Path $ArchivePath) {
        throw "L'archivio di destinazione esiste già: $ArchivePath"
    }

    $parent = Split-Path -Parent $ArchivePath
    New-Item -ItemType Directory -Path $parent -Force | Out-Null
    [System.IO.Compression.ZipFile]::CreateFromDirectory(
        $SourceDirectory,
        $ArchivePath,
        [System.IO.Compression.CompressionLevel]::Optimal,
        $false
    )
}

function Expand-StudioBackupArchive {
    param(
        [Parameter(Mandatory = $true)]
        [string]$ArchivePath,
        [Parameter(Mandatory = $true)]
        [string]$DestinationDirectory
    )

    Add-Type -AssemblyName System.IO.Compression.FileSystem
    if (-not (Test-Path $ArchivePath -PathType Leaf)) {
        throw "Archivio di backup non trovato: $ArchivePath"
    }
    if (Test-Path $DestinationDirectory) {
        throw "La directory temporanea esiste già: $DestinationDirectory"
    }

    New-Item -ItemType Directory -Path $DestinationDirectory | Out-Null
    $root = [System.IO.Path]::GetFullPath($DestinationDirectory)
    if (-not $root.EndsWith([string][System.IO.Path]::DirectorySeparatorChar)) {
        $root += [System.IO.Path]::DirectorySeparatorChar
    }

    $archive = [System.IO.Compression.ZipFile]::OpenRead($ArchivePath)
    try {
        foreach ($entry in $archive.Entries) {
            $entryName = $entry.FullName
            if ([string]::IsNullOrWhiteSpace($entryName) -or
                $entryName.StartsWith("/") -or
                $entryName.StartsWith("\") -or
                $entryName -match '^[A-Za-z]:'
            ) {
                throw "Percorso assoluto o vuoto non consentito nell'archivio: $entryName"
            }

            $segments = @($entryName -split '[/\\]')
            for ($index = 0; $index -lt $segments.Count; $index++) {
                $segment = $segments[$index]
                if ([string]::IsNullOrEmpty($segment)) {
                    if ($index -ne ($segments.Count - 1)) {
                        throw "Segmento vuoto non consentito nell'archivio: $entryName"
                    }
                    continue
                }
                if ($segment -eq "." -or $segment -eq ".." -or
                    $segment -match '[<>:"|?*]' -or
                    $segment -match '[\x00-\x1F]' -or
                    $segment.EndsWith(".") -or $segment.EndsWith(" ")
                ) {
                    throw "Nome di file non sicuro nell'archivio: $entryName"
                }

                $deviceName = [System.IO.Path]::GetFileNameWithoutExtension($segment).ToUpperInvariant()
                if ($deviceName -match '^(CON|PRN|AUX|NUL|COM[1-9]|LPT[1-9])$') {
                    throw "Nome di dispositivo Windows non consentito nell'archivio: $entryName"
                }
            }

            $unixMode = (($entry.ExternalAttributes -shr 16) -band 0xF000)
            if ($unixMode -eq 0xA000) {
                throw "L'archivio contiene un collegamento simbolico non consentito: $($entry.FullName)"
            }

            $relative = $entryName.Replace('/', [System.IO.Path]::DirectorySeparatorChar)
            $target = [System.IO.Path]::GetFullPath((Join-Path $DestinationDirectory $relative))
            if (-not $target.StartsWith($root, [System.StringComparison]::OrdinalIgnoreCase)) {
                throw "Percorso non sicuro nell'archivio: $($entry.FullName)"
            }

            if ([string]::IsNullOrEmpty($entry.Name)) {
                New-Item -ItemType Directory -Path $target -Force | Out-Null
                continue
            }

            $targetParent = Split-Path -Parent $target
            New-Item -ItemType Directory -Path $targetParent -Force | Out-Null
            [System.IO.Compression.ZipFileExtensions]::ExtractToFile($entry, $target, $false)
        }
    }
    finally {
        $archive.Dispose()
    }
}
