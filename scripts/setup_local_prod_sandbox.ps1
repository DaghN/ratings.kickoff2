# Create prod-shaped sandbox: ko2unity_baseline + ko2unity_work.
# Does NOT touch ko2unity_db (browser/dev). Does NOT change PHP config.
#
# Usage (repo root, Laragon MySQL running):
#   powershell -ExecutionPolicy Bypass -File scripts\setup_local_prod_sandbox.ps1
#
# Optional:
#   -SkipExtract              dump already in data/dumps/
#   -SkipImport               baseline already imported
#   -ApplyMigrationsToWork    run schema/apply_local.ps1 on ko2unity_work (off by default)

param(
    [switch]$SkipExtract,
    [switch]$SkipImport,
    [switch]$ApplyMigrationsToWork
)

$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'lib\LaragonMysql.ps1')
. (Join-Path $PSScriptRoot 'lib\ProdDumpSanitize.ps1')
$RepoRoot = Get-RepoRoot

$MysqlExe = Find-LaragonMysqlExe
$DumpExe = Find-LaragonMysqldumpExe
if (-not $MysqlExe -or -not $DumpExe) {
    Write-Error "Laragon mysql/mysqldump not found. Open Laragon and Start All."
}

$DumpFile = Join-Path $RepoRoot 'data\dumps\ko2unity_prod-2026-06-02.sql'
$Baseline = 'ko2unity_baseline'
$Work = 'ko2unity_work'
$DevDb = 'ko2unity_db'
$ProtectedDatabases = @($DevDb)

function Assert-DatabaseMayDrop {
    param([string]$Name)
    if ($Name -in $ProtectedDatabases) {
        Write-Error "SAFETY: refusing to DROP or replace protected database '$Name' (local dev / browser)."
    }
}

function Invoke-Mysql {
    param([string]$Sql)
    & $MysqlExe -u root -e $Sql
    if ($LASTEXITCODE -ne 0) { Write-Error "mysql failed: $Sql" }
}

function Import-SanitizedDumpToBaseline {
    param([string]$SourceSql, [string]$TargetDb)

    Assert-DatabaseMayDrop -Name $TargetDb
    if ($TargetDb -ne 'ko2unity_baseline') {
        Write-Error "Import helper only supports ko2unity_baseline."
    }

    if (-not (Test-ProdDumpSanitized -Path $SourceSql)) {
        Write-Error @"
Dump file is not sanitized (still CREATE DATABASE ko2unity_db?).
  powershell -File scripts\sanitize_prod_dump.ps1
  or: powershell -File scripts\extract_prod_dump.ps1 -Force
"@
    }

    Write-Host "Importing sanitized dump into '$TargetDb' (10-25 min, do not close)..." -ForegroundColor Cyan
    Invoke-Mysql "DROP DATABASE IF EXISTS ``$TargetDb``;"
    $dumpForCmd = $SourceSql -replace '/', '\'
    cmd /c "`"$MysqlExe`" -u root --default-character-set=utf8mb4 < `"$dumpForCmd`""
    if ($LASTEXITCODE -ne 0) { Write-Error "mysql import exited with $LASTEXITCODE" }
    Write-Host "[OK] Baseline import finished." -ForegroundColor Green
}

function Copy-Database {
    param([string]$From, [string]$To)
    Assert-DatabaseMayDrop -Name $To
    if ($From -eq $DevDb -or $To -eq $DevDb) {
        Write-Error "SAFETY: clone path cannot involve $DevDb."
    }
    Write-Host "Cloning '$From' -> '$To'..." -ForegroundColor Cyan
    Invoke-Mysql "DROP DATABASE IF EXISTS ``$To``; CREATE DATABASE ``$To`` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"
    & $DumpExe -u root --single-transaction --no-create-db --routines --events $From | & $MysqlExe -u root $To
    if ($LASTEXITCODE -ne 0) { Write-Error "Clone $From -> $To failed." }
}

Write-Host "=== Local prod sandbox setup (dev DB untouched) ===" -ForegroundColor Cyan
Write-Host "  Protected: $DevDb (browser / PHP config stays on this database)"
Write-Host "  Creating:  $Baseline (pristine prod) + $Work (experiments)"
Write-Host ""

$devExists = & $MysqlExe -u root -N -e "SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='$DevDb';"
if ($devExists -eq '1') {
    $devGames = & $MysqlExe -u root -N -e "SELECT COUNT(*) FROM ``$DevDb``.ratedresults;"
    Write-Host "[OK] Dev database '$DevDb' present - ratedresults: $devGames (will not be modified)" -ForegroundColor Green
} else {
    Write-Host "[!!] Dev database '$DevDb' not found - sandbox setup continues; restore dev separately if needed." -ForegroundColor Yellow
}

if (-not $SkipExtract) {
    & (Join-Path $PSScriptRoot 'extract_prod_dump.ps1')
}

if (-not (Test-Path $DumpFile)) {
    Write-Error "Dump not found: $DumpFile. Place KOOL_DB_Live.zip in Downloads and re-run."
}

if (-not $SkipImport) {
    Import-SanitizedDumpToBaseline -SourceSql $DumpFile -TargetDb $Baseline
}

$baseCheck = & $MysqlExe -u root -N -e "SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='$Baseline';"
if ($baseCheck -ne '1') {
    Write-Error "Baseline '$Baseline' missing after import."
}

Copy-Database -From $Baseline -To $Work

if ($ApplyMigrationsToWork) {
    Write-Host "Applying schema migrations to work DB only..." -ForegroundColor Cyan
    & powershell -ExecutionPolicy Bypass -File (Join-Path $RepoRoot 'schema\apply_local.ps1') -Database $Work
} else {
    Write-Host "Skipping migrations on work (pass -ApplyMigrationsToWork when expand schema is ready)." -ForegroundColor DarkCyan
}

foreach ($db in @($Baseline, $Work)) {
    $games = & $MysqlExe -u root -N -e "SELECT COUNT(*) FROM ``$db``.ratedresults;"
    $tables = & $MysqlExe -u root -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$db';"
    Write-Host "[OK] $db - tables: $tables, ratedresults: $games" -ForegroundColor Green
}

Write-Host ""
Write-Host "Sandbox setup complete." -ForegroundColor Green
Write-Host "  Browser / PHP: still $DevDb (unchanged)"
Write-Host "  Ladder/sim on work: site/config/ladder-work.ini + python -m scripts.ladder ... --target sandbox --ini site/config/ladder-work.ini"
Write-Host "  Reset work: scripts\reset_local_work_db.ps1"
Write-Host "  Docs: docs/coordination/database-copies-2026-06.md"
