# Refresh work — clone ko2unity_baseline → ko2unity_work (prepare platform v2).
# Usage: powershell -ExecutionPolicy Bypass -File scripts\refresh_local_work_db.ps1
#        powershell -ExecutionPolicy Bypass -File scripts\refresh_local_work_db.ps1 -DryRun

param([switch]$DryRun)

$ErrorActionPreference = 'Stop'
$RepoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
Push-Location $RepoRoot
try {
    $args = @('-m', 'scripts.work_prepare', 'refresh-work', '--target', 'local-work')
    if ($DryRun) { $args += '--dry-run' }
    python @args
    if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
} finally {
    Pop-Location
}
