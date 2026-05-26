# Rebuild player_period_games on local ko2unity_db from ratedresults.
# Usage: powershell -ExecutionPolicy Bypass -File scripts\rebuild_player_period_games_local.ps1

param(
    [string]$Database = 'ko2unity_db',
    [string]$User = 'root',
    [string]$Password = '',
    [switch]$AllowNonLocal
)

$ErrorActionPreference = 'Stop'
$RepoRoot = Resolve-Path (Join-Path $PSScriptRoot '..')
$SqlFile = Join-Path $RepoRoot 'scripts\ladder\sql\player_period_games_rebuild.sql'
$PeakSqlFile = Join-Path $RepoRoot 'scripts\ladder\sql\player_peak_period_games_rebuild.sql'
$MysqlExe = 'C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe'

if (-not (Test-Path $MysqlExe)) {
    Write-Error "mysql.exe not found at $MysqlExe (start Laragon - docs/LOCAL_DEV.md)."
}

if (-not (Test-Path $SqlFile)) {
    Write-Error "SQL rebuild file not found at $SqlFile"
}
if (-not (Test-Path $PeakSqlFile)) {
    Write-Error "SQL peak rebuild file not found at $PeakSqlFile"
}

if ($Database -ne 'ko2unity_db' -and -not $AllowNonLocal) {
    Write-Error "Refusing to rebuild player_period_games on '$Database'. Use -AllowNonLocal only for an explicitly reviewed one-off."
}

$mysqlArgs = @('-u', $User, $Database)
if ($Password -ne '') {
    $mysqlArgs = @('-u', $User, "-p$Password", $Database)
}

Write-Host "Rebuilding player_period_games on $Database..." -ForegroundColor Cyan
$identity = & $MysqlExe @mysqlArgs -N -e "SELECT DATABASE(), CURRENT_USER(), @@hostname, @@port, VERSION();"
if ($LASTEXITCODE -ne 0) {
    Write-Error 'Could not verify database identity.'
}
Write-Host "DB identity: $identity" -ForegroundColor DarkCyan
Write-Host 'This rebuild truncates and repopulates player_period_games and player_peak_period_games.' -ForegroundColor Yellow
Get-Content -Raw -LiteralPath $SqlFile | & $MysqlExe @mysqlArgs
if ($LASTEXITCODE -ne 0) {
    Write-Error "player_period_games rebuild failed (exit $LASTEXITCODE)."
}
Get-Content -Raw -LiteralPath $PeakSqlFile | & $MysqlExe @mysqlArgs
if ($LASTEXITCODE -ne 0) {
    Write-Error "player_peak_period_games rebuild failed (exit $LASTEXITCODE)."
}

$counts = & $MysqlExe @mysqlArgs -N -e "SELECT period_type, COUNT(*) AS row_count, SUM(games) AS appearances FROM player_period_games GROUP BY period_type ORDER BY FIELD(period_type, 'day', 'week', 'month', 'year');"
if ($LASTEXITCODE -ne 0) {
    Write-Error 'Could not verify player_period_games counts.'
}

Write-Host $counts
$peakCounts = & $MysqlExe @mysqlArgs -N -e "SELECT period_type, COUNT(*) AS peak_rows FROM player_peak_period_games GROUP BY period_type ORDER BY FIELD(period_type, 'day', 'week', 'month', 'year');"
if ($LASTEXITCODE -ne 0) {
    Write-Error 'Could not verify player_peak_period_games counts.'
}

Write-Host $peakCounts
Write-Host '[OK] player_period_games and player_peak_period_games rebuilt.' -ForegroundColor Green
