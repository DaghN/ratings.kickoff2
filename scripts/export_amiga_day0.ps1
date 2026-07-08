# Seal day 0 L3 witness ground from ko2amiga_db (D0-1).
# Usage (repo root): powershell -ExecutionPolicy Bypass -File scripts\export_amiga_day0.ps1
$ErrorActionPreference = 'Stop'
$RepoRoot = Split-Path -Parent $PSScriptRoot
Set-Location $RepoRoot
python -m scripts.amiga seal-day0 @args
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }