# Dump local ko2amiga_work for staging import into ko2amiga_db on the server.
# Promotes work video manifest to site/public_html before export (WinSCP sync).
# Usage (repo root): powershell -ExecutionPolicy Bypass -File scripts\export_ko2amiga_work.ps1
$ErrorActionPreference = 'Stop'
$RepoRoot = Split-Path -Parent $PSScriptRoot
Set-Location $RepoRoot

python -m scripts.amiga promote-video-deploy
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

. (Join-Path $PSScriptRoot 'lib\Export-Ko2AmigaStaging.ps1')
Export-Ko2AmigaStagingDatabase -SourceDatabase 'ko2amiga_work'