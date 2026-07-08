# Shared staging export - dumps a local Amiga DB into ko2amiga_* import parts.
# Daily path: scripts\export_ko2amiga_work.ps1 (source ko2amiga_work).
# Oracle archaeology: scripts\export_ko2amiga_db.ps1 (source ko2amiga_db).

function Export-Ko2AmigaStagingDatabase {
    param(
        [Parameter(Mandatory = $true)]
        [ValidateSet('ko2amiga_work', 'ko2amiga_db')]
        [string]$SourceDatabase
    )

    $ErrorActionPreference = 'Stop'
    . (Join-Path $PSScriptRoot 'LaragonMysql.ps1')
    $RepoRoot = Split-Path -Parent (Split-Path -Parent $PSScriptRoot)
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
        'amiga_games', 'amiga_game_ratings',
        'amiga_player_event_snapshots', 'amiga_player_current', 'amiga_player_elo_rank_at_event',
        'amiga_player_matchup_at_event', 'amiga_player_matchup_summary',
        'amiga_tournament_standings', 'amiga_tournament_catalog_stats',
        'amiga_generalstats', 'amiga_realm_snapshots',
        'amiga_community_stats', 'amiga_community_stats_snapshots', 'amiga_community_stat_facts',
        'amiga_world_cup_stats',
        'amiga_tournament_finish_override',
        'amiga_player_slice_totals', 'amiga_player_slice_at_event',
        'amiga_country_slice_totals', 'amiga_country_slice_at_event',
        'amiga_wc_hof_snapshots', 'amiga_wc_hof_present'
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

    function Remove-StaleKo2AmigaImportParts {
        param(
            [string]$Directory,
            [string[]]$KeepPartNames
        )
        $keep = [System.Collections.Generic.HashSet[string]]::new([StringComparer]::OrdinalIgnoreCase)
        foreach ($name in $KeepPartNames) {
            [void]$keep.Add($name)
        }
        [void]$keep.Add('ko2amiga_db.sql')

        $removed = New-Object System.Collections.Generic.List[string]
        Get-ChildItem -LiteralPath $Directory -File -Filter 'ko2amiga_*.sql' | ForEach-Object {
            if (-not $keep.Contains($_.Name)) {
                Remove-Item -LiteralPath $_.FullName -Force
                [void]$removed.Add($_.Name)
            }
        }
        return $removed
    }

    $parts = New-Object System.Collections.Generic.List[string]

    # 01 — DDL only
    $schemaFile = Join-Path $OutDir 'ko2amiga_01_schema.sql'
    Write-DumpFile $schemaFile @('--no-data', $SourceDatabase) + $Tables
    $parts.Add('ko2amiga_01_schema.sql')

    # 02–09 — small ground-truth + structure data
    $templatesFile = Join-Path $OutDir 'ko2amiga_02_format_templates.sql'
    Write-DumpFile $templatesFile @('--no-create-info', $SourceDatabase, 'tournament_format_templates')
    $parts.Add('ko2amiga_02_format_templates.sql')

    $tourFile = Join-Path $OutDir 'ko2amiga_03_tournaments.sql'
    Write-DumpFile $tourFile @('--no-create-info', $SourceDatabase, 'tournaments')
    $parts.Add('ko2amiga_03_tournaments.sql')

    $playersFile = Join-Path $OutDir 'ko2amiga_04_players.sql'
    Write-DumpFile $playersFile @('--no-create-info', $SourceDatabase, 'amiga_players')
    $parts.Add('ko2amiga_04_players.sql')

    $finishOverridePart = ('ko2amiga_{0:D2}_finish_override.sql' -f 5)
    $finishOverrideFile = Join-Path $OutDir $finishOverridePart
    Write-DumpFile $finishOverrideFile @('--no-create-info', $SourceDatabase, 'amiga_tournament_finish_override')
    $parts.Add($finishOverridePart)

    $entrantsFile = Join-Path $OutDir 'ko2amiga_06_entrants.sql'
    Write-DumpFile $entrantsFile @('--no-create-info', $SourceDatabase, 'tournament_entrants')
    $parts.Add('ko2amiga_06_entrants.sql')

    $stagesFile = Join-Path $OutDir 'ko2amiga_07_stages.sql'
    Write-DumpFile $stagesFile @('--no-create-info', $SourceDatabase, 'tournament_stages')
    $parts.Add('ko2amiga_07_stages.sql')

    $stagePlayersFile = Join-Path $OutDir 'ko2amiga_08_stage_players.sql'
    Write-DumpFile $stagePlayersFile @('--no-create-info', $SourceDatabase, 'tournament_stage_players')
    $parts.Add('ko2amiga_08_stage_players.sql')

    $fixturesFile = Join-Path $OutDir 'ko2amiga_09_fixtures.sql'
    Write-DumpFile $fixturesFile @('--no-create-info', $SourceDatabase, 'tournament_fixtures')
    $parts.Add('ko2amiga_09_fixtures.sql')

    # 10+ — games + ratings in ~5k row chunks (staging-friendly)
    $chunkSize = 5000
    $maxIdText = (& $MysqlExe -u root -N -B -e "SELECT COALESCE(MAX(id), 0) FROM ${SourceDatabase}.amiga_games" 2>&1 | Out-String).Trim()
    if ($maxIdText -notmatch '^\d+$') {
        Write-Error "Could not read MAX(id) from ${SourceDatabase}.amiga_games: $maxIdText"
    }
    $maxId = [int]$maxIdText
    Write-Host "Chunking games/ratings: max id $maxId (chunk size $chunkSize)"

    $idx = 10
    for ($start = 1; $start -le $maxId; $start += $chunkSize) {
        $end = [Math]::Min($start + $chunkSize - 1, $maxId)
        $where = "id >= $start AND id <= $end"

        $gamesPart = ('ko2amiga_{0:D2}_games_{1}_{2}.sql' -f $idx, $start, $end)
        Write-DumpFile (Join-Path $OutDir $gamesPart) @('--no-create-info', "--where=$where", $SourceDatabase, 'amiga_games')
        $parts.Add($gamesPart)
        $idx++

        $ratingsPart = ('ko2amiga_{0:D2}_ratings_{1}_{2}.sql' -f $idx, $start, $end)
        Write-DumpFile (Join-Path $OutDir $ratingsPart) @('--no-create-info', "--where=game_id >= $start AND game_id <= $end", $SourceDatabase, 'amiga_game_ratings')
        $parts.Add($ratingsPart)
        $idx++
    }

    $snapshotsPart = ('ko2amiga_{0:D2}_event_snapshots.sql' -f $idx)
    $snapshotsFile = Join-Path $OutDir $snapshotsPart
    Write-DumpFile $snapshotsFile @('--no-create-info', $SourceDatabase, 'amiga_player_event_snapshots')
    $parts.Add($snapshotsPart)
    $idx++

    $currentPart = ('ko2amiga_{0:D2}_player_current.sql' -f $idx)
    $currentFile = Join-Path $OutDir $currentPart
    Write-DumpFile $currentFile @('--no-create-info', $SourceDatabase, 'amiga_player_current')
    $parts.Add($currentPart)
    $idx++

    $eloRankAtEventPart = ('ko2amiga_{0:D2}_elo_rank_at_event.sql' -f $idx)
    $eloRankAtEventFile = Join-Path $OutDir $eloRankAtEventPart
    Write-DumpFile $eloRankAtEventFile @('--no-create-info', $SourceDatabase, 'amiga_player_elo_rank_at_event')
    $parts.Add($eloRankAtEventPart)
    $idx++

    $matchupAtEventPart = ('ko2amiga_{0:D2}_matchup_at_event.sql' -f $idx)
    $matchupAtEventFile = Join-Path $OutDir $matchupAtEventPart
    Write-DumpFile $matchupAtEventFile @('--no-create-info', $SourceDatabase, 'amiga_player_matchup_at_event')
    $parts.Add($matchupAtEventPart)
    $idx++

    $standingsPart = ('ko2amiga_{0:D2}_standings.sql' -f $idx)
    $standingsFile = Join-Path $OutDir $standingsPart
    Write-DumpFile $standingsFile @('--no-create-info', $SourceDatabase, 'amiga_tournament_standings')
    $parts.Add($standingsPart)
    $idx++

    $catalogPart = ('ko2amiga_{0:D2}_catalog_stats.sql' -f $idx)
    $catalogFile = Join-Path $OutDir $catalogPart
    Write-DumpFile $catalogFile @('--no-create-info', $SourceDatabase, 'amiga_tournament_catalog_stats')
    $parts.Add($catalogPart)
    $idx++

    $matchupPart = ('ko2amiga_{0:D2}_matchup_summary.sql' -f $idx)
    $matchupFile = Join-Path $OutDir $matchupPart
    Write-DumpFile $matchupFile @('--no-create-info', $SourceDatabase, 'amiga_player_matchup_summary')
    $parts.Add($matchupPart)
    $idx++

    $generalstatsPart = ('ko2amiga_{0:D2}_generalstats.sql' -f $idx)
    $generalstatsFile = Join-Path $OutDir $generalstatsPart
    Write-DumpFile $generalstatsFile @('--no-create-info', $SourceDatabase, 'amiga_generalstats')
    $parts.Add($generalstatsPart)
    $idx++

    $realmSnapshotsPart = ('ko2amiga_{0:D2}_realm_snapshots.sql' -f $idx)
    $realmSnapshotsFile = Join-Path $OutDir $realmSnapshotsPart
    Write-DumpFile $realmSnapshotsFile @('--no-create-info', $SourceDatabase, 'amiga_realm_snapshots')
    $parts.Add($realmSnapshotsPart)
    $idx++

    $communityStatsPart = ('ko2amiga_{0:D2}_community_stats.sql' -f $idx)
    $communityStatsFile = Join-Path $OutDir $communityStatsPart
    Write-DumpFile $communityStatsFile @('--no-create-info', $SourceDatabase, 'amiga_community_stats')
    $parts.Add($communityStatsPart)
    $idx++

    $communitySnapshotsPart = ('ko2amiga_{0:D2}_community_stats_snapshots.sql' -f $idx)
    $communitySnapshotsFile = Join-Path $OutDir $communitySnapshotsPart
    Write-DumpFile $communitySnapshotsFile @('--no-create-info', $SourceDatabase, 'amiga_community_stats_snapshots')
    $parts.Add($communitySnapshotsPart)
    $idx++

    $communityFactsPart = ('ko2amiga_{0:D2}_community_stat_facts.sql' -f $idx)
    $communityFactsFile = Join-Path $OutDir $communityFactsPart
    Write-DumpFile $communityFactsFile @('--no-create-info', $SourceDatabase, 'amiga_community_stat_facts')
    $parts.Add($communityFactsPart)
    $idx++

    $worldCupStatsPart = ('ko2amiga_{0:D2}_world_cup_stats.sql' -f $idx)
    $worldCupStatsFile = Join-Path $OutDir $worldCupStatsPart
    Write-DumpFile $worldCupStatsFile @('--no-create-info', $SourceDatabase, 'amiga_world_cup_stats')
    $parts.Add($worldCupStatsPart)
    $idx++

    $sliceTotalsPart = ('ko2amiga_{0:D2}_slice_totals.sql' -f $idx)
    $sliceTotalsFile = Join-Path $OutDir $sliceTotalsPart
    Write-DumpFile $sliceTotalsFile @('--no-create-info', $SourceDatabase, 'amiga_player_slice_totals')
    $parts.Add($sliceTotalsPart)
    $idx++

    $sliceAtEventPart = ('ko2amiga_{0:D2}_slice_at_event.sql' -f $idx)
    $sliceAtEventFile = Join-Path $OutDir $sliceAtEventPart
    Write-DumpFile $sliceAtEventFile @('--no-create-info', $SourceDatabase, 'amiga_player_slice_at_event')
    $parts.Add($sliceAtEventPart)
    $idx++

    $countrySliceTotalsPart = ('ko2amiga_{0:D2}_country_slice_totals.sql' -f $idx)
    $countrySliceTotalsFile = Join-Path $OutDir $countrySliceTotalsPart
    Write-DumpFile $countrySliceTotalsFile @('--no-create-info', $SourceDatabase, 'amiga_country_slice_totals')
    $parts.Add($countrySliceTotalsPart)
    $idx++

    $countrySliceAtEventPart = ('ko2amiga_{0:D2}_country_slice_at_event.sql' -f $idx)
    $countrySliceAtEventFile = Join-Path $OutDir $countrySliceAtEventPart
    Write-DumpFile $countrySliceAtEventFile @('--no-create-info', $SourceDatabase, 'amiga_country_slice_at_event')
    $parts.Add($countrySliceAtEventPart)
    $idx++

    $wcHofSnapshotsPart = ('ko2amiga_{0:D2}_wc_hof_snapshots.sql' -f $idx)
    $wcHofSnapshotsFile = Join-Path $OutDir $wcHofSnapshotsPart
    Write-DumpFile $wcHofSnapshotsFile @('--no-create-info', $SourceDatabase, 'amiga_wc_hof_snapshots')
    $parts.Add($wcHofSnapshotsPart)
    $idx++

    $wcHofPresentPart = ('ko2amiga_{0:D2}_wc_hof_present.sql' -f $idx)
    $wcHofPresentFile = Join-Path $OutDir $wcHofPresentPart
    Write-DumpFile $wcHofPresentFile @('--no-create-info', $SourceDatabase, 'amiga_wc_hof_present')
    $parts.Add($wcHofPresentPart)

    $manifest = @{
        generated        = (Get-Date).ToString('yyyy-MM-dd HH:mm:ss')
        source_database  = $SourceDatabase
        staging_database = 'ko2amiga_db'
        parts            = @($parts)
    }
    $manifestPath = Join-Path $OutDir 'ko2amiga_manifest.json'
    $manifestJson = $manifest | ConvertTo-Json
    [System.IO.File]::WriteAllText($manifestPath, $manifestJson, $Utf8NoBom)

    # Full dump for Heidi / one-shot local mysql
    $OutFile = Join-Path $OutDir 'ko2amiga_db.sql'
    Write-DumpFile $OutFile @($SourceDatabase) + $Tables
    $ArchiveFile = Join-Path $ArchiveDir "ko2amiga_db-$Stamp.sql"
    Copy-Item -LiteralPath $OutFile -Destination $ArchiveFile -Force

    $removedStale = Remove-StaleKo2AmigaImportParts -Directory $OutDir -KeepPartNames @($parts)
    $partsBytes = ($parts | ForEach-Object {
        (Get-Item -LiteralPath (Join-Path $OutDir $_)).Length
    } | Measure-Object -Sum).Sum
    $fullDumpBytes = (Get-Item -LiteralPath $OutFile).Length

    Write-Host "Wrote $($parts.Count) part files + manifest to $OutDir"
    Write-Host ("Active export: {0:N1} MB manifest parts, {1:N1} MB full dump" -f ($partsBytes / 1MB), ($fullDumpBytes / 1MB))
    if ($removedStale.Count -gt 0) {
        Write-Host "Removed $($removedStale.Count) stale ko2amiga_*.sql file(s) not in manifest:"
        foreach ($name in $removedStale) {
            Write-Host "  - $name"
        }
    } else {
        Write-Host 'No stale ko2amiga_*.sql files to remove.'
    }
    Write-Host "Full dump: $OutFile"
    Write-Host "Archive copy: $ArchiveFile"

    Write-Host ("Source database: {0} -> staging import ko2amiga_db" -f $SourceDatabase)
}
