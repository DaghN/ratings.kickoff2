# Fix an existing data/dumps/*.sql that still CREATE DATABASE ko2unity_db.
# Usage: powershell -ExecutionPolicy Bypass -File scripts\sanitize_prod_dump.ps1

param(
    [string]$SourcePath,
    [string]$DestPath
)

$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'lib\LaragonMysql.ps1')
. (Join-Path $PSScriptRoot 'lib\ProdDumpSanitize.ps1')
$RepoRoot = Get-RepoRoot

if (-not $SourcePath) {
    $SourcePath = Join-Path $RepoRoot 'data\dumps\ko2unity_prod-2026-06-02.sql'
}
if (-not $DestPath) {
    $DestPath = $SourcePath
}

if (Test-ProdDumpSanitized -Path $SourcePath) {
    Write-Host "[OK] Already sanitized: $SourcePath" -ForegroundColor Green
    exit 0
}

$raw = $SourcePath
if ($SourcePath -eq $DestPath) {
    $raw = "$SourcePath.unsanitized.bak"
    Copy-Item -LiteralPath $SourcePath -Destination $raw -Force
    Write-Host "Backed up unsanitized file to:`n  $raw"
}

Write-SanitizedProdDump -SourcePath $raw -DestPath $DestPath
