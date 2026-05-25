# Rebuild player_monthly_league on local ko2unity_db from ratedresults.
# Usage: powershell -ExecutionPolicy Bypass -File scripts\rebuild_player_monthly_league_local.ps1

param(
    [string]$Database = 'ko2unity_db',
    [string]$User = 'root',
    [string]$Password = '',
    [switch]$AllowNonLocal
)

$ErrorActionPreference = 'Stop'
$RepoRoot = Resolve-Path (Join-Path $PSScriptRoot '..')
$SqlFile = Join-Path $RepoRoot 'scripts\ladder\sql\player_monthly_league_rebuild.sql'
$MysqlExe = 'C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe'

if (-not (Test-Path $MysqlExe)) {
    Write-Error "mysql.exe not found at $MysqlExe (start Laragon - docs/LOCAL_DEV.md)."
}

if (-not (Test-Path $SqlFile)) {
    Write-Error "SQL rebuild file not found at $SqlFile"
}

if ($Database -ne 'ko2unity_db' -and -not $AllowNonLocal) {
    Write-Error "Refusing to rebuild player_monthly_league on '$Database'. Use -AllowNonLocal only for an explicitly reviewed one-off."
}

$mysqlArgs = @('-u', $User, $Database)
if ($Password -ne '') {
    $mysqlArgs = @('-u', $User, "-p$Password", $Database)
}

Write-Host "Rebuilding player_monthly_league on $Database..." -ForegroundColor Cyan
$identity = & $MysqlExe @mysqlArgs -N -e "SELECT DATABASE(), CURRENT_USER(), @@hostname, @@port, VERSION();"
if ($LASTEXITCODE -ne 0) {
    Write-Error 'Could not verify database identity.'
}
Write-Host "DB identity: $identity" -ForegroundColor DarkCyan
Write-Host 'This rebuild truncates and repopulates player_monthly_league.' -ForegroundColor Yellow
Get-Content -Raw -LiteralPath $SqlFile | & $MysqlExe @mysqlArgs
if ($LASTEXITCODE -ne 0) {
    Write-Error "player_monthly_league rebuild failed (exit $LASTEXITCODE)."
}

$counts = & $MysqlExe @mysqlArgs -N -e "SELECT COUNT(*) AS row_count, SUM(played) AS appearances, SUM(played) / 2 AS rated_games FROM player_monthly_league;"
if ($LASTEXITCODE -ne 0) {
    Write-Error 'Could not verify player_monthly_league counts.'
}

Write-Host $counts
Write-Host '[OK] player_monthly_league rebuilt.' -ForegroundColor Green
