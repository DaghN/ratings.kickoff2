# Pull staged ko2amiga_db -> local ko2amiga_work (PULL-1a).
# Dot-source from scripts\pull_ko2amiga_from_staging.ps1

function Get-AmigaOpsPasswordFromConfig {
    param(
        [string]$RepoRoot,
        [ValidateSet('admin', 'organizer')]
        [string]$Role = 'admin'
    )
    if ($Role -eq 'admin' -and $env:AMIGA_OPS_ADMIN_PASSWORD -and $env:AMIGA_OPS_ADMIN_PASSWORD.Trim() -ne '') {
        return $env:AMIGA_OPS_ADMIN_PASSWORD.Trim()
    }
    if ($env:AMIGA_OPS_PASSWORD -and $env:AMIGA_OPS_PASSWORD.Trim() -ne '') {
        return $env:AMIGA_OPS_PASSWORD.Trim()
    }
    $candidates = @(
        (Join-Path $RepoRoot 'site\public_html\amiga\_ops\amiga_ops_password.local.php'),
        (Join-Path $RepoRoot 'site\config\amiga_ops_password.local.php')
    )
    foreach ($local in $candidates) {
        if (-not (Test-Path -LiteralPath $local)) {
            continue
        }
        $text = [System.IO.File]::ReadAllText($local)
        $admin = $null
        $organizer = $null
        $legacy = $null
        if ($text -match "(?m)^\s*\`$admin_password\s*=\s*'([^']*)'\s*;") {
            $admin = $Matches[1]
        } elseif ($text -match '(?m)^\s*\$admin_password\s*=\s*"([^"]*)"\s*;') {
            $admin = $Matches[1]
        }
        if ($text -match "(?m)^\s*\`$organizer_password\s*=\s*'([^']*)'\s*;") {
            $organizer = $Matches[1]
        } elseif ($text -match '(?m)^\s*\$organizer_password\s*=\s*"([^"]*)"\s*;') {
            $organizer = $Matches[1]
        }
        if ($text -match "(?m)^\s*\`$password\s*=\s*'([^']*)'\s*;") {
            $legacy = $Matches[1]
        } elseif ($text -match '(?m)^\s*\$password\s*=\s*"([^"]*)"\s*;') {
            $legacy = $Matches[1]
        }
        if ($Role -eq 'admin') {
            $pwd = if (-not [string]::IsNullOrWhiteSpace($admin)) { $admin } else { $legacy }
        } else {
            $pwd = if (-not [string]::IsNullOrWhiteSpace($organizer)) { $organizer } else { $legacy }
        }
        if ([string]::IsNullOrWhiteSpace($pwd)) {
            throw "amiga_ops_password.local.php missing `$Role password ($local)."
        }
        return $pwd
    }
    throw "Missing Amiga ops password. Create site\public_html\amiga\_ops\amiga_ops_password.local.php (or site\config\…) or set env AMIGA_OPS_ADMIN_PASSWORD / AMIGA_OPS_PASSWORD."
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
        $Password = Get-AmigaOpsPasswordFromConfig -RepoRoot $RepoRoot -Role admin
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
        Write-Host "This REPLACES local database $TargetDatabase with staged ground." -ForegroundColor Yellow
        if ($TargetDatabase -eq 'ko2amiga_work') {
            Write-Host 'Unpushed local repair-shop changes will be lost.' -ForegroundColor Yellow
        } else {
            Write-Host 'ko2amiga_work is NOT touched (side-database pull).' -ForegroundColor Green
        }
        $answer = Read-Host 'Type YES to continue'
        if ($answer -ne 'YES') {
            throw 'Aborted.'
        }
    } elseif ($TargetDatabase -ne 'ko2amiga_work') {
        Write-Host "Side pull into $TargetDatabase (ko2amiga_work left untouched)." -ForegroundColor Green
    }

    $base = $StagingBaseUrl.TrimEnd('/')
    $exportScript = "$base/amiga/run_export_ko2amiga.php"
    $onceQuery = "once=$([uri]::EscapeDataString($OnceKey))"

    $generateMeta = $null
    if (-not $SkipGenerate) {
        Write-Host "Generating staging dump via $exportScript ..."
        $genUri = "{0}?{1}" -f $exportScript, $onceQuery
        $genBody = @{
            once     = $OnceKey
            pwd      = $Password
            generate = '1'
            format   = 'json'
        }
        try {
            $generateMeta = Invoke-RestMethod -Uri $genUri -Method Post -Body $genBody -TimeoutSec 600
        } catch {
            throw "Staging generate failed: $($_.Exception.Message)"
        }
        if (-not $generateMeta.ok) {
            # Staging may still run export-v1/v2 (HTML only). Fall back to HTML probe.
            Write-Warning 'JSON generate response missing ok=true; probing HTML export page ...'
            $html = (Invoke-WebRequest -Uri $genUri -Method Post -Body $genBody -UseBasicParsing -TimeoutSec 600).Content
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
        $statusUri = "{0}?{1}" -f $exportScript, $onceQuery
        $statusBody = @{
            once   = $OnceKey
            pwd    = $Password
            status = '1'
        }
        try {
            $status = Invoke-RestMethod -Uri $statusUri -Method Post -Body $statusBody -TimeoutSec 120
        } catch {
            $status = $null
        }
        if (-not $status -or -not $status.exists) {
            $html = (Invoke-WebRequest -Uri $statusUri -Method Post -Body @{ once = $OnceKey; pwd = $Password } -UseBasicParsing -TimeoutSec 120).Content
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
    $dlUri = "{0}?{1}" -f $exportScript, $onceQuery
    $dlBody = @{
        once     = $OnceKey
        pwd      = $Password
        download = '1'
    }
    Invoke-WebRequest -Uri $dlUri -Method Post -Body $dlBody -OutFile $RawDump -UseBasicParsing -TimeoutSec 900
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
    Write-Host "Pull complete. Local $TargetDatabase now mirrors staged ground." -ForegroundColor Green
    if ($TargetDatabase -ne 'ko2amiga_work') {
        Write-Host 'ko2amiga_work was not modified.' -ForegroundColor Green
    }
    if (-not $Simul) {
        Write-Host 'Simul not run (default). When needed: python -m scripts.amiga simul' -ForegroundColor Yellow
    }

    return [pscustomobject]$sync
}