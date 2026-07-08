# Oracle-only staging export from frozen ko2amiga_db (archaeology / legacy prove path).
# Daily path: scripts\export_ko2amiga_work.ps1 (living ground on ko2amiga_work).
$ErrorActionPreference = 'Stop'
Write-Warning 'export_ko2amiga_db.ps1 dumps oracle ko2amiga_db — daily export is export_ko2amiga_work.ps1'
. (Join-Path $PSScriptRoot 'lib\Export-Ko2AmigaStaging.ps1')
Export-Ko2AmigaStagingDatabase -SourceDatabase 'ko2amiga_db'