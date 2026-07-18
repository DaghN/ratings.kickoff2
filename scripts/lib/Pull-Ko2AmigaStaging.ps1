# Pull staged ko2amiga_db -> local ko2amiga_work (PULL-1a).
# Dot-source from scripts\pull_ko2amiga_from_staging.ps1

function Get-AmigaOpsPasswordFromConfig {
    param([string]$RepoRoot)
    if ($env:AMIGA_OPS_PASSWORD -and $env:AMIGA_OPS_PASSWORD.Trim() -ne '') {
        return $env:AMIGA_OPS_PASSWORD.Trim()
    }
    $local = Join-Path $RepoRoot 'site\config\amiga_ops_password.local.php'
    if (-not (Test-Path -LiteralPath $local)) {
        throw "Missing Amiga ops password. Copy site\config\amiga_ops_password.local.php.example → amiga_ops_password.local.php (or set env AMIGA_OPS_PASSWORD)."
    }
    $text = [System.IO.File]::ReadAllText($local)
    # Single-quoted pattern so $password is literal (not a PowerShell variable).
    if ($text -match '(?m)^\s*\$password\s*=\s*''([^'']*)''\s*;') {
        $pwd = $Matches[1]
        if ([string]::IsNullOrWhiteSpace($pwd)) {
            throw "amiga_ops_password.local.php has empty `$password."
        }
        return $pwd
    }
    throw "Could not parse `$password from $local"
}

