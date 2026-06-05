# One-shot: create ko2amiga_db, config, import Access, Elo replay.
# Usage (repo root): powershell -ExecutionPolicy Bypass -File scripts\setup_ko2amiga_db.ps1

$ErrorActionPreference = 'Stop'
$RepoRoot = Split-Path -Parent $PSScriptRoot
Set-Location $RepoRoot

foreach ($pair in @(
        @('site\config\ko2amiga_config.local.php', 'site\config\ko2amiga_config.local.php.example'),
        @('site\public_html\amiga\ko2amiga_config.local.php', 'site\public_html\amiga\ko2amiga_config.local.php.example')
    )) {
    $dest = Join-Path $RepoRoot $pair[0]
    $src = Join-Path $RepoRoot $pair[1]
    if (-not (Test-Path $dest)) {
        Copy-Item $src $dest
        Write-Host "Created $dest"
    }
}

. (Join-Path $PSScriptRoot 'lib\LaragonMysql.ps1')
$MysqlExe = Find-LaragonMysqlExe
if (-not $MysqlExe) {
    Write-Error 'Laragon mysql.exe not found (start Laragon - docs/LOCAL_DEV.md).'
}
& $MysqlExe -u root -e 'CREATE DATABASE IF NOT EXISTS ko2amiga_db CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;'
Write-Host 'Database ko2amiga_db ready.'

python -m scripts.amiga run --recreate-schema
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

powershell -ExecutionPolicy Bypass -File (Join-Path $RepoRoot 'scripts\export_ko2amiga_db.ps1')
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

Write-Host ''
Write-Host 'Done. Local: http://ratingskickoff.test/amiga/rating.php'
Write-Host 'Staging: WinSCP sync public_html, then WhatsApp Steve (see docs/amiga-staging-handoff.md)'
