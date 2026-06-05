# Local Laragon + ratings site health check (Windows).
# Usage: powershell -ExecutionPolicy Bypass -File scripts\check_local_dev.ps1

$ErrorActionPreference = 'Continue'
$RepoRoot = Split-Path -Parent $PSScriptRoot
$script:Ok = $true

function Report {
    param([bool]$Pass, [string]$Message)
    if (-not $Pass) { $script:Ok = $false }
    $icon = if ($Pass) { '[OK]' } else { '[!!]' }
    Write-Host "$icon $Message"
}

Write-Host "=== Local dev check ===" -ForegroundColor Cyan
Write-Host "Repo: $RepoRoot"
Write-Host ""

# Laragon paths
$LaragonRoot = 'C:\laragon'
$MysqlExe = 'C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe'
$HttpdExe = 'C:\laragon\bin\apache\httpd-2.4.66-260223-Win64-VS18\bin\httpd.exe'

if (-not (Test-Path $LaragonRoot)) {
    Report -Pass $false -Message "Laragon not found at $LaragonRoot"
} else {
    Report -Pass $true -Message "Laragon root: $LaragonRoot"
}

if (-not (Test-Path $MysqlExe)) {
    Report -Pass $false -Message "mysql.exe not found (expected $MysqlExe)"
} else {
    Report -Pass $true -Message "mysql.exe found"
}

# MySQL
if (Test-Path $MysqlExe) {
    $ver = & $MysqlExe -u root -N -e "SELECT VERSION();" 2>&1
    if ($LASTEXITCODE -eq 0) {
        Report -Pass $true -Message "MySQL running - version $ver"
    } else {
        Report -Pass $false -Message "MySQL not reachable (start Laragon / MySQL): $ver"
    }
}

# Database + counts
if (Test-Path $MysqlExe) {
    $games = & $MysqlExe -u root -N -e "SELECT COUNT(*) FROM ko2unity_db.ratedresults;" 2>&1
    if ($LASTEXITCODE -eq 0 -and $games -match '^\d+$') {
        Report -Pass $true -Message "ko2unity_db (dev) ratedresults rows: $games"
    } else {
        Report -Pass $false -Message "ko2unity_db missing or empty - import dev dump (data/README.md): $games"
    }
    $gst = & $MysqlExe -u root -N -e "SHOW TABLES FROM ko2unity_db LIKE 'generalstatstable';" 2>&1
    if ($gst -match 'generalstatstable') {
        Report -Pass $true -Message 'generalstatstable present'
    } else {
        Report -Pass $true -Message 'generalstatstable absent (expected with current dump - see docs/LOCAL_DEV.md)'
    }
}

# Apache port 80
$port80 = Get-NetTCPConnection -LocalPort 80 -State Listen -ErrorAction SilentlyContinue
if ($port80) {
    Report -Pass $true -Message 'Port 80 listening (Apache/web server)'
} else {
    Report -Pass $false -Message 'Port 80 not listening - ratingskickoff.test will fail'
    if (Test-Path $HttpdExe) {
        Write-Host "    Attempting to start Apache..."
        Start-Process -FilePath $HttpdExe -WindowStyle Hidden
        Start-Sleep -Seconds 2
        $port80 = Get-NetTCPConnection -LocalPort 80 -State Listen -ErrorAction SilentlyContinue
        if ($port80) { Report -Pass $true -Message 'Port 80 listening after httpd start' }
    }
}

# Junction
$wwwLink = 'C:\laragon\www\ratingskickoff'
if (Test-Path $wwwLink) {
    Report -Pass $true -Message "Web root junction: $wwwLink"
} else {
    Report -Pass $false -Message "Missing junction $wwwLink - recreate in Laragon or mklink to site\public_html"
}

