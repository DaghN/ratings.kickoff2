# Drop ko2unity_work and clone fresh from ko2unity_baseline (fast reset before sim/replay).
# Usage: powershell -ExecutionPolicy Bypass -File scripts\reset_local_work_db.ps1

$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'lib\LaragonMysql.ps1')

$MysqlExe = Find-LaragonMysqlExe
$DumpExe = Find-LaragonMysqldumpExe
if (-not $MysqlExe -or -not $DumpExe) {
    Write-Error 'Laragon mysql/mysqldump not found. Start Laragon (docs/LOCAL_DEV.md).'
}

$Baseline = 'ko2unity_baseline'
$Work = 'ko2unity_work'

if ($Work -eq 'ko2unity_db' -or $Baseline -eq 'ko2unity_db') {
    Write-Error 'Refusing to run: work/baseline must not be ko2unity_db (dev database).'
}

$check = & $MysqlExe -u root -N -e "SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='$Baseline';" 2>&1
if ($LASTEXITCODE -ne 0 -or $check -ne '1') {
    Write-Error "Baseline database '$Baseline' missing. Run scripts\setup_local_prod_sandbox.ps1 first."
}

Write-Host "Resetting '$Work' from '$Baseline' (a few minutes)..." -ForegroundColor Cyan
& $MysqlExe -u root -e "DROP DATABASE IF EXISTS ``$Work``; CREATE DATABASE ``$Work`` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"
if ($LASTEXITCODE -ne 0) { Write-Error 'DROP/CREATE work database failed.' }

& $DumpExe -u root --single-transaction --routines --events $Baseline | & $MysqlExe -u root $Work
if ($LASTEXITCODE -ne 0) { Write-Error 'mysqldump | mysql clone failed.' }

$games = & $MysqlExe -u root -N -e "SELECT COUNT(*) FROM ``$Work``.ratedresults;"
Write-Host "[OK] $Work ready - ratedresults rows: $games" -ForegroundColor Green
