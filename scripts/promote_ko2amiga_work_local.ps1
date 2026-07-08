# PROMOTE-1 — point local PHP Amiga config at ko2amiga_work (one-time / after clone).
# Usage (repo root): powershell -ExecutionPolicy Bypass -File scripts\promote_ko2amiga_work_local.ps1
$ErrorActionPreference = 'Stop'
$RepoRoot = Split-Path -Parent $PSScriptRoot
$local = Join-Path $RepoRoot 'site\config\ko2amiga_config.local.php'
$example = Join-Path $RepoRoot 'site\config\ko2amiga_config.local.php.example'

if (-not (Test-Path $local)) {
    if (-not (Test-Path $example)) { Write-Error "Missing $example" }
    Copy-Item -LiteralPath $example -Destination $local
    Write-Host "Created $local from example (ko2amiga_work)."
    exit 0
}

$content = [System.IO.File]::ReadAllText($local)
$updated = $content -replace "\`$database\s*=\s*'ko2amiga_db'\s*;", "`$database = 'ko2amiga_work';"
if ($updated -eq $content -and $content -notmatch 'ko2amiga_work') {
    Write-Warning 'ko2amiga_config.local.php has no ko2amiga_db line to replace — edit $database manually.'
    exit 1
}
if ($updated -ne $content) {
    [System.IO.File]::WriteAllText($local, $updated, (New-Object System.Text.UTF8Encoding $false))
    Write-Host "Updated $local -> database = ko2amiga_work"
} else {
    Write-Host "Already on ko2amiga_work: $local"
}