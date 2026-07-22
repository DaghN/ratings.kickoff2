# Pull staged Amiga ground into a local database (default: ko2amiga_work).
# Triggers staging export PHP, downloads dump, replaces TargetDatabase.
# Simul is opt-in (-Simul) — default pull does not run it (~20 min).
#
# Usage (repo root):
#   powershell -ExecutionPolicy Bypass -File scripts\pull_ko2amiga_from_staging.ps1 -Force
#   powershell -ExecutionPolicy Bypass -File scripts\pull_ko2amiga_from_staging.ps1 -Force -Simul
#   powershell -ExecutionPolicy Bypass -File scripts\pull_ko2amiga_from_staging.ps1 -SkipGenerate -Force
#   # Side DB (does NOT touch ko2amiga_work):
#   powershell -ExecutionPolicy Bypass -File scripts\pull_ko2amiga_from_staging.ps1 -Force -TargetDatabase ko2amiga_staging_cmp
#
# Agents: use -Force (non-interactive). Staged = prod; default target wipes unpushed local work.

param(
    [string]$StagingBaseUrl = 'https://ratings.kickoff2.com',
    [string]$TargetDatabase = 'ko2amiga_work',
    [switch]$SkipGenerate,
    [switch]$Simul,
    [switch]$Force
)

$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'lib\Pull-Ko2AmigaStaging.ps1')
Invoke-Ko2AmigaStagingPull @PSBoundParameters