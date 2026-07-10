# Seal a milestone git checkpoint of local ko2amiga_work (full export tier).
# Usage: powershell -ExecutionPolicy Bypass -File scripts\seal_amiga_work_checkpoint.ps1 -Label tail
#        powershell -ExecutionPolicy Bypass -File scripts\seal_amiga_work_checkpoint.ps1 -Label tail -SkipExport
param(
    [Parameter(Mandatory = $true)]
    [string]$Label,
    [string]$Notes = '',
    [switch]$SkipExport
)

$ErrorActionPreference = 'Stop'
$RepoRoot = Split-Path -Parent $PSScriptRoot
Set-Location $RepoRoot

if (-not $SkipExport) {
    & (Join-Path $PSScriptRoot 'export_ko2amiga_work.ps1')
    if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
}

$date = Get-Date -Format 'yyyy-MM-dd'
$checkpointId = "work-$date-$Label"
$checkpointDir = Join-Path $RepoRoot "data\amiga\checkpoints\$checkpointId"
$importDir = Join-Path $RepoRoot 'site\public_html\amiga\_import'
$companionDir = Join-Path $checkpointDir 'companion'
New-Item -ItemType Directory -Force -Path $companionDir | Out-Null

Copy-Item -LiteralPath (Join-Path $importDir 'ko2amiga_manifest.json') -Destination $checkpointDir -Force
Get-ChildItem -LiteralPath $importDir -Filter 'ko2amiga_*.sql' | Where-Object { $_.Name -ne 'ko2amiga_db.sql' } | Copy-Item -Destination $checkpointDir -Force

$companions = @(
    @{ src = 'site\public_html\data\amiga\tournament_videos.json'; name = 'tournament_videos.json' },
    @{ src = 'site\public_html\data\amiga\tournament_videos\review.csv'; name = 'tournament_videos_review.csv' },
    @{ src = 'scripts\amiga\tournament_structure\disposition_register.json'; name = 'disposition_register.json' },
    @{ src = 'scripts\amiga\match_extensions_verified_register.json'; name = 'match_extensions_verified_register.json' },
    @{ src = 'data\amiga\tournament_videos\video_game_links.csv'; name = 'video_game_links.csv' }
)
$copied = @()
foreach ($c in $companions) {
    $src = Join-Path $RepoRoot $c.src
    if (Test-Path $src) {
        Copy-Item -LiteralPath $src -Destination (Join-Path $companionDir $c.name) -Force
        $copied += $c.name
    }
}

. (Join-Path $PSScriptRoot 'lib\LaragonMysql.ps1')
$MysqlExe = Find-LaragonMysqlExe
if (-not $MysqlExe) { throw 'Laragon mysql.exe not found.' }
$countQuery = @"
SELECT 'tournaments', COUNT(*) FROM ko2amiga_work.tournaments UNION ALL
SELECT 'amiga_players', COUNT(*) FROM ko2amiga_work.amiga_players UNION ALL
SELECT 'amiga_games', COUNT(*) FROM ko2amiga_work.amiga_games UNION ALL
SELECT 'tournament_stages', COUNT(*) FROM ko2amiga_work.tournament_stages UNION ALL
SELECT 'tournament_fixtures', COUNT(*) FROM ko2amiga_work.tournament_fixtures UNION ALL
SELECT 'amiga_tournament_finish_override', COUNT(*) FROM ko2amiga_work.amiga_tournament_finish_override UNION ALL
SELECT 'amiga_player_event_snapshots', COUNT(*) FROM ko2amiga_work.amiga_player_event_snapshots
"@
$counts = @{}
& $MysqlExe -u root -N -B -e $countQuery | ForEach-Object {
    $parts = $_ -split "`t", 2
    if ($parts.Count -eq 2) { $counts[$parts[0]] = [int]$parts[1] }
}

$partsBytes = (Get-ChildItem -LiteralPath $checkpointDir -Filter 'ko2amiga_*.sql' | Measure-Object Length -Sum).Sum
$exportManifest = Get-Content (Join-Path $checkpointDir 'ko2amiga_manifest.json') -Raw -Encoding UTF8 | ConvertFrom-Json
$gitHead = (git -C $RepoRoot rev-parse --short HEAD).Trim()
$simul = $null
$simulPath = Join-Path $RepoRoot 'data\amiga\modern\simul-last.json'
if (Test-Path $simulPath) {
    $simul = Get-Content $simulPath -Raw -Encoding UTF8 | ConvertFrom-Json
}

$manifest = [ordered]@{
    checkpoint_id = $checkpointId
    tier = 'full'
    sealed_utc = (Get-Date).ToUniversalTime().ToString('yyyy-MM-ddTHH:mm:ssZ')
    label = $Label
    notes_text = $Notes
    source_database = 'ko2amiga_work'
    git_commit_at_seal = $gitHead
    export = @{
        generated = $exportManifest.generated
        parts_count = $exportManifest.parts.Count
        parts_bytes = $partsBytes
        parts_bytes_mb = [math]::Round($partsBytes / 1MB, 2)
    }
    counts = $counts
    simul_last = if ($simul) {
        @{
            finished_utc = $simul.finished_utc
            duration_sec = $simul.duration_sec
            git_head = $simul.git_head
            note = 'Structure edits after this simul may be included in SQL; re-simul if restoring without L5 parts.'
        }
    } else { $null }
    companion_files = $copied
    restore = @{
        import_parts_in_order = 'ko2amiga_manifest.json parts[]'
        target_database = 'ko2amiga_work'
        after_import = 'Point ko2amiga_config.local.php at ko2amiga_work; run python -m scripts.amiga simul if L5 derived looks stale.'
    }
}

$enc = New-Object System.Text.UTF8Encoding $false
[System.IO.File]::WriteAllText((Join-Path $checkpointDir 'manifest.json'), ($manifest | ConvertTo-Json -Depth 6), $enc)

Write-Host ''
Write-Host "Sealed: $checkpointDir" -ForegroundColor Green
Write-Host ("  {0} parts, {1:N1} MB" -f $exportManifest.parts.Count, ($partsBytes / 1MB))
Write-Host "  companions: $($copied -join ', ')"
Write-Host ''
Write-Host 'Next: add gitignore allowlist for this folder (see data/amiga/checkpoints/README.md), then git add + commit.' -ForegroundColor Yellow