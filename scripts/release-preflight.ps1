param(
    [ValidateSet('Install', 'Update', 'Package')]
    [string]$Mode = 'Install',
    [string]$ProjectRoot = '',
    [switch]$SkipToolChecks
)

$ErrorActionPreference = 'Stop'

if ([string]::IsNullOrWhiteSpace($ProjectRoot)) {
    $ProjectRoot = Split-Path -Parent $PSScriptRoot
}
elseif (-not [System.IO.Path]::IsPathRooted($ProjectRoot)) {
    $ProjectRoot = Join-Path (Get-Location) $ProjectRoot
}

$projectPath = [System.IO.Path]::GetFullPath($ProjectRoot)
if (-not (Test-Path -LiteralPath $projectPath -PathType Container)) {
    throw "Cartella progetto non trovata: $projectPath"
}

function Require-Command([string]$Name) {
    if (-not (Get-Command $Name -ErrorAction SilentlyContinue)) {
        throw "Comando richiesto non trovato: $Name"
    }
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

function Assert-RequiredFile([string]$RelativePath) {
    $fullPath = Join-Path $projectPath $RelativePath
    if (-not (Test-Path -LiteralPath $fullPath -PathType Leaf)) {
        throw "File obbligatorio mancante: $RelativePath"
    }
    if ((Get-Item -LiteralPath $fullPath).Length -le 0) {
        throw "File obbligatorio vuoto: $RelativePath"
    }
}

$requiredFiles = @(
    '.env.local.dist',
    'README.md',
    'composer.json',
    'composer.lock',
    'package.json',
    'package-lock.json',
    'bin/console',
    'public/index.php',
    'public/.htaccess',
    'config/services.yaml',
    'src/Kernel.php',
    'scripts/php-runtime-contract.php',
    'scripts/setup.ps1',
    'scripts/backup.ps1',
    'scripts/verify-backup.ps1',
    'scripts/restore-backup.ps1',
    'scripts/package-release.ps1',
    'scripts/verify-release-package.ps1'
)

foreach ($requiredFile in $requiredFiles) {
    Assert-RequiredFile -RelativePath $requiredFile
}

if ($Mode -ne 'Update') {
    foreach ($deploymentFile in @('scripts/release-preflight.ps1', 'scripts/update.ps1', 'scripts/install-smoke.ps1')) {
        Assert-RequiredFile -RelativePath $deploymentFile
    }
}

if ($Mode -eq 'Package') {
    $forbiddenPaths = @(
        '.env.local',
        'vendor',
        'node_modules',
        'var',
        'backups',
        'dist',
        'public/vendor'
    )

    foreach ($forbiddenPath in $forbiddenPaths) {
        if (Test-Path -LiteralPath (Join-Path $projectPath $forbiddenPath)) {
            throw "Il pacchetto estratto contiene stato locale vietato: $forbiddenPath"
        }
    }

    $forbiddenFiles = @(Get-ChildItem -LiteralPath $projectPath -Recurse -Force -File | Where-Object {
        $_.Name -eq '.env.local' -or
        $_.Name -match '^\.env\..+\.local$' -or
        $_.Name -match '\.(db|sqlite|sqlite3|log|tmp|temp|bak|zip)$' -or
        $_.Name -match '-(wal|shm|journal)$'
    })
    if ($forbiddenFiles.Count -gt 0) {
        $preview = ($forbiddenFiles | Select-Object -First 10 | ForEach-Object {
            [System.IO.Path]::GetRelativePath($projectPath, $_.FullName)
        }) -join ', '
        throw "Il pacchetto estratto contiene file locali vietati: $preview"
    }
}

if ($Mode -eq 'Update') {
    foreach ($requiredLocalPath in @('.env.local', 'var/studio_commesse.db')) {
        if (-not (Test-Path -LiteralPath (Join-Path $projectPath $requiredLocalPath) -PathType Leaf)) {
            throw "Installazione di destinazione incompleta: manca $requiredLocalPath"
        }
    }
}

if (-not $SkipToolChecks) {
    foreach ($command in @('php', 'composer', 'node', 'npm')) {
        Require-Command $command
    }

    Push-Location $projectPath
    try {
        Invoke-Checked -Command 'php' -Arguments @('scripts/php-runtime-contract.php')
        Invoke-Checked -Command 'composer' -Arguments @('validate', '--strict')
        Invoke-Checked -Command 'node' -Arguments @('-e', 'process.exit(Number(process.versions.node.split(".")[0]) >= 20 ? 0 : 1)')
        Invoke-Checked -Command 'npm' -Arguments @('--version')
    }
    finally {
        Pop-Location
    }
}

Write-Host ("Preflight {0} superato: {1}" -f $Mode, $projectPath) -ForegroundColor Green
