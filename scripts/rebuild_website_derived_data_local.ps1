# DEPRECATED for cutover — dev repair on ko2unity_db only.
# Happy path: php site/public_html/ops/run_ops_sim.php run --target local-work
# See: docs/coordination/cutover-readiness.md, docs/coordination/ops-simul-runbook.md
#
# Rebuild all website-owned derived data on local ko2unity_db (batch SQL chain).
# Usage: powershell -ExecutionPolicy Bypass -File scripts\rebuild_website_derived_data_local.ps1
# Contract: docs/website-data-contract.md

param(
    [string]$Database = 'ko2unity_db',
    [string]$User = 'root',
    [string]$Password = '',
    [switch]$AllowNonLocal
)

$ErrorActionPreference = 'Stop'
$RepoRoot = Resolve-Path (Join-Path $PSScriptRoot '..')
$SqlDir = Join-Path $RepoRoot 'scripts\ladder\sql\archive\batch-2026-05'
$MysqlExe = 'C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe'

if (-not (Test-Path $MysqlExe)) {
    Write-Error "mysql.exe not found at $MysqlExe (start Laragon - docs/LOCAL_DEV.md)."
}

if ($Database -ne 'ko2unity_db' -and -not $AllowNonLocal) {
    Write-Error "Refusing to rebuild website derived data on '$Database'. Use -AllowNonLocal only for an explicitly reviewed one-off."
}

$mysqlArgs = @('-u', $User, $Database)
if ($Password -ne '') {
    $mysqlArgs = @('-u', $User, "-p$Password", $Database)
}

function Invoke-MysqlQuery {
    param([string]$Sql, [string]$Label)

    $result = & $MysqlExe @mysqlArgs -N -e $Sql
    if ($LASTEXITCODE -ne 0) {
        Write-Error "$Label failed (exit $LASTEXITCODE)."
    }

    return $result
}

function Invoke-RebuildSql {
    param([string]$FileName, [string]$Label)

    $path = Join-Path $SqlDir $FileName
    if (-not (Test-Path $path)) {
        Write-Error "SQL rebuild file not found: $path"
    }

    Write-Host "-> $Label ($FileName)" -ForegroundColor Cyan
    Get-Content -Raw -LiteralPath $path | & $MysqlExe @mysqlArgs
    if ($LASTEXITCODE -ne 0) {
        Write-Error "$Label failed (exit $LASTEXITCODE)."
    }
}

Write-Host "Rebuilding website derived data on $Database..." -ForegroundColor Cyan
$identity = Invoke-MysqlQuery "SELECT DATABASE(), CURRENT_USER(), @@hostname, @@port, VERSION(), @@session.time_zone;" 'DB identity check'
Write-Host "DB identity: $identity" -ForegroundColor DarkCyan
Write-Host 'This rebuild truncates and repopulates project-owned derived tables.' -ForegroundColor Yellow

$steps = @(
    @{ File = 'player_period_games_rebuild.sql'; Label = 'player_period_games' },
    @{ File = 'player_peak_period_games_rebuild.sql'; Label = 'player_peak_period_games' },
    @{ File = 'server_daily_activity_rebuild.sql'; Label = 'server_daily_activity' },
    @{ File = 'player_period_league_rebuild.sql'; Label = 'player_period_league' },
    @{ File = 'player_matchup_summary_rebuild.sql'; Label = 'player_matchup_summary' },
    @{ File = 'server_period_game_totals_rebuild.sql'; Label = 'server_period_game_totals' },
    @{ File = 'server_period_matchups_rebuild.sql'; Label = 'server_period_matchups' }
)

foreach ($step in $steps) {
    Invoke-RebuildSql $step.File $step.Label
}

Write-Host 'Running parity checks...' -ForegroundColor Cyan

$globalCounts = Invoke-MysqlQuery @"
SET time_zone = '+00:00';
SELECT 'ratedresults', COUNT(*) FROM ratedresults
UNION ALL SELECT 'player_period_games day / 2', SUM(games) / 2 FROM player_period_games WHERE period_type = 'day'
UNION ALL SELECT 'player_period_league day / 2', SUM(played) / 2 FROM player_period_league WHERE period_type = 'day'
UNION ALL SELECT 'server_daily_activity', SUM(rated_games) FROM server_daily_activity
UNION ALL SELECT 'server_period_game_totals day', SUM(rated_games) FROM server_period_game_totals WHERE period_type = 'day'
UNION ALL SELECT 'server_period_matchups day', SUM(games) FROM server_period_matchups WHERE period_type = 'day';
"@ 'global parity checks'
Write-Host $globalCounts

