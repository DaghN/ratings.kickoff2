# Dump ko2amiga_db for staging import (gitignored output).
# Writes one full dump + smaller part files for browser import (avoids gateway timeouts).
$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'lib\LaragonMysql.ps1')
$RepoRoot = Split-Path -Parent $PSScriptRoot
$MysqlExe = Find-LaragonMysqlExe
if (-not $MysqlExe) { Write-Error 'Laragon mysql.exe not found.' }
$DumpExe = Join-Path (Split-Path $MysqlExe -Parent) 'mysqldump.exe'
if (-not (Test-Path $DumpExe)) { Write-Error "mysqldump.exe not found at $DumpExe" }

$OutDir = Join-Path $RepoRoot 'site\public_html\amiga\_import'
New-Item -ItemType Directory -Force -Path $OutDir | Out-Null
$ArchiveDir = Join-Path $RepoRoot 'data\amiga\exports'
New-Item -ItemType Directory -Force -Path $ArchiveDir | Out-Null
$Stamp = Get-Date -Format 'yyyy-MM-dd'

$Tables = @(
    'tournament_format_templates', 'tournaments', 'amiga_players',
    'tournament_entrants', 'tournament_stages', 'tournament_stage_players', 'tournament_fixtures',
    'amiga_games', 'amiga_game_ratings', 'amiga_player_stats',
    'amiga_tournament_standings', 'amiga_tournament_catalog_stats'
)

$Utf8NoBom = New-Object System.Text.UTF8Encoding $false

function Write-DumpFile {
    param(
        [string]$Path,
        [string[]]$DumpArgs
    )
    $text = (& $DumpExe -u root --single-transaction @DumpArgs | Out-String)
    [System.IO.File]::WriteAllText($Path, $text, $Utf8NoBom)
}

$parts = New-Object System.Collections.Generic.List[string]

# 01 — DDL only
$schemaFile = Join-Path $OutDir 'ko2amiga_01_schema.sql'
Write-DumpFile $schemaFile @('--no-data', 'ko2amiga_db') + $Tables
$parts.Add('ko2amiga_01_schema.sql')

# 02–07 — small ground-truth data
$templatesFile = Join-Path $OutDir 'ko2amiga_02_format_templates.sql'
Write-DumpFile $templatesFile @('--no-create-info', 'ko2amiga_db', 'tournament_format_templates')
$parts.Add('ko2amiga_02_format_templates.sql')

$tourFile = Join-Path $OutDir 'ko2amiga_03_tournaments.sql'
Write-DumpFile $tourFile @('--no-create-info', 'ko2amiga_db', 'tournaments')
$parts.Add('ko2amiga_03_tournaments.sql')

$playersFile = Join-Path $OutDir 'ko2amiga_04_players.sql'
Write-DumpFile $playersFile @('--no-create-info', 'ko2amiga_db', 'amiga_players')
$parts.Add('ko2amiga_04_players.sql')

$entrantsFile = Join-Path $OutDir 'ko2amiga_05_entrants.sql'
Write-DumpFile $entrantsFile @('--no-create-info', 'ko2amiga_db', 'tournament_entrants')
$parts.Add('ko2amiga_05_entrants.sql')

$stagesFile = Join-Path $OutDir 'ko2amiga_06_stages.sql'
Write-DumpFile $stagesFile @('--no-create-info', 'ko2amiga_db', 'tournament_stages')
$parts.Add('ko2amiga_06_stages.sql')

$stagePlayersFile = Join-Path $OutDir 'ko2amiga_07_stage_players.sql'
Write-DumpFile $stagePlayersFile @('--no-create-info', 'ko2amiga_db', 'tournament_stage_players')
$parts.Add('ko2amiga_07_stage_players.sql')

$fixturesFile = Join-Path $OutDir 'ko2amiga_08_fixtures.sql'
Write-DumpFile $fixturesFile @('--no-create-info', 'ko2amiga_db', 'tournament_fixtures')
$parts.Add('ko2amiga_08_fixtures.sql')

# 09+ — games + ratings in ~5k row chunks (staging-friendly)
$chunkSize = 5000
$maxIdText = (& $MysqlExe -u root -N -B -e 'SELECT COALESCE(MAX(id), 0) FROM ko2amiga_db.amiga_games' 2>&1 | Out-String).Trim()
if ($maxIdText -notmatch '^\d+$') {
    Write-Error "Could not read MAX(id) from ko2amiga_db.amiga_games: $maxIdText"
}
$maxId = [int]$maxIdText
Write-Host "Chunking games/ratings: max id $maxId (chunk size $chunkSize)"

$idx = 9
for ($start = 1; $start -le $maxId; $start += $chunkSize) {
    $end = [Math]::Min($start + $chunkSize - 1, $maxId)
    $where = "id >= $start AND id <= $end"

    $gamesPart = ('ko2amiga_{0:D2}_games_{1}_{2}.sql' -f $idx, $start, $end)
    Write-DumpFile (Join-Path $OutDir $gamesPart) @('--no-create-info', "--where=$where", 'ko2amiga_db', 'amiga_games')
    $parts.Add($gamesPart)
    $idx++

    $ratingsPart = ('ko2amiga_{0:D2}_ratings_{1}_{2}.sql' -f $idx, $start, $end)
    Write-DumpFile (Join-Path $OutDir $ratingsPart) @('--no-create-info', "--where=game_id >= $start AND game_id <= $end", 'ko2amiga_db', 'amiga_game_ratings')
    $parts.Add($ratingsPart)
    $idx++
}

$statsPart = ('ko2amiga_{0:D2}_stats.sql' -f $idx)
$statsFile = Join-Path $OutDir $statsPart
Write-DumpFile $statsFile @('--no-create-info', 'ko2amiga_db', 'amiga_player_stats')
$parts.Add($statsPart)
$idx++

$standingsPart = ('ko2amiga_{0:D2}_standings.sql' -f $idx)
$standingsFile = Join-Path $OutDir $standingsPart
Write-DumpFile $standingsFile @('--no-create-info', 'ko2amiga_db', 'amiga_tournament_standings')
$parts.Add($standingsPart)
$idx++

$catalogPart = ('ko2amiga_{0:D2}_catalog_stats.sql' -f $idx)
$catalogFile = Join-Path $OutDir $catalogPart
Write-DumpFile $catalogFile @('--no-create-info', 'ko2amiga_db', 'amiga_tournament_catalog_stats')
$parts.Add($catalogPart)

$manifest = @{
    generated = (Get-Date).ToString('yyyy-MM-dd HH:mm:ss')
    parts     = @($parts)
}
$manifestPath = Join-Path $OutDir 'ko2amiga_manifest.json'
$manifestJson = $manifest | ConvertTo-Json
[System.IO.File]::WriteAllText($manifestPath, $manifestJson, $Utf8NoBom)

# Full dump for Heidi / one-shot local mysql
$OutFile = Join-Path $OutDir 'ko2amiga_db.sql'
Write-DumpFile $OutFile @('ko2amiga_db') + $Tables
$ArchiveFile = Join-Path $ArchiveDir "ko2amiga_db-$Stamp.sql"
Copy-Item -LiteralPath $OutFile -Destination $ArchiveFile -Force

Write-Host "Wrote $($parts.Count) part files + manifest to $OutDir"
Write-Host "Full dump: $OutFile"
Write-Host "Archive copy: $ArchiveFile"
