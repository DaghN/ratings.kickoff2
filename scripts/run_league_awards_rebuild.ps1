# REP-012 local: rebuild league_period, player_league_award, player_league_totals.
# Usage: powershell -ExecutionPolicy Bypass -File scripts\run_league_awards_rebuild.ps1

$ErrorActionPreference = 'Stop'
$PhpExe = 'C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe'
if (-not (Test-Path $PhpExe)) {
    Get-ChildItem 'C:\laragon\bin\php\*\php.exe' | Select-Object -First 1 | ForEach-Object { $PhpExe = $_.FullName }
}
if (-not (Test-Path $PhpExe)) {
    Write-Error 'php.exe not found (Laragon — docs/LOCAL_DEV.md).'
}

$RepoRoot = Resolve-Path (Join-Path $PSScriptRoot '..')
& $PhpExe (Join-Path $RepoRoot 'scripts\finalize_league_periods.php') '--full-rebuild'
if ($LASTEXITCODE -ne 0) {
    Write-Error "League awards rebuild failed (exit $LASTEXITCODE)."
}
Write-Host '[OK] league awards rebuilt.' -ForegroundColor Green