$matchups = Invoke-MysqlQuery @"
SET time_zone = '+00:00';
SELECT s.ym, s.unique_pairs AS stored_pairs, r.unique_pairs AS raw_pairs, s.unique_pairs - r.unique_pairs AS diff
FROM (
  SELECT DATE_FORMAT(period_start, '%Y-%m') AS ym, COUNT(*) AS unique_pairs
  FROM server_period_matchups
  WHERE period_type = 'month'
  GROUP BY ym
  ORDER BY ym DESC
  LIMIT 5
) s
JOIN (
  SELECT DATE_FORMAT(`Date`, '%Y-%m') AS ym,
         COUNT(DISTINCT CONCAT(LEAST(idA, idB), '-', GREATEST(idA, idB))) AS unique_pairs
  FROM ratedresults
  GROUP BY ym
) r ON r.ym = s.ym
ORDER BY s.ym DESC;
"@ 'matchup breadth parity check'
Write-Host $matchups

$badGlobal = Invoke-MysqlQuery @"
SET time_zone = '+00:00';
SELECT COUNT(DISTINCT games) FROM (
  SELECT COUNT(*) AS games FROM ratedresults
  UNION ALL SELECT SUM(games) / 2 FROM player_period_games WHERE period_type = 'day'
  UNION ALL SELECT SUM(played) / 2 FROM player_period_league WHERE period_type = 'day'
  UNION ALL SELECT SUM(rated_games) FROM server_daily_activity
  UNION ALL SELECT SUM(rated_games) FROM server_period_game_totals WHERE period_type = 'day'
  UNION ALL SELECT SUM(games) FROM server_period_matchups WHERE period_type = 'day'
) checks;
"@ 'global parity comparison'
$badGlobalValue = [int](($badGlobal | Select-Object -First 1).ToString().Trim())
if ($badGlobalValue -ne 1) {
    Write-Error 'Global parity check failed: derived totals do not all match ratedresults.'
}

$badMatchups = Invoke-MysqlQuery @"
SET time_zone = '+00:00';
SELECT COALESCE(SUM(ABS(diff)), 0)
FROM (
  SELECT s.ym, s.unique_pairs - r.unique_pairs AS diff
  FROM (
    SELECT DATE_FORMAT(period_start, '%Y-%m') AS ym, COUNT(*) AS unique_pairs
    FROM server_period_matchups
    WHERE period_type = 'month'
    GROUP BY ym
    ORDER BY ym DESC
    LIMIT 5
  ) s
  JOIN (
    SELECT DATE_FORMAT(`Date`, '%Y-%m') AS ym,
           COUNT(DISTINCT CONCAT(LEAST(idA, idB), '-', GREATEST(idA, idB))) AS unique_pairs
    FROM ratedresults
    GROUP BY ym
  ) r ON r.ym = s.ym
) recent_months;
"@ 'matchup parity comparison'
$badMatchupsValue = [int](($badMatchups | Select-Object -First 1).ToString().Trim())
if ($badMatchupsValue -ne 0) {
    Write-Error 'Matchup breadth parity check failed for recent months.'
}

Write-Host '-> league_period_awards (REP-012 via PHP)' -ForegroundColor Cyan
$PhpExe = 'C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe'
if (-not (Test-Path $PhpExe)) {
    Get-ChildItem 'C:\laragon\bin\php\*\php.exe' | Select-Object -First 1 | ForEach-Object { $PhpExe = $_.FullName }
}
if (Test-Path $PhpExe) {
    & $PhpExe (Join-Path $RepoRoot 'site\public_html\ops\run_finalize_league.php') 'rebuild-all' '--target' 'local-dev'
    if ($LASTEXITCODE -ne 0) {
        Write-Error 'League awards rebuild failed.'
    }
} else {
    Write-Warning 'php.exe not found — skip REP-012; run scripts\run_league_awards_rebuild.ps1 later.'
}

