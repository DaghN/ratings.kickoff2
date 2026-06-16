# Activity wing — one-shot dev DB fill (ko2unity_db only).
# Schema SCH-022/023/024 + rebuild participation + play streaks (incl. month/year HoF GST).
# No ops simul — assumes player_period_games already populated (e.g. prior full rebuild).
#
#   powershell -ExecutionPolicy Bypass -File scripts\rebuild_activity_wing_local.ps1
#
# Prerequisite: Laragon MySQL running; browser site uses ko2unity_db via ko2unitydb_config.local.php

param(
    [string]$Database = 'ko2unity_db',
    [string]$User = 'root',
    [string]$Password = '',
    [switch]$SkipMigrations,
    [switch]$AllowNonLocal
)

$ErrorActionPreference = 'Stop'
$RepoRoot = Resolve-Path (Join-Path $PSScriptRoot '..')
$MigrationsDir = Join-Path $RepoRoot 'site\public_html\ops\sql\migrations'
$OneOffSqlDir = Join-Path $RepoRoot 'scripts\ladder\sql\archive\one-off-2026-06'
$MysqlExe = 'C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe'
$PhpExe = 'C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe'

if (-not (Test-Path $MysqlExe)) {
    Get-ChildItem 'C:\laragon\bin\mysql\*\bin\mysql.exe' | Select-Object -First 1 | ForEach-Object { $MysqlExe = $_.FullName }
}
if (-not (Test-Path $MysqlExe)) {
    Write-Error 'mysql.exe not found (start Laragon).'
}

if ($Database -ne 'ko2unity_db' -and -not $AllowNonLocal) {
    Write-Error "Refusing on '$Database'. Dev UI fill is ko2unity_db only; use ops simul on work DB."
}

if (-not (Test-Path $PhpExe)) {
    Get-ChildItem 'C:\laragon\bin\php\*\php.exe' | Select-Object -First 1 | ForEach-Object { $PhpExe = $_.FullName }
}

$mysqlArgs = @('-u', $User, $Database)
if ($Password -ne '') {
    $mysqlArgs = @('-u', $User, "-p$Password", $Database)
}

function Invoke-MysqlFile {
    param([string]$Path, [string]$Label)

    if (-not (Test-Path $Path)) {
        Write-Error "Missing SQL: $Path"
    }
    Write-Host "-> $Label" -ForegroundColor Cyan
    Get-Content -Raw -LiteralPath $Path | & $MysqlExe @mysqlArgs
    if ($LASTEXITCODE -ne 0) {
        Write-Error "$Label failed (exit $LASTEXITCODE)."
    }
}

function Invoke-MysqlScalar {
    param([string]$Sql)

    $result = & $MysqlExe @mysqlArgs -N -e $Sql
    if ($LASTEXITCODE -ne 0) {
        Write-Error 'MySQL query failed.'
    }
    return [int](($result | Select-Object -First 1).ToString().Trim())
}

Write-Host "Activity wing dev fill on $Database..." -ForegroundColor Cyan

$periodRows = Invoke-MysqlScalar "SET time_zone = '+00:00'; SELECT COUNT(*) FROM player_period_games WHERE period_type = 'day';"
if ($periodRows -le 0) {
    Write-Error 'player_period_games is empty. Run scripts\rebuild_website_derived_data_local.ps1 first (or ensure period aggregates exist).'
}
Write-Host "player_period_games day rows: $periodRows" -ForegroundColor DarkCyan

if (-not $SkipMigrations) {
    foreach ($name in @(
        '022_player_activity_participation.sql',
        '023_play_streaks_month_year.sql',
        '024_player_play_streaks_best_anchor_start.sql'
    )) {
        $path = Join-Path $MigrationsDir $name
        Invoke-MysqlFile $path "migrate $name"
    }
} else {
    Write-Host '-> skip migrations (-SkipMigrations)' -ForegroundColor DarkYellow
}

Invoke-MysqlFile (Join-Path $OneOffSqlDir 'player_activity_participation_rebuild.sql') 'player_activity_participation rebuild'

if (-not (Test-Path $PhpExe)) {
    Write-Error 'php.exe not found — cannot rebuild player_play_streaks.'
}
Write-Host '-> player_play_streaks + HoF GST (PHP rebuild)' -ForegroundColor Cyan
& $PhpExe (Join-Path $RepoRoot 'scripts\rebuild_player_play_streaks.php')
if ($LASTEXITCODE -ne 0) {
    Write-Error 'rebuild_player_play_streaks.php failed.'
}

Write-Host 'Running Activity wing parity spot checks...' -ForegroundColor Cyan
$daySum = Invoke-MysqlScalar "SET time_zone = '+00:00'; SELECT COALESCE(SUM(active_days),0) FROM player_activity_participation;"
$dayPeriod = Invoke-MysqlScalar "SET time_zone = '+00:00'; SELECT COUNT(*) FROM player_period_games WHERE period_type = 'day';"
if ($daySum -ne $dayPeriod) {
    Write-Error "Participation parity failed: SUM(active_days)=$daySum period_day_rows=$dayPeriod"
}

$perPlayerBad = Invoke-MysqlScalar @"
SET time_zone = '+00:00';
SELECT COUNT(*) FROM player_activity_participation p WHERE
  p.active_days <> (SELECT COUNT(*) FROM player_period_games g WHERE g.player_id = p.player_id AND g.period_type = 'day');
"@
if ($perPlayerBad -ne 0) {
    Write-Error "Per-player participation mismatches: $perPlayerBad"
}

$streakTypes = Invoke-MysqlScalar "SELECT COUNT(DISTINCT streak_type) FROM player_play_streaks;"
if ($streakTypes -lt 4) {
    Write-Error "Expected 4 streak_type values in player_play_streaks; got $streakTypes"
}

$hofDay = Invoke-MysqlScalar "SELECT COALESCE(LongestDailyPlayStreak,0) FROM generalstatstable WHERE id=1;"
$tableMaxDay = Invoke-MysqlScalar "SELECT COALESCE(MAX(best_streak),0) FROM player_play_streaks WHERE streak_type='day';"
if ($hofDay -ne $tableMaxDay) {
    Write-Error "HoF day streak ($hofDay) != table max ($tableMaxDay)"
}

Write-Host "[OK] Activity wing ready on $Database (participation rows match period games; streak types=$streakTypes; HoF day=$hofDay)." -ForegroundColor Green
