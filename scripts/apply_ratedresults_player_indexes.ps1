# Apply ratedresults idA / idB indexes (Phase A).
# Usage: powershell -ExecutionPolicy Bypass -File scripts\apply_ratedresults_player_indexes.ps1
# Optional: -Database kooldb -User root -Password ''

param(
    [string]$Database = 'ko2unity_db',
    [string]$User = 'root',
    [string]$Password = ''
)

$ErrorActionPreference = 'Stop'
$MysqlExe = 'C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe'
$SqlFile = Join-Path $PSScriptRoot 'sql\ratedresults_player_indexes.sql'

if (-not (Test-Path $MysqlExe)) {
    Write-Error "mysql.exe not found at $MysqlExe (start Laragon / check LOCAL_DEV.md)."
}
if (-not (Test-Path $SqlFile)) {
    Write-Error "SQL file missing: $SqlFile"
}

$mysqlArgs = @('-u', $User)
if ($Password -ne '') {
    $mysqlArgs += @("-p$Password")
}

Write-Host "Checking existing indexes on $Database.ratedresults..." -ForegroundColor Cyan
$existing = & $MysqlExe @mysqlArgs -N -e @"
SELECT INDEX_NAME
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = '$Database' AND TABLE_NAME = 'ratedresults'
  AND INDEX_NAME IN ('idx_ratedresults_idA', 'idx_ratedresults_idB');
"@ 2>&1
if ($LASTEXITCODE -ne 0) {
    Write-Error "MySQL check failed: $existing"
}

if ($existing -match 'idx_ratedresults_idA' -and $existing -match 'idx_ratedresults_idB') {
    Write-Host '[OK] Indexes already present — nothing to do.' -ForegroundColor Green
    exit 0
}

Write-Host "Creating indexes (may take a few seconds on ~75k rows)..." -ForegroundColor Cyan
$sql = @"
USE $Database;
CREATE INDEX idx_ratedresults_idA ON ratedresults (idA);
CREATE INDEX idx_ratedresults_idB ON ratedresults (idB);
"@
$sql | & $MysqlExe @mysqlArgs
if ($LASTEXITCODE -ne 0) {
    Write-Error 'CREATE INDEX failed.'
}

Write-Host '[OK] idx_ratedresults_idA and idx_ratedresults_idB created.' -ForegroundColor Green
Write-Host 'Verify: http://ratingskickoff.test/individual1_profile_diag.php?id=237' -ForegroundColor Cyan