$milestonesCore = Get-Content -Raw (Join-Path $SqlDir 'player_milestones_rebuild.sql')
$milestonesExists = Get-Content -Raw (Join-Path $SqlDir 'player_milestones_rebuild_exists.sql')
$milestonesStreaks = Get-Content -Raw (Join-Path $SqlDir 'player_milestones_rebuild_streaks.sql')
$milestonesChrono = Get-Content -Raw (Join-Path $SqlDir 'player_milestones_rebuild_chrono.sql')
$milestonesTail = Get-Content -Raw (Join-Path $SqlDir 'player_milestones_rebuild_tail.sql')
$milestonesPeriod = Get-Content -Raw (Join-Path $SqlDir 'player_milestones_rebuild_period.sql')
$milestonesPlayStreak100 = Get-Content -Raw (Join-Path $SqlDir 'player_milestones_rebuild_play_streak_100.sql')
$milestonesYearInHeaven = Get-Content -Raw (Join-Path $SqlDir 'player_milestones_rebuild_year_in_heaven.sql')
$leagueMarker = '-- League wave: first matching award row'
$leagueIdx = $milestonesCore.IndexOf($leagueMarker)
if ($leagueIdx -lt 0) {
    Write-Error "player_milestones_rebuild.sql missing league marker."
}
$milestonesSql = $milestonesCore.Substring(0, $leagueIdx) + $milestonesExists + $milestonesStreaks + $milestonesChrono + $milestonesTail + $milestonesPeriod + $milestonesPlayStreak100 + $milestonesYearInHeaven + $milestonesCore.Substring($leagueIdx)
Write-Host '-> player_milestones (core + exists + streaks + chrono + tail + period + play_streak_100 + year_in_heaven + league)' -ForegroundColor Cyan
$milestonesSql | & $MysqlExe @mysqlArgs
if ($LASTEXITCODE -ne 0) {
    Write-Error 'player_milestones rebuild failed.'
}

Write-Host 'Running milestone parity checks...' -ForegroundColor Cyan
$milestones = Invoke-MysqlQuery @"
SET time_zone = '+00:00';
SELECT 'player_milestones established_20', COUNT(*) FROM player_milestones WHERE milestone_key = 'established_20'
UNION ALL SELECT 'playertable NumberGames >= 20', COUNT(*) FROM playertable WHERE NumberGames >= 20
UNION ALL SELECT 'player_milestones league keys', COUNT(DISTINCT milestone_key) FROM player_milestones WHERE milestone_key LIKE 'league_%' OR milestone_key IN ('moment_of_glory','activity_king');
"@ 'milestone parity check'
Write-Host $milestones

$badMilestones = Invoke-MysqlQuery @"
SET time_zone = '+00:00';
SELECT (
  SELECT COUNT(*) FROM player_milestones WHERE milestone_key = 'established_20'
) - (
  SELECT COUNT(*) FROM playertable WHERE NumberGames >= 20
) AS diff;
"@ 'milestone parity comparison'
$badMilestonesValue = [int](($badMilestones | Select-Object -First 1).ToString().Trim())
if ($badMilestonesValue -ne 0) {
    Write-Error 'Milestone parity check failed: established_20 does not match playertable NumberGames >= 20.'
}

$badMilestoneSources = Invoke-MysqlQuery @"
SET time_zone = '+00:00';
SELECT COUNT(*) FROM player_milestones WHERE source_kind IS NULL;
"@ 'milestone source null check'
$badMilestoneSourcesValue = [int](($badMilestoneSources | Select-Object -First 1).ToString().Trim())
if ($badMilestoneSourcesValue -ne 0) {
    Write-Error 'Milestone source check failed: rows with NULL source_kind (rebuild must set game or league).'
}

if (Test-Path $PhpExe) {
    $streakScript = Join-Path $RepoRoot 'scripts\rebuild_player_play_streaks.php'
    if (Test-Path $streakScript) {
        Write-Host '-> player_play_streaks (REP-015 via PHP)' -ForegroundColor Cyan
        & $PhpExe $streakScript
        if ($LASTEXITCODE -ne 0) {
            Write-Error 'player_play_streaks rebuild failed.'
        }
    }
} else {
    Write-Warning 'php.exe not found — skip REP-015; run scripts\rebuild_player_play_streaks.php after SCH-014.'
}

Write-Host '[OK] website derived data rebuilt and verified.' -ForegroundColor Green
