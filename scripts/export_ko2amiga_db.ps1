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
    'tournaments', 'amiga_players', 'amiga_games', 'amiga_game_ratings', 'amiga_player_stats'
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

# 02–03 — small ground-truth data
$tourFile = Join-Path $OutDir 'ko2amiga_02_tournaments.sql'
Write-DumpFile $tourFile @('--no-create-info', 'ko2amiga_db', 'tournaments')
$parts.Add('ko2amiga_02_tournaments.sql')

$playersFile = Join-Path $OutDir 'ko2amiga_03_players.sql'
Write-DumpFile $playersFile @('--no-create-info', 'ko2amiga_db', 'amiga_players')
$parts.Add('ko2amiga_03_players.sql')

# 04–09 — games + ratings in ~5k row chunks (staging-friendly)
$chunkSize = 5000
$maxId = 27408
$idx = 4
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

$statsFile = Join-Path $OutDir 'ko2amiga_16_stats.sql'
Write-DumpFile $statsFile @('--no-create-info', 'ko2amiga_db', 'amiga_player_stats')
$parts.Add('ko2amiga_16_stats.sql')

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