function Invoke-Ko2AmigaStagingPull {
    param(
        [string]$StagingBaseUrl = 'https://ratings.kickoff2.com',
        [string]$OnceKey = 'ko2amiga-export-one-shot',
        [string]$Password = '',
        [string]$TargetDatabase = 'ko2amiga_work',
        [string]$SourceDatabaseName = 'ko2amiga_db',
        [switch]$SkipGenerate,
        [switch]$Simul,
        [switch]$Force
    )

    $ErrorActionPreference = 'Stop'
    $RepoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
    if ([string]::IsNullOrWhiteSpace($Password)) {
        $Password = Get-AmigaOpsPasswordFromConfig -RepoRoot $RepoRoot
    }
    . (Join-Path $PSScriptRoot 'LaragonMysql.ps1')

    $MysqlExe = Find-LaragonMysqlExe
    if (-not $MysqlExe) {
        throw 'Laragon mysql.exe not found (start Laragon).'
    }

    $PullDir = Join-Path $RepoRoot 'data\amiga\pulls'
    New-Item -ItemType Directory -Force -Path $PullDir | Out-Null
    $Stamp = Get-Date -Format 'yyyy-MM-dd_HHmmss'
    $RawDump = Join-Path $PullDir "ko2amiga_staging_pull_$Stamp.sql"
    $WorkDump = Join-Path $PullDir 'ko2amiga_staging_pull_latest.sql'
    $SyncManifest = Join-Path $RepoRoot 'data\amiga\modern\staging-sync-last.json'
    New-Item -ItemType Directory -Force -Path (Split-Path $SyncManifest) | Out-Null

    if (-not $Force) {
        Write-Host ''
        Write-Host 'This REPLACES local ko2amiga_work with staged ground.' -ForegroundColor Yellow
        Write-Host 'Unpushed local repair-shop changes will be lost.' -ForegroundColor Yellow
        $answer = Read-Host 'Type YES to continue'
        if ($answer -ne 'YES') {
            throw 'Aborted.'
        }
    }

    $base = $StagingBaseUrl.TrimEnd('/')
    $query = "once=$([uri]::EscapeDataString($OnceKey))&pwd=$([uri]::EscapeDataString($Password))"
    $exportScript = "$base/amiga/run_export_ko2amiga.php"

    $generateMeta = $null
    if (-not $SkipGenerate) {
        Write-Host "Generating staging dump via $exportScript ..."
        $genUrl = ('{0}?{1}&generate=1&format=json' -f $exportScript, $query)
        try {
            $generateMeta = Invoke-RestMethod -Uri $genUrl -Method Get -TimeoutSec 600
        } catch {
            throw "Staging generate failed: $($_.Exception.Message)"
        }
        if (-not $generateMeta.ok) {
            # Staging may still run export-v1/v2 (HTML only). Fall back to HTML probe.
            Write-Warning 'JSON generate response missing ok=true; probing HTML export page ...'
            $html = (Invoke-WebRequest -Uri $genUrl -UseBasicParsing -TimeoutSec 600).Content
            if ($html -notmatch 'status:\s*OK') {
                throw 'Staging generate did not report status: OK in HTML response.'
            }
            $bytes = 0
            if ($html -match 'bytes:\s*([\d,]+)') {
                $bytes = [int]($Matches[1] -replace ',', '')
            }
            $method = 'mysqldump'
            if ($html -match 'method:\s*(\S+)') {
                $method = $Matches[1]
            }
            $generateMeta = @{
                ok       = $true
                generate = @{
                    method = $method
                    bytes  = $bytes
                    source = 'html-fallback'
                }
            }
        }
        $elapsed = if ($null -ne $generateMeta.generate.elapsed) { $generateMeta.generate.elapsed } else { '?' }
        Write-Host ("  OK method={0} bytes={1} elapsed={2}s" -f $generateMeta.generate.method, $generateMeta.generate.bytes, $elapsed)
    } else {
        Write-Host 'SkipGenerate: using existing staging dump file.'
        $statusUrl = ('{0}?{1}&status=1' -f $exportScript, $query)
        try {
            $status = Invoke-RestMethod -Uri $statusUrl -Method Get -TimeoutSec 120
        } catch {
            $status = $null
        }
        if (-not $status -or -not $status.exists) {
            $html = (Invoke-WebRequest -Uri ('{0}?{1}' -f $exportScript, $query) -UseBasicParsing -TimeoutSec 120).Content
            if ($html -notmatch 'ko2amiga_staging_pull\.sql') {
                throw 'No staging dump on server. Run without -SkipGenerate first.'
            }
            $bytes = 0
            if ($html -match 'bytes:\s*([\d,]+)') {
                $bytes = [int]($Matches[1] -replace ',', '')
            }
            $status = @{ exists = $true; bytes = $bytes; manifest = @{ source = 'html-fallback' } }
        }
        $generateMeta = @{ ok = $true; generate = $status.manifest }
        Write-Host ("  existing bytes={0}" -f $status.bytes)
    }

    Write-Host 'Downloading dump ...'
    $dlUrl = ('{0}?{1}&download=1' -f $exportScript, $query)
    Invoke-WebRequest -Uri $dlUrl -OutFile $RawDump -UseBasicParsing -TimeoutSec 900
    $rawBytes = (Get-Item $RawDump).Length
    $head = [System.IO.File]::ReadAllText($RawDump, [System.Text.Encoding]::UTF8).Substring(0, [Math]::Min(120, $rawBytes))
    if ($head -match '^\s*<!DOCTYPE|^\s*<html') {
        throw @"
Download returned HTML, not SQL. Staging likely still has export-v1 (no download=1).
WinSCP sync these files to staging, then re-run pull:
  site/public_html/amiga/run_export_ko2amiga.php
  site/public_html/amiga/includes/amiga_staging_export_lib.php
"@
    }
    if ($rawBytes -lt 1024) {
        throw "Downloaded dump too small ($rawBytes bytes) - likely an error page."
    }
    $expectedBytes = $null
    if ($generateMeta.generate.bytes) {
        $expectedBytes = [int]$generateMeta.generate.bytes
        if ($expectedBytes -gt 0 -and [math]::Abs($rawBytes - $expectedBytes) -gt 4096) {
            Write-Warning "Download size $rawBytes differs from generate bytes $expectedBytes (continuing)."
        }
    }
    $sizeMb = [math]::Round($rawBytes / 1048576, 1)
    Write-Host "  saved $RawDump ($sizeMb MB)"

    Write-Host "Rewriting $SourceDatabaseName -> $TargetDatabase for local import ..."
    $enc = New-Object System.Text.UTF8Encoding $false
    $reader = New-Object System.IO.StreamReader($RawDump, $enc)
    $writer = New-Object System.IO.StreamWriter($WorkDump, $false, $enc)
    try {
        while (-not $reader.EndOfStream) {
            $line = $reader.ReadLine()
            if ($null -ne $line) {
                if ($line -match '^(Warning:|mysqldump:)') {
                    continue
                }
                $writer.WriteLine(($line -replace [regex]::Escape($SourceDatabaseName), $TargetDatabase))
            }
        }
    } finally {
        $writer.Close()
        $reader.Close()
    }
    Copy-Item -Path $WorkDump -Destination $RawDump -Force

    Write-Host "Recreating local database $TargetDatabase ..."
    & $MysqlExe -u root -e "DROP DATABASE IF EXISTS ``$TargetDatabase``; CREATE DATABASE ``$TargetDatabase`` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"
    if ($LASTEXITCODE -ne 0) {
        throw "mysql recreate $TargetDatabase failed (exit $LASTEXITCODE)"
    }

    Write-Host 'Importing (several minutes for large dumps) ...'
    $dumpForCmd = $WorkDump -replace '/', '\'
    cmd /c "`"$MysqlExe`" -u root --default-character-set=utf8mb4 $TargetDatabase < `"$dumpForCmd`""
    if ($LASTEXITCODE -ne 0) {
        throw "mysql import failed (exit $LASTEXITCODE). ko2amiga_work may be empty - re-run pull."
    }

    Write-Host 'Spot-check counts ...'
    foreach ($pair in @(
            @('tournaments', 'tournaments'),
            @('players', 'amiga_players'),
            @('games', 'amiga_games')
        )) {
        $label = $pair[0]
        $table = $pair[1]
        $n = & $MysqlExe -u root -N -e "SELECT COUNT(*) FROM ``$table``" $TargetDatabase 2>&1
        if ($LASTEXITCODE -ne 0) {
            throw "post-import count failed for ${table}: $n"
        }
        Write-Host "  ${label}: $n"
    }

    $sync = [ordered]@{
        pulled_at       = (Get-Date).ToUniversalTime().ToString('yyyy-MM-dd HH:mm:ss') + ' UTC'
        source          = 'staging'
        staging_url     = $base
        target_database = $TargetDatabase
        dump_bytes      = $rawBytes
        dump_path       = (Resolve-Path $WorkDump).Path
        raw_dump_path   = (Resolve-Path $RawDump).Path
        generate        = $generateMeta.generate
        simul           = 'not_run'
    }

    if ($Simul) {
        Write-Host 'Running simul on ko2amiga_work ...'
        Push-Location $RepoRoot
        try {
            python -m scripts.amiga simul
            if ($LASTEXITCODE -ne 0) {
                throw "simul failed (exit $LASTEXITCODE)"
            }
            $sync.simul = 'ok'
        } finally {
            Pop-Location
        }
    }

    $json = $sync | ConvertTo-Json -Depth 6
    [System.IO.File]::WriteAllText($SyncManifest, $json + "`n", $enc)
    Write-Host "Wrote $SyncManifest"

    Write-Host ''
    Write-Host 'Pull complete. Local ko2amiga_work now mirrors staged ground.' -ForegroundColor Green
    if (-not $Simul) {
        Write-Host 'Simul not run (default). When needed: python -m scripts.amiga simul' -ForegroundColor Yellow
    }

    return [pscustomobject]$sync
}