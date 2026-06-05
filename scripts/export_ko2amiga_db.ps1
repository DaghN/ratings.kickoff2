# Dump ko2amiga_db for staging import (gitignored output).
$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'lib\LaragonMysql.ps1')
$RepoRoot = Split-Path -Parent $PSScriptRoot
$MysqlExe = Find-LaragonMysqlExe
if (-not $MysqlExe) { Write-Error 'Laragon mysql.exe not found.' }
$DumpExe = Join-Path (Split-Path $MysqlExe -Parent) 'mysqldump.exe'
if (-not (Test-Path $DumpExe)) { Write-Error "mysqldump.exe not found at $DumpExe" }
# Fixed path so WinSCP public_html sync carries the dump to Steve.
$OutDir = Join-Path $RepoRoot 'site\public_html\amiga\_import'
New-Item -ItemType Directory -Force -Path $OutDir | Out-Null
$OutFile = Join-Path $OutDir 'ko2amiga_db.sql'
$ArchiveDir = Join-Path $RepoRoot 'data\amiga\exports'
New-Item -ItemType Directory -Force -Path $ArchiveDir | Out-Null
$Stamp = Get-Date -Format 'yyyy-MM-dd'
$ArchiveFile = Join-Path $ArchiveDir "ko2amiga_db-$Stamp.sql"

& $DumpExe -u root --databases ko2amiga_db --single-transaction | Set-Content -LiteralPath $OutFile -Encoding utf8
Copy-Item -LiteralPath $OutFile -Destination $ArchiveFile -Force
Write-Host "Wrote $OutFile (WinSCP sync this with public_html)"
Write-Host "Archive copy: $ArchiveFile"
