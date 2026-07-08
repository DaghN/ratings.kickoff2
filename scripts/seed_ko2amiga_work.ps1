# Seed ko2amiga_work from day 0 L3 archive (W-1).
# Usage (repo root): powershell -ExecutionPolicy Bypass -File scripts\seed_ko2amiga_work.ps1
$ErrorActionPreference = 'Stop'
$RepoRoot = Split-Path -Parent $PSScriptRoot
Set-Location $RepoRoot
python -m scripts.amiga seed-work @args
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }