# Legacy wrapper. Prefer scripts\rebuild_website_derived_data_local.ps1.
# Kept so old notes still lead to the one canonical website-derived-data rebuild.

param(
    [string]$Database = 'ko2unity_db',
    [string]$User = 'root',
    [string]$Password = '',
    [switch]$AllowNonLocal
)

$ErrorActionPreference = 'Stop'
$Canonical = Join-Path $PSScriptRoot 'rebuild_website_derived_data_local.ps1'

Write-Host 'This wrapper is superseded by scripts\rebuild_website_derived_data_local.ps1.' -ForegroundColor Yellow

$args = @('-ExecutionPolicy', 'Bypass', '-File', $Canonical, '-Database', $Database, '-User', $User)
if ($Password -ne '') {
    $args += @('-Password', $Password)
}
if ($AllowNonLocal) {
    $args += '-AllowNonLocal'
}

powershell @args
if ($LASTEXITCODE -ne 0) {
    exit $LASTEXITCODE
}
