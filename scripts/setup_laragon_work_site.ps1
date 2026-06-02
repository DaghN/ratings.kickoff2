# One-time: hosts entry for work.ratingskickoff.test + migrate legacy PHP DB config.
# Apache: Laragon auto.ratingskickoff.test.conf already has ServerAlias *.ratingskickoff.test
# (same public_html - no extra vhost file required).
#
# Usage:
#   powershell -ExecutionPolicy Bypass -File scripts\setup_laragon_work_site.ps1
#
# If hosts update fails, re-run as Administrator or add manually:
#   127.0.0.1 work.ratingskickoff.test

$ErrorActionPreference = 'Stop'
$RepoRoot = Split-Path -Parent $PSScriptRoot
$ConfigDir = Join-Path $RepoRoot 'site\config'
$RouterPath = Join-Path $ConfigDir 'ko2unitydb_config.php'
$LocalPath = Join-Path $ConfigDir 'ko2unitydb_config.local.php'
$WorkPath = Join-Path $ConfigDir 'ko2unitydb_config_work.local.php'
$LocalExample = Join-Path $ConfigDir 'ko2unitydb_config.local.php.example'
$WorkExample = Join-Path $ConfigDir 'ko2unitydb_config_work.local.php.example'

function Test-ConfigIsRouter {
    param([string]$Path)
    if (-not (Test-Path -LiteralPath $Path)) { return $false }
    $content = Get-Content -LiteralPath $Path -Raw
    return ($content -match 'ko2unitydb_config\.local\.php') -and ($content -match 'HTTP_HOST')
}

function Test-ConfigIsLegacyCredentials {
    param([string]$Path)
    if (-not (Test-Path -LiteralPath $Path)) { return $false }
    if (Test-ConfigIsRouter -Path $Path) { return $false }
    $content = Get-Content -LiteralPath $Path -Raw
    return $content -match '\$database\s*='
}

Write-Host '=== Laragon work site setup ===' -ForegroundColor Cyan
Write-Host "Repo: $RepoRoot"
Write-Host ''

# Legacy: single gitignored ko2unitydb_config.php with credentials -> .local.php
if ((Test-Path -LiteralPath $RouterPath) -and (Test-ConfigIsLegacyCredentials -Path $RouterPath)) {
    if (-not (Test-Path -LiteralPath $LocalPath)) {
        Move-Item -LiteralPath $RouterPath -Destination $LocalPath
        Write-Host '[OK] Moved legacy ko2unitydb_config.php -> ko2unitydb_config.local.php' -ForegroundColor Green
    } else {
        Write-Host '[!!] Legacy ko2unitydb_config.php found but .local.php already exists - rename or merge manually.' -ForegroundColor Yellow
    }
}

if (-not (Test-Path -LiteralPath $RouterPath)) {
    Write-Error "Missing router: $RouterPath (git pull repo)"
}
if (-not (Test-ConfigIsRouter -Path $RouterPath)) {
    Write-Error "ko2unitydb_config.php is not the host router - restore from repo."
}

if (-not (Test-Path -LiteralPath $LocalPath)) {
    if (-not (Test-Path -LiteralPath $LocalExample)) {
        Write-Error "Missing $LocalExample"
    }
    Copy-Item -LiteralPath $LocalExample -Destination $LocalPath
    Write-Host '[OK] Created ko2unitydb_config.local.php from example (dev DB)' -ForegroundColor Green
} else {
    Write-Host '[OK] ko2unitydb_config.local.php exists' -ForegroundColor Green
}

if (-not (Test-Path -LiteralPath $WorkPath)) {
    if (-not (Test-Path -LiteralPath $WorkExample)) {
        Write-Error "Missing $WorkExample"
    }
    Copy-Item -LiteralPath $WorkExample -Destination $WorkPath
    Write-Host '[OK] Created ko2unitydb_config_work.local.php from example (work DB)' -ForegroundColor Green
} else {
    Write-Host '[OK] ko2unitydb_config_work.local.php exists' -ForegroundColor Green
}

# Hosts
$hostsFile = Join-Path $env:SystemRoot 'System32\drivers\etc\hosts'
$hostsLine = '127.0.0.1 work.ratingskickoff.test'
$hostsContent = Get-Content -LiteralPath $hostsFile -ErrorAction Stop
if ($hostsContent -match '(?im)^\s*127\.0\.0\.1\s+work\.ratingskickoff\.test\s*$') {
    Write-Host '[OK] hosts already has work.ratingskickoff.test' -ForegroundColor Green
} else {
    try {
        Add-Content -LiteralPath $hostsFile -Value "`n$hostsLine`n" -Encoding ascii
        Write-Host "[OK] Added to hosts: $hostsLine" -ForegroundColor Green
    } catch {
        Write-Host "[!!] Could not write hosts file (try Administrator): $($_.Exception.Message)" -ForegroundColor Yellow
        Write-Host "     Add manually: $hostsLine"
    }
}

Write-Host ''
Write-Host 'URLs (Laragon Start All):' -ForegroundColor Cyan
Write-Host '  Dev:  http://ratingskickoff.test/       -> ko2unity_db'
Write-Host '  Work: http://work.ratingskickoff.test/  -> ko2unity_work'
Write-Host ''
Write-Host 'If work URL fails, restart Laragon (Apache) and confirm hosts entry.'
