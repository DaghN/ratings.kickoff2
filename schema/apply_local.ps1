# Apply all schema/migrations/*.sql in order on local Laragon MySQL.
# Usage: powershell -ExecutionPolicy Bypass -File schema\apply_local.ps1
# Register: docs/coordination/schema-register.md

param(
    [string]$Database = 'ko2unity_db',
    [string]$User = 'root',
    [string]$Password = '',
    [switch]$AllowNonLocal
)

$ErrorActionPreference = 'Stop'
$MigrationsDir = Join-Path $PSScriptRoot 'migrations'
$MysqlExe = 'C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe'

if (-not (Test-Path $MysqlExe)) {
    Write-Error "mysql.exe not found at $MysqlExe (start Laragon - docs/LOCAL_DEV.md)."
}

if ($Database -ne 'ko2unity_db' -and -not $AllowNonLocal) {
    Write-Error "Refusing to apply local migrations to '$Database'. Use -AllowNonLocal only for an explicitly reviewed one-off."
}

$files = Get-ChildItem -Path $MigrationsDir -Filter '*.sql' | Sort-Object Name
if ($files.Count -eq 0) {
    Write-Host '[OK] No migrations in schema/migrations/.' -ForegroundColor Green
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
    $sql = "USE $Database;`n" + (Get-Content -Raw -LiteralPath $f.FullName)
    $sql | & $MysqlExe @mysqlArgs
    if ($LASTEXITCODE -ne 0) {
        Write-Error "Failed: $($f.Name)"
    }
}
Write-Host '[OK] schema migrations applied.' -ForegroundColor Green