# PHP config (router + gitignored local files)
$phpRouter = Join-Path $RepoRoot 'site\config\ko2unitydb_config.php'
$phpLocal = Join-Path $RepoRoot 'site\config\ko2unitydb_config.local.php'
$phpWork = Join-Path $RepoRoot 'site\config\ko2unitydb_config_work.local.php'
if (Test-Path $phpRouter) {
    Report -Pass $true -Message 'site/config/ko2unitydb_config.php (router) exists'
} else {
    Report -Pass $false -Message 'Missing site/config/ko2unitydb_config.php — git pull repo'
}
if (Test-Path $phpLocal) {
    Report -Pass $true -Message 'site/config/ko2unitydb_config.local.php exists (dev)'
} else {
    Report -Pass $false -Message 'Run scripts\setup_laragon_work_site.ps1 or copy ko2unitydb_config.local.php.example'
}
if (Test-Path $phpWork) {
    Report -Pass $true -Message 'site/config/ko2unitydb_config_work.local.php exists (work)'
} else {
    Report -Pass $false -Message 'Run scripts\setup_laragon_work_site.ps1 or copy ko2unitydb_config_work.local.php.example'
}

# Hosts: work site
$hostsFile = Join-Path $env:SystemRoot 'System32\drivers\etc\hosts'
if (Test-Path $hostsFile) {
    $hostsText = Get-Content -LiteralPath $hostsFile -Raw
    if ($hostsText -match '(?im)^\s*127\.0\.0\.1\s+work\.ratingskickoff\.test\s*$') {
        Report -Pass $true -Message 'hosts: work.ratingskickoff.test'
    } else {
        Report -Pass $false -Message 'hosts missing work.ratingskickoff.test — run scripts\setup_laragon_work_site.ps1 (Administrator)'
    }
}

# HTTP site
try {
    $r = Invoke-WebRequest -Uri 'http://ratingskickoff.test/leaderboards/peak-rating.php' -UseBasicParsing -TimeoutSec 12
    if ($r.StatusCode -eq 200) {
        Report -Pass $true -Message "HTTP ratingskickoff.test/leaderboards/peak-rating.php status $($r.StatusCode)"
    } else {
        Report -Pass $false -Message "HTTP leaderboards/peak-rating.php status $($r.StatusCode)"
    }
} catch {
    Report -Pass $false -Message "HTTP ratingskickoff.test failed: $($_.Exception.Message)"
}

try {
    $api = Invoke-WebRequest -Uri 'http://ratingskickoff.test/api/server_games_by_month.php?realm=online' -UseBasicParsing -TimeoutSec 12
    if ($api.Content -match '^\s*\{') {
        Report -Pass $true -Message 'Chart API returns JSON (DB + PHP path OK)'
    } else {
        Report -Pass $false -Message 'Chart API did not return JSON'
    }
} catch {
    Report -Pass $false -Message "Chart API failed: $($_.Exception.Message)"
}

# Work DB row count (MySQL)
if (Test-Path $MysqlExe) {
    $workGames = & $MysqlExe -u root -N -e "SELECT COUNT(*) FROM ko2unity_work.ratedresults;" 2>&1
    if ($LASTEXITCODE -eq 0 -and $workGames -match '^\d+$') {
        Report -Pass $true -Message "ko2unity_work ratedresults rows: $workGames"
    } else {
        Report -Pass $false -Message "ko2unity_work missing or unreachable — run setup_local_prod_sandbox.ps1: $workGames"
    }
}

# Work site HTTP (same code, different host -> work DB)
try {
    $wr = Invoke-WebRequest -Uri 'http://work.ratingskickoff.test/leaderboards/peak-rating.php' -UseBasicParsing -TimeoutSec 12
    if ($wr.StatusCode -eq 200) {
        Report -Pass $true -Message "HTTP work.ratingskickoff.test/leaderboards/peak-rating.php status $($wr.StatusCode)"
    } else {
        Report -Pass $false -Message "HTTP work leaderboards/peak-rating.php status $($wr.StatusCode)"
    }
} catch {
    Report -Pass $false -Message "HTTP work.ratingskickoff.test failed (hosts + Laragon?): $($_.Exception.Message)"
}

Write-Host ""
if ($script:Ok) {
    Write-Host 'All critical checks passed.' -ForegroundColor Green
    exit 0
}
Write-Host 'Some checks failed - see docs/LOCAL_DEV.md' -ForegroundColor Yellow
exit 1
