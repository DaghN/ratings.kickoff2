# Apply ratedresults idA / idB indexes (Phase A).
# Usage: powershell -ExecutionPolicy Bypass -File scripts\apply_ratedresults_player_indexes.ps1
# Local helper for schema/migrations/001 only. Server applies should use reviewed SQL handoff.

param(
    [string]$Database = 'ko2unity_db',
    [string]$User = 'root',
    [string]$Password = '',
    [switch]$AllowNonLocal
)

$ErrorActionPreference = 'Stop'
$RepoRoot = Resolve-Path (Join-Path $PSScriptRoot '..')
$SqlFile = Join-Path $RepoRoot 'schema\migrations\001_ratedresults_player_indexes.sql'
$MysqlExe = 'C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe'

if (-not (Test-Path $MysqlExe)) {
    Write-Error "mysql.exe not found at $MysqlExe (start Laragon - docs/LOCAL_DEV.md)."
}

if (-not (Test-Path $SqlFile)) {
    Write-Error "Migration file not found at $SqlFile"
}

if ($Database -ne 'ko2unity_db' -and -not $AllowNonLocal) {
    Write-Error "Refusing to apply index helper to '$Database'. Use -AllowNonLocal only for an explicitly reviewed one-off."
}

$mysqlArgs = @('-u', $User, $Database)
if ($Password -ne '') {
    $mysqlArgs = @('-u', $User, "-p$Password", $Database)
}

Write-Host "Applying 001_ratedresults_player_indexes.sql to $Database..." -ForegroundColor Cyan
Get-Content -Raw -LiteralPath $SqlFile | & $MysqlExe @mysqlArgs
if ($LASTEXITCODE -ne 0) {
    Write-Error "Index migration failed (exit $LASTEXITCODE)."
}

Write-Host '[OK] ratedresults player indexes applied.' -ForegroundColor Green
