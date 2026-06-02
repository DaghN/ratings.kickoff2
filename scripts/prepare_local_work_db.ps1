# Full prepare on local work DB: refresh → migrate → seed catalog → zero derived (+ parity checks).
# Usage: powershell -ExecutionPolicy Bypass -File scripts\prepare_local_work_db.ps1
#        powershell -ExecutionPolicy Bypass -File scripts\prepare_local_work_db.ps1 -ZeroOnly
#        powershell -ExecutionPolicy Bypass -File scripts\prepare_local_work_db.ps1 -DryRun

param(
    [switch]$ZeroOnly,
    [switch]$DryRun
)

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
    $args = @($Runner, 'prepare', '--target', 'local-work')
    if ($ZeroOnly) { $args += '--zero-only' }
    if ($DryRun) { $args += '--dry-run' }
    & $PhpExe @args
    exit $LASTEXITCODE
} finally {
    Pop-Location
}
