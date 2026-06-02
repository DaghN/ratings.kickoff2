# Report dev + prod sandbox databases (no changes).
# Usage: powershell -ExecutionPolicy Bypass -File scripts\verify_local_databases.ps1

$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'lib\LaragonMysql.ps1')

$MysqlExe = Find-LaragonMysqlExe
if (-not $MysqlExe) { Write-Error 'Laragon mysql.exe not found.' }

$ConfigPhp = Join-Path (Get-RepoRoot) 'site\config\ko2unitydb_config.php'
$phpDb = '(config missing)'
if (Test-Path $ConfigPhp) {
    $text = Get-Content -Raw -LiteralPath $ConfigPhp
    if ($text -match '\$database\s*=\s*''([^'']+)''') { $phpDb = $Matches[1] }
}

Write-Host '=== Local MySQL databases (ko2*) ===' -ForegroundColor Cyan
Write-Host "PHP ko2unitydb_config.php -> $phpDb"
Write-Host ''

foreach ($row in @(
        @{ Name = 'ko2unity_db'; Role = 'DEV - browser / daily PHP' },
        @{ Name = 'ko2unity_baseline'; Role = 'SANDBOX - pristine prod (do not mutate)' },
        @{ Name = 'ko2unity_work'; Role = 'SANDBOX - sim / replay / expand' }
    )) {
    $db = $row.Name
    $exists = & $MysqlExe -u root -N -e "SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='$db';"
    if ($exists -ne '1') {
        Write-Host "[--] $db - not present - $($row.Role)" -ForegroundColor DarkYellow
        continue
    }
    $games = & $MysqlExe -u root -N -e "SELECT COUNT(*) FROM ``$db``.ratedresults;"
    $tables = & $MysqlExe -u root -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$db';"
    Write-Host "[OK] $db - tables: $tables, ratedresults: $games - $($row.Role)" -ForegroundColor Green
}
