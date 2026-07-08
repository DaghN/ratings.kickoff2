# Modern Amiga simul on ko2amiga_work (S-1).
# Usage (repo root): powershell -ExecutionPolicy Bypass -File scripts\run_amiga_simul.ps1
$ErrorActionPreference = 'Stop'
$RepoRoot = Split-Path -Parent $PSScriptRoot
Set-Location $RepoRoot
python -m scripts.amiga simul @args
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }