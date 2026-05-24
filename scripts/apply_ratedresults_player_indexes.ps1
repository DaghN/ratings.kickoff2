# Apply ratedresults idA / idB indexes (Phase A).
# Usage: powershell -ExecutionPolicy Bypass -File scripts\apply_ratedresults_player_indexes.ps1
# Optional: -Database kooldb -User root -Password ''

param(
    [string]$Database = 'ko2unity_db',
    [string]$User = 'root',
    [string]$Password = ''
)

$ErrorActionPreference = 'Stop'
# Delegates to schema/migrations/001 (SCH-001). Prefer: schema\apply_local.ps1
$ApplyAll = Join-Path $PSScriptRoot '..\schema\apply_local.ps1'
if (-not (Test-Path $ApplyAll)) {
    Write-Error "schema/apply_local.ps1 not found at $ApplyAll"
}
& $ApplyAll -Database $Database -User $User -Password $Password
