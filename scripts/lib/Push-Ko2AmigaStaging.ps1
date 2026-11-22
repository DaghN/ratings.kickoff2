# Push local ko2amiga_work -> staged ko2amiga_db without typing the admin password.
# Dot-source from scripts\push_ko2amiga_to_staging.ps1 (after Pull-Ko2AmigaStaging.ps1,
# which provides Get-AmigaOpsPasswordFromConfig).
#
# Flow: export (optional) -> staged-unchanged gate (SS-4) -> WinSCP upload of _import parts
#       -> server preview check -> POST apply part 1..N -> side-pull verify (work == staged).

function Find-WinScpCom {
    $candidates = @(
        (Join-Path $env:LOCALAPPDATA 'Programs\WinSCP\WinSCP.com'),
        (Join-Path ${env:ProgramFiles(x86)} 'WinSCP\WinSCP.com'),
        (Join-Path $env:ProgramFiles 'WinSCP\WinSCP.com')
    )
    foreach ($path in $candidates) {
        if ($path -and (Test-Path $path)) { return $path }
    }
    $cmd = Get-Command 'WinSCP.com' -ErrorAction SilentlyContinue
    if ($cmd) { return $cmd.Source }
    return $null
}

function Find-LaragonPhpExe {
    $found = Get-ChildItem 'C:\laragon\bin\php\*\php.exe' -ErrorAction SilentlyContinue |
        Sort-Object FullName -Descending | Select-Object -First 1
    if ($found) { return $found.FullName }
    return $null
}

function Invoke-WinScpScript {
    # Runs WinSCP.com with a temp script that opens the saved session. Password stays inside WinSCP.
    param(
        [string]$WinScpCom,
        [string]$Session,
        [string[]]$Commands,
        [string]$WorkDir
    )
    $lines = @('option batch abort', 'option confirm off', "open `"$Session`"", 'cd /public_html') + $Commands + @('exit')
    $scriptPath = Join-Path $WorkDir ("winscp_{0}.txt" -f ([guid]::NewGuid().ToString('N').Substring(0, 8)))
    [System.IO.File]::WriteAllLines($scriptPath, $lines, (New-Object System.Text.UTF8Encoding $false))
    try {
        $out = & $WinScpCom "/script=$scriptPath" 2>&1
        $code = $LASTEXITCODE
    } finally {
        Remove-Item -LiteralPath $scriptPath -ErrorAction SilentlyContinue
    }
    $clean = $out | Where-Object { $_ -notmatch '(?i)password|passphrase|hostkey|should not rely on saved sites' }
    if ($code -ne 0) {
        $clean | Select-Object -Last 15 | ForEach-Object { Write-Host "  winscp: $_" }
        throw "WinSCP failed (exit $code)."
    }
    return $clean
}

function Get-Ko2AmigaDumpFingerprint {
    # SHA-256 of a mysqldump, ignoring the per-run Host / "Dump completed" lines and
    # undoing the local DB-name rewrite done by the pull script.
    param(
        [string]$Path,
        [string]$RewrittenDbName = ''
    )
    $enc = New-Object System.Text.UTF8Encoding $false
    $sha = [System.Security.Cryptography.SHA256]::Create()
    $reader = New-Object System.IO.StreamReader($Path, $enc)
    try {
        while ($null -ne ($line = $reader.ReadLine())) {
            if ($line.StartsWith('-- Host:') -or $line.StartsWith('-- Dump completed') -or
                $line.StartsWith('Warning:') -or $line.StartsWith('mysqldump:')) {
                continue
            }
            if ($RewrittenDbName -and $RewrittenDbName -ne 'ko2amiga_db') {
                $line = $line.Replace($RewrittenDbName, 'ko2amiga_db')
            }
            $bytes = $enc.GetBytes($line + "`n")
            [void]$sha.TransformBlock($bytes, 0, $bytes.Length, $null, 0)
        }
        [void]$sha.TransformFinalBlock([byte[]]@(), 0, 0)
        return ([BitConverter]::ToString($sha.Hash) -replace '-', '').ToLowerInvariant()
    } finally {
        $reader.Dispose()
        $sha.Dispose()
    }
}

