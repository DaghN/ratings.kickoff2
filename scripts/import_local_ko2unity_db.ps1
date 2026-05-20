# Import local dev dump into Laragon MySQL/MariaDB (Windows).
# Usage (from repo root):
#   powershell -ExecutionPolicy Bypass -File scripts\import_local_ko2unity_db.ps1
#
# Requires Laragon MySQL running. Adjust $MysqlExe if your Laragon path differs.

$ErrorActionPreference = 'Stop'
$RepoRoot = Split-Path -Parent $PSScriptRoot
$DumpPath = Join-Path $RepoRoot 'data\dumps\ko2unity_db-2026-05-20.sql'

if (-not (Test-Path $DumpPath)) {
    Write-Error "Dump not found: $DumpPath"
}

$Candidates = @(
    'C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe',
    'C:\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysql.exe',
    'C:\laragon\bin\mariadb\mariadb-11.4.2-winx64\bin\mysql.exe',
    'C:\laragon\bin\mariadb\mariadb-10.11.8-winx64\bin\mysql.exe'
)

$MysqlExe = $Candidates | Where-Object { Test-Path $_ } | Select-Object -First 1
if (-not $MysqlExe) {
    Write-Host 'Laragon mysql.exe not found. Use HeidiSQL or Laragon Terminal (see data/README.md).'
    exit 1
}

Write-Host "Using: $MysqlExe"
Write-Host "Importing (several minutes, ~600 MB): $DumpPath"
Write-Host 'Do not close this window until finished.'
# CMD stdin redirect handles large dumps; avoid loading whole file into PowerShell memory.
$DumpForCmd = $DumpPath -replace '/', '\'
cmd /c "`"$MysqlExe`" -u root --default-character-set=utf8mb4 < `"$DumpForCmd`""
if ($LASTEXITCODE -ne 0) {
    Write-Error "mysql exited with code $LASTEXITCODE"
}

Write-Host 'Done. Verify with:'
Write-Host '  mysql -u root -e "USE ko2unity_db; SELECT COUNT(*) FROM ratedresults;"'
