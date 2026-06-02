# Rewrite Steve dump CREATE DATABASE / USE from ko2unity_db -> ko2unity_baseline.
# Dot-source from extract_prod_dump.ps1 / sanitize_prod_dump.ps1

function Get-SqlBacktick {
    [char]0x60
}

function Test-ProdDumpSanitized {
    param(
        [Parameter(Mandatory)]
        [string]$Path,
        [string]$BaselineDb = 'ko2unity_baseline',
        [string]$UnsafeDb = 'ko2unity_db'
    )
    if (-not (Test-Path $Path)) { return $false }

    $bt = Get-SqlBacktick
    $head = Get-Content -LiteralPath $Path -TotalCount 80 -ErrorAction SilentlyContinue
    if (-not $head) { return $false }

    # Ignore comment lines (our header mentions "CREATE DATABASE ko2unity_db" in prose).
    $sqlLines = $head | Where-Object { $_ -notmatch '^\s*--' }

    $baselineCreate = $sqlLines | Where-Object { $_ -match "^\s*CREATE DATABASE.*${bt}${BaselineDb}${bt}" }
    $baselineUse = $sqlLines | Where-Object { $_ -match "^\s*USE\s+${bt}${BaselineDb}${bt}" }
    $unsafeCreate = $sqlLines | Where-Object { $_ -match "^\s*CREATE DATABASE.*${bt}${UnsafeDb}${bt}" }

    return (($null -ne $baselineCreate) -or ($null -ne $baselineUse)) -and ($null -eq $unsafeCreate)
}

function Convert-ProdDumpLine {
    param(
        [string]$Line,
        [string]$TargetDb = 'ko2unity_baseline',
        [string]$SourceDb = 'ko2unity_db'
    )

    $bt = Get-SqlBacktick
    $quotedSource = "$bt$SourceDb$bt"
    $quotedTarget = "$bt$TargetDb$bt"

    if ($Line.StartsWith('CREATE DATABASE')) {
        return $Line.Replace($quotedSource, $quotedTarget)
    }
    if ($Line -match '^\s*USE\s+') {
        return "USE $quotedTarget;"
    }
    return $Line
}

function Write-SanitizedProdDump {
    param(
        [Parameter(Mandatory)]
        [string]$SourcePath,
        [Parameter(Mandatory)]
        [string]$DestPath,
        [string]$TargetDb = 'ko2unity_baseline',
        [string]$SourceDb = 'ko2unity_db'
    )

    if (-not (Test-Path $SourcePath)) {
        Write-Error "Source dump not found: $SourcePath"
    }

    $destDir = Split-Path -Parent $DestPath
    if ($destDir -and -not (Test-Path $destDir)) {
        New-Item -ItemType Directory -Force -Path $destDir | Out-Null
    }

    $tmp = "$DestPath.part"
    if (Test-Path $tmp) { Remove-Item -Force $tmp }

    Write-Host "Sanitizing dump -> $TargetDb (several minutes)..." -ForegroundColor Cyan

    $reader = [System.IO.StreamReader]::new($SourcePath)
    $writer = [System.IO.StreamWriter]::new($tmp, $false, [System.Text.UTF8Encoding]::new($false))
    $writer.WriteLine("-- KOOL local prod archive (sanitized $(Get-Date -Format 'yyyy-MM-dd'))")
    $writer.WriteLine("-- Targets database $TargetDb only. Safe to import with mysql/HeidiSQL.")
    $writer.WriteLine("-- Unsanitized Steve zip still creates $SourceDb if imported raw.")
    $writer.WriteLine('')

    $lineNum = 0
    while (-not $reader.EndOfStream) {
        $line = $reader.ReadLine()
        $lineNum++
        $line = Convert-ProdDumpLine -Line $line -TargetDb $TargetDb -SourceDb $SourceDb
        $writer.WriteLine($line)
        if ($lineNum % 500000 -eq 0) {
            Write-Host "  ... $lineNum lines"
        }
    }
    $reader.Close()
    $writer.Close()

    if (Test-Path $DestPath) { Remove-Item -Force $DestPath }
    Move-Item -LiteralPath $tmp -Destination $DestPath

    if (-not (Test-ProdDumpSanitized -Path $DestPath)) {
        $peek = Get-Content -LiteralPath $DestPath -TotalCount 30 | Out-String
        Write-Error "Sanitize failed header check on $DestPath`nFirst lines:`n$peek"
    }

    Write-Host '[OK] Sanitized dump ready.' -ForegroundColor Green
}
