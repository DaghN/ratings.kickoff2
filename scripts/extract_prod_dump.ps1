# Extract Steve's prod SQL from Downloads zip -> sanitized dump in data/dumps/.
# The on-disk file targets ko2unity_baseline only (never ko2unity_db).
#
# Usage: powershell -ExecutionPolicy Bypass -File scripts\extract_prod_dump.ps1
#        ... -Force   re-extract and re-sanitize

param([switch]$Force)

$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'lib\LaragonMysql.ps1')
. (Join-Path $PSScriptRoot 'lib\ProdDumpSanitize.ps1')
$RepoRoot = Get-RepoRoot

$ZipPath = Join-Path $env:USERPROFILE 'Downloads\KOOL_DB_Live.zip'
$OutDir = Join-Path $RepoRoot 'data\dumps'
$OutFile = Join-Path $OutDir 'ko2unity_prod-2026-06-02.sql'
$RawTemp = Join-Path $OutDir '_ko2unity_prod_raw.tmp.sql'

if (-not (Test-Path $ZipPath)) {
    Write-Error "Prod zip not found: $ZipPath`nExpected Steve's KOOL_DB_Live.zip in Downloads."
}

New-Item -ItemType Directory -Force -Path $OutDir | Out-Null

if ((Test-Path $OutFile) -and (Test-ProdDumpSanitized -Path $OutFile) -and -not $Force) {
    $sizeMb = [math]::Round((Get-Item $OutFile).Length / 1MB, 1)
    Write-Host "[OK] Sanitized dump already present ($sizeMb MB):`n  $OutFile"
    Write-Host '     Pass -Force to re-extract from zip.'
    exit 0
}

Add-Type -AssemblyName System.IO.Compression.FileSystem
$zip = [System.IO.Compression.ZipFile]::OpenRead($ZipPath)
if ($zip.Entries.Count -ne 1) {
    $zip.Dispose()
    Write-Error "Expected exactly one file in $ZipPath"
}
$entry = $zip.Entries[0]
Write-Host "Extracting $($entry.FullName) to temp (minutes)..."
if (Test-Path $RawTemp) { Remove-Item -Force $RawTemp }
[System.IO.Compression.ZipFileExtensions]::ExtractToFile($entry, $RawTemp, $false)
$zip.Dispose()

Write-SanitizedProdDump -SourcePath $RawTemp -DestPath $OutFile
Remove-Item -Force $RawTemp

$finalMb = [math]::Round((Get-Item $OutFile).Length / 1MB, 1)
Write-Host "[OK] $finalMb MB sanitized dump:`n  $OutFile"
Write-Host '     CREATE DATABASE / USE -> ko2unity_baseline (dev ko2unity_db not referenced).'
