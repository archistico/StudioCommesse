param(
    [string]$OutputPath = "",
    [switch]$Force
)

$ErrorActionPreference = "Stop"
$projectRoot = [System.IO.Path]::GetFullPath((Split-Path -Parent $PSScriptRoot))
Set-Location $projectRoot

function Test-ExcludedReleasePath {
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

function Assert-SafeArchiveEntryName {
    param([Parameter(Mandatory = $true)][string]$EntryName)

    if ([string]::IsNullOrWhiteSpace($EntryName)
        -or $EntryName.StartsWith('/')
        -or $EntryName.StartsWith('\')
        -or $EntryName.Contains('\')
        -or $EntryName.Contains(':')
    ) {
        throw "Nome non sicuro nel pacchetto: $EntryName"
    }

    $reservedNames = '^(CON|PRN|AUX|NUL|COM[1-9]|LPT[1-9])$'
    foreach ($segment in $EntryName.Split('/')) {
        if ([string]::IsNullOrWhiteSpace($segment)
            -or $segment -eq '.'
            -or $segment -eq '..'
            -or $segment.EndsWith('.')
            -or $segment.EndsWith(' ')
        ) {
            throw "Segmento non sicuro nel pacchetto: $EntryName"
        }
        $baseName = [System.IO.Path]::GetFileNameWithoutExtension($segment).ToUpperInvariant()
        if ($baseName -match $reservedNames) {
            throw "Nome di dispositivo Windows non consentito nel pacchetto: $EntryName"
        }
    }
}

function Get-ReleasableFiles {
    param(
        [Parameter(Mandatory = $true)][string]$Directory,
        [string]$RelativePrefix = ''
    )

    foreach ($item in (Get-ChildItem -LiteralPath $Directory -Force)) {
        $relative = if ([string]::IsNullOrEmpty($RelativePrefix)) {
            $item.Name
        } else {
            $RelativePrefix + '/' + $item.Name
        }

        if (Test-ExcludedReleasePath -RelativePath $relative) {
            continue
        }
        if (($item.Attributes -band [System.IO.FileAttributes]::ReparsePoint) -ne 0) {
            throw "Collegamento o reparse point non consentito nel sorgente: $($item.FullName)"
        }

        if ($item.PSIsContainer) {
            Get-ReleasableFiles -Directory $item.FullName -RelativePrefix $relative
        } elseif ($item -is [System.IO.FileInfo]) {
            [PSCustomObject]@{ File = $item; Relative = $relative.Replace('\', '/') }
        }
    }
}

if ([string]::IsNullOrWhiteSpace($OutputPath)) {
    $OutputPath = Join-Path $projectRoot 'dist/StudioCommesse_M9.2-A_Hotfix1_PowerShell_Parser.zip'
} elseif (-not [System.IO.Path]::IsPathRooted($OutputPath)) {
    $OutputPath = Join-Path $projectRoot $OutputPath
}

$outputFullPath = [System.IO.Path]::GetFullPath($OutputPath)
if (-not $outputFullPath.EndsWith('.zip', [System.StringComparison]::OrdinalIgnoreCase)) {
    throw "Il pacchetto deve avere estensione .zip."
}
if (Test-Path -LiteralPath $outputFullPath) {
    if (-not $Force) {
        throw "Il pacchetto esiste già. Usare -Force per sostituirlo: $outputFullPath"
    }
    Remove-Item -LiteralPath $outputFullPath -Force
}

$outputDirectory = Split-Path -Parent $outputFullPath
New-Item -ItemType Directory -Path $outputDirectory -Force | Out-Null

$files = @(Get-ReleasableFiles -Directory $projectRoot | Where-Object {
    -not $_.File.FullName.Equals($outputFullPath, [System.StringComparison]::OrdinalIgnoreCase)
} | Sort-Object Relative)

if ($files.Count -eq 0) {
    throw "Nessun file disponibile per il pacchetto."
}

Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem
$tempArchive = $outputFullPath + '.tmp-' + [guid]::NewGuid().ToString('N')

try {
    $archive = [System.IO.Compression.ZipFile]::Open($tempArchive, [System.IO.Compression.ZipArchiveMode]::Create)
    try {
        foreach ($item in $files) {
            Assert-SafeArchiveEntryName -EntryName $item.Relative
            [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile(
                $archive,
                $item.File.FullName,
                $item.Relative,
                [System.IO.Compression.CompressionLevel]::Optimal
            ) | Out-Null
        }
    } finally {
        $archive.Dispose()
    }

    $requiredEntries = @(
        '.env.local.dist',
        'README.md',
        'CHANGELOG.md',
        'composer.json',
        'composer.lock',
        'package.json',
        'package-lock.json',
        'config/services.yaml',
        'scripts/setup.ps1',
        'scripts/validate.ps1',
        'scripts/package-release.ps1',
        'docs/PACKAGING.md'
    )

    $readArchive = [System.IO.Compression.ZipFile]::OpenRead($tempArchive)
    try {
        $entryNames = @($readArchive.Entries | ForEach-Object { $_.FullName })
        $duplicates = @($entryNames | Group-Object { $_.ToLowerInvariant() } | Where-Object { $_.Count -gt 1 })
        if ($duplicates.Count -gt 0) {
            throw "Il pacchetto contiene nomi duplicati senza distinzione tra maiuscole e minuscole."
        }

        foreach ($entryName in $entryNames) {
            Assert-SafeArchiveEntryName -EntryName $entryName
            if (Test-ExcludedReleasePath -RelativePath $entryName) {
                throw "Il pacchetto contiene un elemento vietato: $entryName"
            }
        }
        foreach ($required in $requiredEntries) {
            if ($entryNames -cnotcontains $required) {
                throw "Il pacchetto non contiene il file obbligatorio: $required"
            }
        }
    } finally {
        $readArchive.Dispose()
    }

    Move-Item -LiteralPath $tempArchive -Destination $outputFullPath
} finally {
    if (Test-Path -LiteralPath $tempArchive) {
        Remove-Item -LiteralPath $tempArchive -Force
    }
}

Write-Host "Pacchetto di distribuzione creato e verificato:" -ForegroundColor Green
Write-Host $outputFullPath
