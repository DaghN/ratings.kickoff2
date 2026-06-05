# Full ladder replay on local ko2unity_db (~74k games).
# Usage: powershell -ExecutionPolicy Bypass -File scripts\run_local_replay.ps1
#        powershell -ExecutionPolicy Bypass -File scripts\run_local_replay.ps1 -DryRun

param(
    [switch]$DryRun
)

$ErrorActionPreference = 'Stop'
$RepoRoot = Resolve-Path (Join-Path $PSScriptRoot '..')

Push-Location $RepoRoot
try {
    $req = Join-Path $RepoRoot 'scripts\ladder\requirements.txt'
    if (-not (Get-Command python -ErrorAction SilentlyContinue)) {
        Write-Error 'python not on PATH. Install Python 3 and Laragon MySQL (docs/LOCAL_DEV.md).'
    }

    Write-Host 'Checking pymysql...' -ForegroundColor Cyan
    python -m pip install -q -r $req

    $args = @('-m', 'scripts.ladder', 'run', '--target', 'local')
    if ($DryRun) {
        $args += '--dry-run'
        Write-Host 'DRY RUN — no database writes' -ForegroundColor Yellow
    } else {
        Write-Host 'Full replay — writes to database in ko2unitydb_config.php' -ForegroundColor Cyan
    }

    python @args
    if ($LASTEXITCODE -ne 0) {
        Write-Error "Replay failed (exit $LASTEXITCODE)."
    }
    Write-Host '[OK] Replay finished.' -ForegroundColor Green
    if (-not $DryRun) {
        Write-Host 'Spot-check: http://ratingskickoff.test/player/profile.php?id=237' -ForegroundColor Cyan
    }
} finally {
    Pop-Location
}
