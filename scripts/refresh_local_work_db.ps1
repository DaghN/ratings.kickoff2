# Refresh work — clone ko2unity_baseline → ko2unity_work (PHP prepare platform v2).
# Usage: powershell -ExecutionPolicy Bypass -File scripts\refresh_local_work_db.ps1
#        powershell -ExecutionPolicy Bypass -File scripts\refresh_local_work_db.ps1 -DryRun
#
# Legacy Python path retired slice 3 — use run_prepare.php (same as prepare_local_work_db.ps1).

param([switch]$DryRun)

$ErrorActionPreference = 'Stop'
$RepoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$PhpCandidates = @(
    'C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe',
    'C:\laragon\bin\php\php-8.3.16-Win32-vs16-x64\php.exe'
)
$PhpExe = $null
foreach ($c in $PhpCandidates) {
    if (Test-Path $c) { $PhpExe = $c; break }
}
if (-not $PhpExe) {
    $PhpExe = 'php'
}

$Runner = Join-Path $RepoRoot 'site\public_html\ops\run_prepare.php'
Push-Location $RepoRoot
try {
    $args = @($Runner, 'refresh-work', '--target', 'local-work')
    if ($DryRun) { $args += '--dry-run' }
    & $PhpExe @args
    exit $LASTEXITCODE
} finally {
    Pop-Location
}
