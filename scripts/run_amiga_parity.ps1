# P-1 parity — ko2amiga_work vs frozen ko2amiga_db oracle.
# Usage (repo root): powershell -ExecutionPolicy Bypass -File scripts\run_amiga_parity.ps1
$ErrorActionPreference = 'Stop'
$RepoRoot = Split-Path -Parent $PSScriptRoot
Set-Location $RepoRoot
python -m scripts.amiga parity @args
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }