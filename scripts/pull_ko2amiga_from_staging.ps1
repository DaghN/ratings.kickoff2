# Pull staged Amiga ground into local ko2amiga_work (PULL-1a).
# Triggers staging export PHP, downloads dump, replaces local work DB.
# Simul is opt-in (-Simul) — default pull does not run it (~20 min).
#
# Usage (repo root):
#   powershell -ExecutionPolicy Bypass -File scripts\pull_ko2amiga_from_staging.ps1 -Force
#   powershell -ExecutionPolicy Bypass -File scripts\pull_ko2amiga_from_staging.ps1 -Force -Simul
#   powershell -ExecutionPolicy Bypass -File scripts\pull_ko2amiga_from_staging.ps1 -SkipGenerate -Force
#
# Agents: use -Force (non-interactive). Staged = prod; this wipes unpushed local work.

param(
    [string]$StagingBaseUrl = 'https://ratings.kickoff2.com',
    [switch]$SkipGenerate,
    [switch]$Simul,
    [switch]$Force
)

$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'lib\Pull-Ko2AmigaStaging.ps1')
Invoke-Ko2AmigaStagingPull @PSBoundParameters