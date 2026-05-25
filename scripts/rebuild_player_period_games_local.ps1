# Rebuild player_period_games on local ko2unity_db from ratedresults.
# Usage: powershell -ExecutionPolicy Bypass -File scripts\rebuild_player_period_games_local.ps1

param(
    [string]$Database = 'ko2unity_db',
    [string]$User = 'root',
    [string]$Password = ''
)

$ErrorActionPreference = 'Stop'
$RepoRoot = Resolve-Path (Join-Path $PSScriptRoot '..')
$SqlFile = Join-Path $RepoRoot 'scripts\ladder\sql\player_period_games_rebuild.sql'
$MysqlExe = 'C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe'

if (-not (Test-Path $MysqlExe)) {
    Write-Error "mysql.exe not found at $MysqlExe (start Laragon - docs/LOCAL_DEV.md)."
}

if (-not (Test-Path $SqlFile)) {
    Write-Error "SQL rebuild file not found at $SqlFile"
}

$mysqlArgs = @('-u', $User, $Database)
if ($Password -ne '') {
    $mysqlArgs = @('-u', $User, "-p$Password", $Database)
}

Write-Host "Rebuilding player_period_games on $Database..." -ForegroundColor Cyan
Get-Content -Raw -LiteralPath $SqlFile | & $MysqlExe @mysqlArgs
if ($LASTEXITCODE -ne 0) {
    Write-Error "player_period_games rebuild failed (exit $LASTEXITCODE)."
}

$counts = & $MysqlExe @mysqlArgs -N -e "SELECT period_type, COUNT(*) AS row_count, SUM(games) AS appearances FROM player_period_games GROUP BY period_type ORDER BY FIELD(period_type, 'day', 'month', 'year');"
if ($LASTEXITCODE -ne 0) {
    Write-Error 'Could not verify player_period_games counts.'
}

Write-Host $counts
Write-Host '[OK] player_period_games rebuilt.' -ForegroundColor Green