function Get-Ko2AmigaPushBaseline {
    # The staged state that local ko2amiga_work was built on. Accepted only from a full pull
    # into ko2amiga_work, or from a push that ended in a verified MATCH. Fails closed otherwise.
    param(
        [string]$RepoRoot,
        [string]$StagingBaseUrl
    )
    $syncPath = Join-Path $RepoRoot 'data\amiga\modern\staging-sync-last.json'
    if (-not (Test-Path -LiteralPath $syncPath)) {
        throw "No push baseline at $syncPath - do a full pull first (pull_ko2amiga_from_staging.ps1)."
    }
    $sync = Get-Content -Raw -LiteralPath $syncPath | ConvertFrom-Json
    $fromFullPull = ([string]$sync.target_database -eq 'ko2amiga_work')
    $fromVerifiedPush = ([string]$sync.baseline_kind -eq 'push-verified')
    if (-not ($fromFullPull -or $fromVerifiedPush)) {
        throw "Push baseline refused: it came from a side pull into '$($sync.target_database)', which says nothing about what ko2amiga_work contains. Do a full pull first."
    }
    if ([string]$sync.staging_url -and ([string]$sync.staging_url).TrimEnd('/') -ne $StagingBaseUrl.TrimEnd('/')) {
        throw "Push baseline refused: it belongs to $($sync.staging_url), not $StagingBaseUrl. Do a full pull first."
    }
    # Only the per-pull timestamped dump counts: ..._latest.sql is overwritten by every pull.
    $dump = [string]$sync.raw_dump_path
    if (-not $dump -or -not (Test-Path -LiteralPath $dump)) {
        throw "Push baseline refused: its dump is no longer on disk (pulled_at $($sync.pulled_at)). Do a full pull first."
    }
    return [pscustomobject]@{ Sync = $sync; Dump = $dump }
}

function Set-Ko2AmigaPushBaselineFromVerify {
    # After a push verified MATCH, staged == work, so this verify pull becomes the new baseline.
    param(
        [string]$RepoRoot,
        [datetime]$NotBefore
    )
    $modern = Join-Path $RepoRoot 'data\amiga\modern'
    $sidePath = Join-Path $modern 'staging-sidepull-last.json'
    if (-not (Test-Path -LiteralPath $sidePath)) {
        throw 'Verify pull left no staging-sidepull-last.json - push baseline not updated.'
    }
    if ((Get-Item -LiteralPath $sidePath).LastWriteTime -lt $NotBefore) {
        throw 'staging-sidepull-last.json is older than this push - push baseline not updated.'
    }
    $side = Get-Content -Raw -LiteralPath $sidePath | ConvertFrom-Json
    if ([string]$side.target_database -ne 'ko2amiga_staging_cmp') {
        throw "Verify pull targeted '$($side.target_database)', expected ko2amiga_staging_cmp - push baseline not updated."
    }
    $side | Add-Member -NotePropertyName baseline_kind -NotePropertyValue 'push-verified' -Force
    $json = $side | ConvertTo-Json -Depth 6
    [System.IO.File]::WriteAllText((Join-Path $modern 'staging-sync-last.json'), $json + "`n", (New-Object System.Text.UTF8Encoding $false))
}

