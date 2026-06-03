# REP-012 + REP-013 on local Laragon dev DB (ko2unity_db).
# Usage: powershell -ExecutionPolicy Bypass -File scripts\run_league_awards_rebuild.ps1

$ErrorActionPreference = 'Stop'
$RepoRoot = Split-Path -Parent $PSScriptRoot

$PhpExe = $null
foreach ($candidate in @(
    'C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe',
    'C:\laragon\bin\php\php-8.3.16-Win32-vs16-x64\php.exe'
)) {
    if (Test-Path $candidate) { $PhpExe = $candidate; break }
}
if (-not $PhpExe) {
    $PhpExe = (Get-Command php -ErrorAction SilentlyContinue).Source
}
if (-not $PhpExe) {
    throw 'php.exe not found — install Laragon PHP or add php to PATH.'
}

$OpsFinalize = Join-Path $RepoRoot 'site\public_html\ops\run_finalize_league.php'
& $PhpExe $OpsFinalize 'rebuild-all' '--target' 'local-dev'
