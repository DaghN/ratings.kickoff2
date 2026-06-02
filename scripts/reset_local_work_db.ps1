# DEPRECATED — use scripts\refresh_local_work_db.ps1 (prepare platform v2).
# Forwards to refresh_local_work_db.ps1 for backward compatibility.

param([switch]$DryRun)

Write-Host '[deprecated] reset_local_work_db.ps1 → refresh_local_work_db.ps1 (refresh work, not zero derived)' -ForegroundColor Yellow
& (Join-Path $PSScriptRoot 'refresh_local_work_db.ps1') @PSBoundParameters
exit $LASTEXITCODE
