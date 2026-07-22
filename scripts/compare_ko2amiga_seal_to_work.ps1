# Import an Amiga backup seal into a SIDE database and compare to ko2amiga_work.
# Does NOT touch ko2amiga_work.
#
# Usage:
#   powershell -ExecutionPolicy Bypass -File scripts\compare_ko2amiga_seal_to_work.ps1 `
#     -SealDir "data\amiga\seals\seal-20260721-232103Z-manual"
#
# WinSCP the seal folder from staging amiga/_backups/<seal-id>/ into SealDir first
# (seals are not downloadable over HTTP — 403 by design).

param(
    [Parameter(Mandatory = $true)]
    [string]$SealDir,
    [string]$SideDatabase = 'ko2amiga_seal_cmp',
    [string]$WorkDatabase = 'ko2amiga_work'
)

$ErrorActionPreference = 'Stop'
$RepoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
. (Join-Path $PSScriptRoot 'lib\LaragonMysql.ps1')

$MysqlExe = Find-LaragonMysqlExe
if (-not $MysqlExe) { throw 'Laragon mysql.exe not found.' }

if (-not [System.IO.Path]::IsPathRooted($SealDir)) {
    $SealDir = Join-Path $RepoRoot $SealDir
}
$SealDir = (Resolve-Path -LiteralPath $SealDir).Path
$manifestPath = Join-Path $SealDir 'ko2amiga_manifest.json'
if (-not (Test-Path -LiteralPath $manifestPath)) {
    throw "Missing manifest: $manifestPath"
}

# List seal parts via PHP (one filename per line) — avoids PS 5.1 JSON array quirks.
$php = 'C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe'
$helper = Join-Path $env:TEMP ("amiga_seal_parts_" + [guid]::NewGuid().ToString('N') + ".php")
$partsListPath = Join-Path $env:TEMP ("amiga_seal_parts_" + [guid]::NewGuid().ToString('N') + ".txt")
$helperPhp = @'
<?php
$manifest = $argv[1] ?? '';
$out = $argv[2] ?? '';
$j = json_decode((string) file_get_contents($manifest), true);
$parts = $j['parts'] ?? [];
$lines = [];
foreach ($parts as $p) {
    if (is_string($p)) {
        $lines[] = $p;
    } elseif (is_array($p) && isset($p['file'])) {
        $lines[] = (string) $p['file'];
    }
}
file_put_contents($out, implode("\n", $lines) . "\n");
'@
[System.IO.File]::WriteAllText($helper, $helperPhp, (New-Object System.Text.UTF8Encoding $false))
& $php $helper $manifestPath $partsListPath
$helperExit = $LASTEXITCODE
Remove-Item -LiteralPath $helper -Force -ErrorAction SilentlyContinue
if ($helperExit -ne 0 -or -not (Test-Path -LiteralPath $partsListPath)) {
    throw "Failed to read parts from manifest via PHP."
}
$parts = @(
    Get-Content -LiteralPath $partsListPath |
        ForEach-Object { $_.Trim() } |
        Where-Object { $_ -ne '' }
)
Remove-Item -LiteralPath $partsListPath -Force
if ($parts.Count -lt 1) { throw 'Manifest has no parts.' }

Write-Host "Seal: $SealDir"
Write-Host "Parts: $($parts.Count)"
Write-Host "Side DB: $SideDatabase (work DB $WorkDatabase left untouched)"

Write-Host "Recreating $SideDatabase ..."
& $MysqlExe -u root -e "DROP DATABASE IF EXISTS ``$SideDatabase``; CREATE DATABASE ``$SideDatabase`` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"
if ($LASTEXITCODE -ne 0) { throw "Failed to create $SideDatabase" }

$tmpDir = Join-Path $RepoRoot "data\amiga\seals\_import_tmp_$SideDatabase"
if (Test-Path $tmpDir) { Remove-Item -LiteralPath $tmpDir -Recurse -Force }
New-Item -ItemType Directory -Path $tmpDir | Out-Null

$i = 0
foreach ($part in $parts) {
    $i++
    $name = [string]$part
    # manifest may be objects {file: ...} or plain strings
    if ($part -is [string]) {
        $name = $part
    } elseif ($part.file) {
        $name = [string]$part.file
    } elseif ($part.name) {
        $name = [string]$part.name
    }
    $src = Join-Path $SealDir $name
    if (-not (Test-Path -LiteralPath $src)) {
        throw "Missing seal part: $src"
    }
    Write-Host ("  [{0}/{1}] {2}" -f $i, $parts.Count, $name)
    $dst = Join-Path $tmpDir $name
    $enc = New-Object System.Text.UTF8Encoding $false
    $reader = New-Object System.IO.StreamReader($src, $enc)
    $writer = New-Object System.IO.StreamWriter($dst, $false, $enc)
    try {
        while (-not $reader.EndOfStream) {
            $line = $reader.ReadLine()
            if ($null -eq $line) { continue }
            $line = $line -replace 'ko2amiga_db', $SideDatabase
            $line = $line -replace 'ko2amiga_work', $SideDatabase
            $writer.WriteLine($line)
        }
    } finally {
        $writer.Close()
        $reader.Close()
    }
    $dstCmd = $dst -replace '/', '\'
    cmd /c "`"$MysqlExe`" -u root --default-character-set=utf8mb4 $SideDatabase < `"$dstCmd`""
    if ($LASTEXITCODE -ne 0) {
        throw "Import failed on part $name (exit $LASTEXITCODE)"
    }
}

Remove-Item -LiteralPath $tmpDir -Recurse -Force
Write-Host "Import OK. Comparing $WorkDatabase vs $SideDatabase ..."

$php = 'C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe'
$probe = Join-Path $RepoRoot 'scripts\oneoff\amiga_compare_two_dbs.php'
& $php $probe $WorkDatabase $SideDatabase
exit $LASTEXITCODE