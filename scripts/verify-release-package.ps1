param(
    [Parameter(Mandatory = $true)]
    [string]$Archive,
    [string]$SourceRoot = ""
)

$ErrorActionPreference = "Stop"
$projectRoot = [System.IO.Path]::GetFullPath((Split-Path -Parent $PSScriptRoot))

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

    if ([string]::IsNullOrWhiteSpace($EntryName) -or
        $EntryName.StartsWith('/') -or
        $EntryName.StartsWith('\') -or
        $EntryName.Contains('\') -or
        $EntryName.Contains(':')
    ) {
        throw "Nome non sicuro nel pacchetto: $EntryName"
    }

    $reservedNames = '^(CON|PRN|AUX|NUL|COM[1-9]|LPT[1-9])$'
    foreach ($segment in $EntryName.Split('/')) {
        if ([string]::IsNullOrWhiteSpace($segment) -or
            $segment -eq '.' -or
            $segment -eq '..' -or
            $segment.EndsWith('.') -or
            $segment.EndsWith(' ')
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

function Assert-RequiredReleaseInventory {
    param(
        [Parameter(Mandatory = $true)][object]$ZipArchive,
        [Parameter(Mandatory = $true)][string[]]$EntryNames
    )

    $requiredFiles = @(
        '.env.local.dist',
        'README.md',
        'CHANGELOG.md',
        'composer.json',
        'composer.lock',
        'package.json',
        'package-lock.json',
        'bin/console',
        'public/index.php',
        'public/.htaccess',
        'config/bundles.php',
        'config/services.yaml',
        'config/authorization_matrix.php',
        'migrations/Version20260728160000.php',
        'src/Kernel.php',
        'src/Controller/UserController.php',
        'src/Controller/AuditController.php',
        'src/Entity/User.php',
        'src/Repository/UserRepository.php',
        'src/Repository/AuditLogRepository.php',
        'src/Query/AuditSearchCriteria.php',
        'src/Query/AuditPage.php',
        'src/Query/AuditSummary.php',
        'src/Query/MonthlyUserCostReportRow.php',
        'src/Query/DashboardSummary.php',
        'src/Repository/DashboardRepository.php',
        'src/Performance/CapacityProfile.php',
        'src/Performance/CapacityDatasetSummary.php',
        'src/Service/PerformanceDatasetSeeder.php',
        'src/Command/SeedPerformanceDatasetCommand.php',
        'src/Command/BenchmarkCapacityCommand.php',
        'src/Security/ActiveUserChecker.php',
        'src/Service/AttachmentManager.php',
        'src/Service/AuditedTransaction.php',
        'src/Service/TimerMutationLock.php',
        'src/Service/AttachmentMutationLock.php',
        'src/Service/DatabaseDataResetter.php',
        'src/Command/ResetDatabaseKeepingUsersCommand.php',
        'src/EventSubscriber/RequestIdSubscriber.php',
        'src/EventSubscriber/DatabaseExceptionSubscriber.php',
        'src/EventSubscriber/HttpExceptionSubscriber.php',
        'src/EventSubscriber/SecurityAuditSubscriber.php',
        'src/EventSubscriber/SecurityHeadersSubscriber.php',
        'src/Service/AuditPrivacyGuard.php',
        'templates/base.html.twig',
        'templates/layout/app.html.twig',
        'templates/authentication/login.html.twig',
        'templates/user/index.html.twig',
        'templates/audit/index.html.twig',
        'templates/bundles/TwigBundle/Exception/error500.html.twig',
        'templates/bundles/TwigBundle/Exception/error503.html.twig',
        'tests/bootstrap.php',
        'tests/Controller/AttachmentManagementTest.php',
        'tests/Controller/AuthorizationHardeningTest.php',
        'tests/Project/AuthorizationMatrixContractTest.php',
        'tests/Project/RobustnessContractTest.php',
        'tests/Project/PhpRuntimeContractTest.php',
        'tests/Project/PhpStanHotfixContractTest.php',
        'tests/Project/PhpStanContractLineEndingHotfixTest.php',
        'tests/Project/DashboardUiHotfixContractTest.php',
        'tests/Project/DashboardCurrentMonthContractTest.php',
        'tests/Controller/DashboardUiHotfixTest.php',
        'tests/Controller/RequestRobustnessTest.php',
        'tests/Controller/OperationalAuditTest.php',
        'tests/Service/AuditLoggerContextTest.php',
        'tests/Service/BackupManagerTest.php',
        'tests/Project/OperationalAuditContractTest.php',
        'tests/Controller/EndToEndWorkflowTest.php',
        'tests/Project/EndToEndWorkflowContractTest.php',
        'tests/Controller/MonthlyReportTest.php',
        'tests/Project/MonthlyUserCostReportContractTest.php',
        'tests/Project/DeploymentContractTest.php',
        'tests/Project/ApacheUpdateContractTest.php',
        'tests/Repository/DashboardRepositoryTest.php',
        'tests/Project/CapacityProfileTest.php',
        'tests/Project/PerformanceCapacityContractTest.php',
        'tests/Controller/LoginSecurityHardeningTest.php',
        'tests/Controller/AccessibilityUiTest.php',
        'tests/Service/AuditPrivacyGuardTest.php',
        'tests/Project/AccessibilitySecurityContractTest.php',
        'scripts/php-runtime-contract.php',
        'scripts/m92c-hotfix1-fileinfo-contract.php',
        'scripts/m92c-hotfix2-phpstan-contract.php',
        'scripts/m92c-hotfix3-contract.php',
        'scripts/m92c-hotfix4-dashboard-contract.php',
        'scripts/m92e2-dashboard-current-month-contract.php',
        'scripts/m92d-operational-audit-contract.php',
        'scripts/m92e-end-to-end-contract.php',
        'scripts/m92e1-monthly-user-cost-contract.php',
        'scripts/setup.ps1',
        'scripts/release-preflight.ps1',
        'scripts/update.ps1',
        'scripts/install-smoke.ps1',
        'scripts/m92f-deployment-contract.php',
        'scripts/m92f1-apache-update-contract.php',
        'scripts/benchmark-capacity.ps1',
        'scripts/m92g-performance-contract.php',
        'scripts/m92h-accessibility-security-contract.php',
        'scripts/validate.ps1',
        'scripts/clear-database-keep-users.ps1',
        'scripts/package-release.ps1',
        'scripts/verify-release-package.ps1',
        'scripts/m92b-authorization-contract.php',
        'scripts/m92c-robustness-contract.php',
        'docs/PACKAGING.md',
        'docs/PERMISSIONS.md',
        'docs/AUTHORIZATION_MATRIX.md',
        'docs/ROBUSTNESS.md',
        'docs/OPERATIONAL_AUDIT.md',
        'docs/END_TO_END_FLOWS.md',
        'docs/MONTHLY_REPORT.md',
        'docs/INSTALL_UPDATE.md',
        'docs/APACHE.md',
        'docs/PERFORMANCE.md',
        'docs/CAPACITY_BENCHMARK.md',
        'docs/USER_MANUAL.md',
        'docs/ADMIN_MANUAL.md',
        'docs/SECURITY.md',
        'docs/ACCESSIBILITY.md'
    )

    foreach ($required in $requiredFiles) {
        $entry = $ZipArchive.GetEntry($required)
        if ($null -eq $entry) {
            throw "Il pacchetto non contiene il file obbligatorio: $required"
        }
        if ($entry.Length -le 0) {
            throw "Il pacchetto contiene un file critico vuoto: $required"
        }
    }

    $requiredGroups = @(
        @{ Description = 'entità'; Pattern = '^src/Entity/.+\.php$' },
        @{ Description = 'repository'; Pattern = '^src/Repository/.+\.php$' },
        @{ Description = 'sicurezza'; Pattern = '^src/Security/.+\.php$' },
        @{ Description = 'servizi'; Pattern = '^src/Service/.+\.php$' },
        @{ Description = 'template'; Pattern = '^templates/.+\.twig$' },
        @{ Description = 'test'; Pattern = '^tests/.+\.php$' },
        @{ Description = 'migrazioni'; Pattern = '^migrations/.+\.php$' }
    )

    foreach ($group in $requiredGroups) {
        $matches = @($EntryNames | Where-Object { $_ -match $group.Pattern })
        if ($matches.Count -eq 0) {
            throw "Il pacchetto non contiene alcun file della famiglia obbligatoria: $($group.Description)"
        }
    }
}

if (-not [System.IO.Path]::IsPathRooted($Archive)) {
    $Archive = Join-Path $projectRoot $Archive
}
$archivePath = [System.IO.Path]::GetFullPath($Archive)
if (-not (Test-Path -LiteralPath $archivePath -PathType Leaf)) {
    throw "Pacchetto di distribuzione non trovato: $archivePath"
}
if (-not $archivePath.EndsWith('.zip', [System.StringComparison]::OrdinalIgnoreCase)) {
    throw "Il pacchetto da verificare deve avere estensione .zip."
}

if ([string]::IsNullOrWhiteSpace($SourceRoot)) {
    $SourceRoot = $projectRoot
} elseif (-not [System.IO.Path]::IsPathRooted($SourceRoot)) {
    $SourceRoot = Join-Path $projectRoot $SourceRoot
}
$sourcePath = [System.IO.Path]::GetFullPath($SourceRoot)
if (-not (Test-Path -LiteralPath $sourcePath -PathType Container)) {
    throw "Sorgente di confronto non trovata: $sourcePath"
}

Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem
$zip = [System.IO.Compression.ZipFile]::OpenRead($archivePath)
try {
    $entryNames = @($zip.Entries | ForEach-Object { $_.FullName })
    if ($entryNames.Count -eq 0) {
        throw "Il pacchetto è vuoto."
    }

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

    Assert-RequiredReleaseInventory -ZipArchive $zip -EntryNames $entryNames

    $expectedNames = @(Get-ReleasableFiles -Directory $sourcePath | ForEach-Object { $_.Relative } | Sort-Object)
    $actualNames = @($entryNames | Sort-Object)
    $missing = @($expectedNames | Where-Object { $actualNames -cnotcontains $_ })
    $unexpected = @($actualNames | Where-Object { $expectedNames -cnotcontains $_ })
    if ($missing.Count -gt 0 -or $unexpected.Count -gt 0) {
        $missingPreview = ($missing | Select-Object -First 10) -join ', '
        $unexpectedPreview = ($unexpected | Select-Object -First 10) -join ', '
        throw "Inventario pacchetto diverso dal sorgente distribuibile. Mancanti: [$missingPreview]. Inattesi: [$unexpectedPreview]."
    }
} finally {
    $zip.Dispose()
}

Write-Host "Pacchetto di distribuzione completo e coerente:" -ForegroundColor Green
Write-Host $archivePath
