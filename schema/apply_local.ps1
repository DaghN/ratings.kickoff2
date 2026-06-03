# Apply ops/sql/migrations/*.sql in order on local Laragon MySQL (legacy wrapper).
# Prefer: php site/public_html/ops/run_prepare.php migrate-work --target local-work
# Usage: powershell -ExecutionPolicy Bypass -File schema\apply_local.ps1
# Register: docs/coordination/schema-register.md

param(
    [string]$Database = 'ko2unity_db',
    [string]$User = 'root',
    [string]$Password = '',
    [switch]$AllowNonLocal
)

$AllowedLocalDatabases = @('ko2unity_work', 'ko2unity_baseline', 'ko2unity_db')

$ErrorActionPreference = 'Stop'
$MigrationsDir = Join-Path $PSScriptRoot '..\site\public_html\ops\sql\migrations'
$MysqlExe = 'C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe'

if (-not (Test-Path $MysqlExe)) {
    Write-Error "mysql.exe not found at $MysqlExe (start Laragon - docs/LOCAL_DEV.md)."
}

if ($Database -eq 'ko2unity_baseline') {
    Write-Error 'Refusing to apply migrations to ko2unity_baseline (pristine prod snapshot - never mutate).'
}

if ($Database -notin $AllowedLocalDatabases -and -not $AllowNonLocal) {
    Write-Error "Refusing to apply local migrations to '$Database'. Allowed: $($AllowedLocalDatabases -join ', '). Use -AllowNonLocal for one-offs."
}

$files = Get-ChildItem -Path $MigrationsDir -Filter '*.sql' | Sort-Object Name
if ($files.Count -eq 0) {
    Write-Host '[OK] No migrations in ops/sql/migrations/.' -ForegroundColor Green
    exit 0
}

$mysqlArgs = @('-u', $User)
if ($Password -ne '') {
    $mysqlArgs += @("-p$Password")
}

Write-Host "Applying $($files.Count) migration(s) to $Database..." -ForegroundColor Cyan
Write-Host 'Migration list:' -ForegroundColor Cyan
foreach ($f in $files) {
    Write-Host "  - $($f.Name)" -ForegroundColor DarkCyan
}
foreach ($f in $files) {
    Write-Host "  -> $($f.Name)" -ForegroundColor DarkCyan
    # Pin UTC before any ratedresults.Date / TIMESTAMP work (website-data-contract.md).
    $sql = "USE $Database;`nSET time_zone = '+00:00';`n" + (Get-Content -Raw -LiteralPath $f.FullName)
    $sql | & $MysqlExe @mysqlArgs
    if ($LASTEXITCODE -ne 0) {
        Write-Error "Failed: $($f.Name)"
    }
}
Write-Host '[OK] schema migrations applied.' -ForegroundColor Green