function Test-Ko2AmigaStagedUnchanged {
    # SS-4 gate: staged must still equal the push baseline (Get-Ko2AmigaPushBaseline).
    param(
        [string]$RepoRoot,
        [string]$StagingBaseUrl,
        [string]$Password,
        [string]$WinScpCom,
        [string]$Session,
        [string]$WorkDir
    )
    $found = Get-Ko2AmigaPushBaseline -RepoRoot $RepoRoot -StagingBaseUrl $StagingBaseUrl
    $sync = $found.Sync
    $baseline = $found.Dump
    Write-Host "Staged-unchanged check against push baseline ($($sync.pulled_at), $([math]::Round((Get-Item $baseline).Length / 1MB, 1)) MB) ..."

    $onceKey = 'ko2amiga-export-one-shot'
    $genUri = "{0}/amiga/run_export_ko2amiga.php?once={1}" -f $StagingBaseUrl.TrimEnd('/'), $onceKey
    $genBody = @{ once = $onceKey; pwd = $Password; generate = '1'; format = 'json' }
    $meta = Invoke-RestMethod -Uri $genUri -Method Post -Body $genBody -TimeoutSec 600
    if (-not $meta.ok) {
        throw 'Staging export generate did not return ok=true.'
    }
    $nowBytes = [int64]$meta.generate.bytes
    $baseBytes = [int64]$sync.generate.bytes
    if ($baseBytes -gt 0 -and $nowBytes -ne $baseBytes) {
        throw "STAGED HAS CHANGED since the last pull ($($sync.pulled_at)): dump $nowBytes bytes vs $baseBytes. Pull first (pull_ko2amiga_from_staging.ps1 -Force), redo the local repair on top, then push."
    }

    $fresh = Join-Path $WorkDir 'staged_now.sql'
    Invoke-WinScpScript -WinScpCom $WinScpCom -Session $Session -WorkDir $WorkDir -Commands @(
        "get /public_html/amiga/_export/ko2amiga_staging_pull.sql `"$fresh`""
    ) | Out-Null
    $hNow = Get-Ko2AmigaDumpFingerprint -Path $fresh
    $hBase = Get-Ko2AmigaDumpFingerprint -Path $baseline -RewrittenDbName ([string]$sync.target_database)
    Remove-Item -LiteralPath $fresh -ErrorAction SilentlyContinue
    if ($hNow -ne $hBase) {
        throw "STAGED HAS CHANGED since the last pull ($($sync.pulled_at)): content fingerprint differs. Pull first, redo the local repair on top, then push."
    }
    Write-Host '  OK - staged is unchanged since the last pull.' -ForegroundColor Green
}

function Get-Ko2AmigaLocalImportManifest {
    param([string]$RepoRoot)
    $importDir = Join-Path $RepoRoot 'site\public_html\amiga\_import'
    $manifestPath = Join-Path $importDir 'ko2amiga_manifest.json'
    if (-not (Test-Path $manifestPath)) {
        throw "No export manifest at $manifestPath - run export_ko2amiga_work.ps1 first."
    }
    $manifest = Get-Content -Raw -LiteralPath $manifestPath | ConvertFrom-Json
    $parts = @()
    foreach ($p in $manifest.parts) {
        if ($p -is [string]) { $parts += $p } elseif ($p.file) { $parts += [string]$p.file } else { $parts += [string]$p.name }
    }
    if ($parts.Count -lt 1) { throw 'Export manifest lists no parts.' }
    $missing = $parts | Where-Object { -not (Test-Path (Join-Path $importDir $_)) }
    if ($missing) { throw "Export parts missing locally: $($missing -join ', ')" }
    $bytes = ($parts | ForEach-Object { (Get-Item (Join-Path $importDir $_)).Length } | Measure-Object -Sum).Sum
    return [pscustomobject]@{
        ImportDir    = $importDir
        ManifestPath = $manifestPath
        Generated    = [string]$manifest.generated
        Parts        = $parts
        Bytes        = $bytes
    }
}

function Invoke-Ko2AmigaImportPreview {
    param([string]$ImportUri, [string]$OnceKey, [string]$Password)
    # format=... marks this as a script POST so the password gate answers in place (no redirect).
    $body = @{ once = $OnceKey; pwd = $Password; format = 'text' }
    $html = (Invoke-WebRequest -Uri $ImportUri -Method Post -Body $body -UseBasicParsing -TimeoutSec 300).Content
    if ($html -match 'Incorrect password|admin password') {
        if ($html -notmatch 'preview only \(no import yet\)') { throw 'Staging rejected the admin password.' }
    }
    $generated = ''
    $parts = 0
    if ($html -match 'generated:\s*([0-9:\- ]+)') { $generated = $Matches[1].Trim() }
    if ($html -match 'parts:\s*(\d+)') { $parts = [int]$Matches[1] }
    return [pscustomobject]@{ Generated = $generated; Parts = $parts }
}

function Invoke-Ko2AmigaImportApply {
    param([string]$ImportUri, [string]$OnceKey, [string]$Password, [int]$PartCount)
    for ($n = 1; $n -le $PartCount; $n++) {
        $body = @{ once = $OnceKey; pwd = $Password; apply = '1'; part = [string]$n }
        $sw = [System.Diagnostics.Stopwatch]::StartNew()
        $html = (Invoke-WebRequest -Uri $ImportUri -Method Post -Body $body -UseBasicParsing -TimeoutSec 900).Content
        $sw.Stop()
        if ($html -match 'status:\s*FAIL' -or $html -match 'Import failed on part') {
            $err = ''
            if ($html -match '(?s)status:\s*FAIL(.{0,400})') { $err = ($Matches[1] -replace '<[^>]+>', ' ').Trim() }
            throw "Import failed on part $n / $PartCount. $err"
        }
        $doneMarker = if ($n -lt $PartCount) { "Part $n finished" } else { 'All parts finished' }
        if ($html -notmatch [regex]::Escape($doneMarker)) {
            throw "Import part $n / $PartCount gave no '$doneMarker' marker (unexpected response)."
        }
        Write-Host ("  part {0,2} / {1} OK ({2:N1}s)" -f $n, $PartCount, $sw.Elapsed.TotalSeconds)
    }
}

function Invoke-Ko2AmigaStagingPush {
    param(
        [string]$StagingBaseUrl = 'https://ratings.kickoff2.com',
        [string]$WinScpSession = 'dagh@ratings.kickoff2.com@ratings.kickoff2.com',
        [switch]$SkipExport,
        [switch]$SkipStagedCheck,
        [switch]$SkipVerify,
        [switch]$DryRun,
        [switch]$Force
    )
    $ErrorActionPreference = 'Stop'
    $RepoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
    $Password = Get-AmigaOpsPasswordFromConfig -RepoRoot $RepoRoot -Role admin
    $WinScpCom = Find-WinScpCom
    if (-not $WinScpCom) { throw 'WinSCP.com not found (install WinSCP or add it to PATH).' }
    $base = $StagingBaseUrl.TrimEnd('/')
    $importOnce = 'ko2amiga-import-one-shot'
    $importUri = "$base/amiga/run_import_ko2amiga.php?once=$importOnce"
    $WorkDir = Join-Path ([System.IO.Path]::GetTempPath()) ("ko2amiga-push-" + (Get-Date -Format 'yyyyMMdd_HHmmss'))
    New-Item -ItemType Directory -Force -Path $WorkDir | Out-Null

    try {
        if (-not $SkipExport) {
            Write-Host '== 1. Export ko2amiga_work -> site\public_html\amiga\_import ==' -ForegroundColor Cyan
            & powershell -NoProfile -ExecutionPolicy Bypass -File (Join-Path $RepoRoot 'scripts\export_ko2amiga_work.ps1')
            if ($LASTEXITCODE -ne 0) { throw "export_ko2amiga_work.ps1 failed (exit $LASTEXITCODE)." }
        } else {
            Write-Host '== 1. Export skipped (-SkipExport): using existing _import parts ==' -ForegroundColor Cyan
        }
        $local = Get-Ko2AmigaLocalImportManifest -RepoRoot $RepoRoot
        Write-Host ("Local export: {0} parts, {1:N1} MB, generated {2}" -f $local.Parts.Count, ($local.Bytes / 1MB), $local.Generated)

        Write-Host '== 2. Staged-unchanged gate (pull before push) ==' -ForegroundColor Cyan
        if ($SkipStagedCheck) {
            Write-Warning 'Skipping staged-unchanged check (-SkipStagedCheck). Anything entered on staged since the last pull will be LOST.'
        } else {
            Test-Ko2AmigaStagedUnchanged -RepoRoot $RepoRoot -StagingBaseUrl $base -Password $Password `
                -WinScpCom $WinScpCom -Session $WinScpSession -WorkDir $WorkDir
        }

        if ($DryRun) {
            $preview = Invoke-Ko2AmigaImportPreview -ImportUri $importUri -OnceKey $importOnce -Password $Password
            Write-Host ("Dry run: server import page currently has manifest generated {0} ({1} parts)." -f $preview.Generated, $preview.Parts)
            Write-Host 'Dry run: nothing uploaded, nothing imported.' -ForegroundColor Yellow
            return
        }

        if (-not $Force) {
            Write-Host ''
            Write-Host "This REPLACES the live staged ko2amiga_db on $base with local ko2amiga_work." -ForegroundColor Yellow
            $answer = Read-Host 'Type PUSH to continue'
            if ($answer -ne 'PUSH') { throw 'Aborted.' }
        }

        Write-Host '== 3. Upload parts + manifest via WinSCP ==' -ForegroundColor Cyan
        $puts = @()
        foreach ($p in $local.Parts) {
            $puts += "put `"$(Join-Path $local.ImportDir $p)`" `"/public_html/amiga/_import/$p`""
        }
        $puts += "put `"$($local.ManifestPath)`" `"/public_html/amiga/_import/ko2amiga_manifest.json`""
        Invoke-WinScpScript -WinScpCom $WinScpCom -Session $WinScpSession -WorkDir $WorkDir -Commands $puts | Out-Null
        Write-Host ("  uploaded {0} files" -f $puts.Count)

        $preview = Invoke-Ko2AmigaImportPreview -ImportUri $importUri -OnceKey $importOnce -Password $Password
        if ($preview.Generated -ne $local.Generated -or $preview.Parts -ne $local.Parts.Count) {
            throw "Server preview shows manifest '$($preview.Generated)' / $($preview.Parts) parts, expected '$($local.Generated)' / $($local.Parts.Count). Upload did not land - not importing."
        }
        Write-Host "  server preview matches local manifest ($($preview.Generated), $($preview.Parts) parts)"

        Write-Host '== 4. Apply import (full replace of staged ko2amiga_db) ==' -ForegroundColor Cyan
        try {
            Invoke-Ko2AmigaImportApply -ImportUri $importUri -OnceKey $importOnce -Password $Password -PartCount $local.Parts.Count
        } catch {
            Write-Warning "Apply failed: $($_.Exception.Message)"
            Write-Warning 'Restarting the import from part 1 once (part 1 recreates the schema, so a rerun is a clean full replace).'
            Invoke-Ko2AmigaImportApply -ImportUri $importUri -OnceKey $importOnce -Password $Password -PartCount $local.Parts.Count
        }
        Write-Host '  import complete' -ForegroundColor Green

        if ($SkipVerify) {
            Write-Warning 'Verification skipped (-SkipVerify). The push baseline was not updated, so the next push will need a full pull first.'
            return
        }
        Write-Host '== 5. Verify: side-pull staged -> ko2amiga_staging_cmp, compare with ko2amiga_work ==' -ForegroundColor Cyan
        $verifyStart = Get-Date
        & powershell -NoProfile -ExecutionPolicy Bypass -File (Join-Path $RepoRoot 'scripts\pull_ko2amiga_from_staging.ps1') -Force -TargetDatabase ko2amiga_staging_cmp
        if ($LASTEXITCODE -ne 0) {
            throw 'Import finished, but the verification pull failed. Re-run: pull_ko2amiga_from_staging.ps1 -Force -TargetDatabase ko2amiga_staging_cmp, then scripts\oneoff\amiga_compare_two_dbs.php ko2amiga_work ko2amiga_staging_cmp. The push baseline was not updated: do a full pull before the next push.'
        }
        $php = Find-LaragonPhpExe
        if (-not $php) { throw 'Laragon php.exe not found for the compare step.' }
        $cmp = & $php (Join-Path $RepoRoot 'scripts\oneoff\amiga_compare_two_dbs.php') ko2amiga_work ko2amiga_staging_cmp 2>&1
        $cmp | Select-Object -Last 4 | ForEach-Object { Write-Host "  $_" }
        if (-not ($cmp -match '^MATCH:')) {
            throw 'Verification FAILED: staged does not match ko2amiga_work after import. See compare output above. The push baseline was not updated.'
        }
        Set-Ko2AmigaPushBaselineFromVerify -RepoRoot $RepoRoot -NotBefore $verifyStart
        Write-Host '  push baseline updated (push-verified)'
        Write-Host ''
        Write-Host 'Push complete and verified: staged ko2amiga_db == local ko2amiga_work.' -ForegroundColor Green
    } finally {
        Remove-Item -LiteralPath $WorkDir -Recurse -Force -ErrorAction SilentlyContinue
    }
}
