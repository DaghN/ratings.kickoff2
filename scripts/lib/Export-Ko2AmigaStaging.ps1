# Shared staging export - dumps a local Amiga DB into ko2amiga_* import parts.
# Daily path: scripts\export_ko2amiga_work.ps1 (source ko2amiga_work).
# Oracle archaeology: scripts\export_ko2amiga_db.ps1 (source ko2amiga_db).
#
# Data parts follow site/public_html/data/amiga/staging_export_tables.json in order.
# Only special case: amiga_games + amiga_game_ratings are chunked (~5k rows).
# Do not maintain a second hardcoded table dump list here.

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

    $ManifestJsonPath = Join-Path $RepoRoot 'site\public_html\data\amiga\staging_export_tables.json'
    if (-not (Test-Path $ManifestJsonPath)) {
        Write-Error "Missing staging export manifest: $ManifestJsonPath (run: python -m scripts.amiga write-staging-export-tables)"
    }
    $manifestPayload = Get-Content -LiteralPath $ManifestJsonPath -Raw -Encoding UTF8 | ConvertFrom-Json
    if (-not $manifestPayload.tables) {
        Write-Error "Invalid staging export manifest (no tables): $ManifestJsonPath"
    }
    $Tables = @($manifestPayload.tables | ForEach-Object { [string]$_ })
    if ($Tables.Count -lt 1) {
        Write-Error "Staging export manifest has empty table list: $ManifestJsonPath"
    }
    if ($Tables -notcontains 'amiga_games' -or $Tables -notcontains 'amiga_game_ratings') {
        Write-Error 'Staging export manifest must include amiga_games and amiga_game_ratings (chunked dump).'
    }

    $Utf8NoBom = New-Object System.Text.UTF8Encoding $false

    function Get-Ko2AmigaTablePartSlug {
        param([string]$TableName)
        if ($TableName.StartsWith('amiga_')) {
            return $TableName.Substring(6)
        }
        return $TableName
    }

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
    $dumped = [System.Collections.Generic.HashSet[string]]::new([StringComparer]::OrdinalIgnoreCase)

    # 01 — DDL only (all registry tables)
    $schemaFile = Join-Path $OutDir 'ko2amiga_01_schema.sql'
    Write-DumpFile $schemaFile (@('--no-data', $SourceDatabase) + $Tables)
    $parts.Add('ko2amiga_01_schema.sql')

    $gamesIdx = -1
    for ($i = 0; $i -lt $Tables.Count; $i++) {
        if ([string]$Tables[$i] -eq 'amiga_games') {
            $gamesIdx = $i
            break
        }
    }
    if ($gamesIdx -lt 0) {
        Write-Error 'amiga_games missing from staging export table list.'
    }
    $earlyTables = @()
    if ($gamesIdx -gt 0) {
        $earlyTables = $Tables[0..($gamesIdx - 1)]
    }
    $tailTables = @()
    if (($gamesIdx + 2) -le ($Tables.Count - 1)) {
        $tailTables = $Tables[($gamesIdx + 2)..($Tables.Count - 1)]
    }
    # Expect ratings immediately after games in the registry order.
    if ([string]$Tables[$gamesIdx + 1] -ne 'amiga_game_ratings') {
        Write-Error 'staging_export_tables.json: amiga_game_ratings must immediately follow amiga_games.'
    }

    $idx = 2
    foreach ($table in $earlyTables) {
        $slug = Get-Ko2AmigaTablePartSlug $table
        # Keep historical 07a filename for scoring steps (docs / older import notes).
        if ($table -eq 'tournament_stage_scoring_steps') {
            $partName = 'ko2amiga_07a_stage_scoring_steps.sql'
        } else {
            $partName = ('ko2amiga_{0:D2}_{1}.sql' -f $idx, $slug)
            $idx++
        }
        Write-DumpFile (Join-Path $OutDir $partName) @('--no-create-info', $SourceDatabase, $table)
        $parts.Add($partName)
        [void]$dumped.Add($table)
    }

    # Games + ratings in ~5k row chunks (staging-friendly)
    $chunkSize = 5000
    $maxIdText = (& $MysqlExe -u root -N -B -e "SELECT COALESCE(MAX(id), 0) FROM ${SourceDatabase}.amiga_games" 2>&1 | Out-String).Trim()
    if ($maxIdText -notmatch '^\d+$') {
        Write-Error "Could not read MAX(id) from ${SourceDatabase}.amiga_games: $maxIdText"
    }
    $maxId = [int]$maxIdText
    Write-Host "Chunking games/ratings: max id $maxId (chunk size $chunkSize)"

    if ($idx -lt 10) { $idx = 10 }

    if ($maxId -le 0) {
        $gamesPart = ('ko2amiga_{0:D2}_games_empty.sql' -f $idx)
        Write-DumpFile (Join-Path $OutDir $gamesPart) @('--no-create-info', $SourceDatabase, 'amiga_games')
        $parts.Add($gamesPart)
        $idx++
        $ratingsPart = ('ko2amiga_{0:D2}_ratings_empty.sql' -f $idx)
        Write-DumpFile (Join-Path $OutDir $ratingsPart) @('--no-create-info', $SourceDatabase, 'amiga_game_ratings')
        $parts.Add($ratingsPart)
        $idx++
    } else {
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
    }
    [void]$dumped.Add('amiga_games')
    [void]$dumped.Add('amiga_game_ratings')

    foreach ($table in $tailTables) {
        $slug = Get-Ko2AmigaTablePartSlug $table
        $partName = ('ko2amiga_{0:D2}_{1}.sql' -f $idx, $slug)
        Write-DumpFile (Join-Path $OutDir $partName) @('--no-create-info', $SourceDatabase, $table)
        $parts.Add($partName)
        [void]$dumped.Add($table)
        $idx++
    }

    $missingData = @($Tables | Where-Object { -not $dumped.Contains($_) })
    if ($missingData.Count -gt 0) {
        Write-Error ("Export data parts missing registry table(s): {0}" -f ($missingData -join ', '))
    }

    $manifest = @{
        generated        = (Get-Date).ToString('yyyy-MM-dd HH:mm:ss')
        source_database  = $SourceDatabase
        staging_database = 'ko2amiga_db'
        parts            = @($parts)
        tables           = @($Tables)
    }
    $manifestPath = Join-Path $OutDir 'ko2amiga_manifest.json'
    $manifestJson = $manifest | ConvertTo-Json -Depth 4
    [System.IO.File]::WriteAllText($manifestPath, $manifestJson, $Utf8NoBom)

    # Full dump for Heidi / one-shot local mysql
    $OutFile = Join-Path $OutDir 'ko2amiga_db.sql'
    Write-DumpFile $OutFile (@($SourceDatabase) + $Tables)
    $ArchiveFile = Join-Path $ArchiveDir "ko2amiga_db-$Stamp.sql"
    Copy-Item -LiteralPath $OutFile -Destination $ArchiveFile -Force

    $removedStale = Remove-StaleKo2AmigaImportParts -Directory $OutDir -KeepPartNames @($parts)
    $partsBytes = ($parts | ForEach-Object {
        (Get-Item -LiteralPath (Join-Path $OutDir $_)).Length
    } | Measure-Object -Sum).Sum
    $fullDumpBytes = (Get-Item -LiteralPath $OutFile).Length

    Write-Host "Wrote $($parts.Count) part files + manifest to $OutDir"
    Write-Host ("Active export: {0:N1} MB manifest parts, {1:N1} MB full dump" -f ($partsBytes / 1MB), ($fullDumpBytes / 1MB))
    Write-Host ("Registry tables dumped: {0}" -f $Tables.Count)
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