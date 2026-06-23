# RETIRED (Jun 2026) — obsolete dev scripts retirement, slice 1.
# Legacy wrapper for rebuild_website_derived_data_local.ps1 (also retired).
#
# Policy: docs/obsolete-dev-scripts-retirement-policy.md

param()

$ErrorActionPreference = 'Stop'
Write-Host @'

[RETIRED] scripts/rebuild_player_period_games_local.ps1

Batch period-game rebuild was part of the retired dev repair chain.
Use holy ops simul on work DB instead.

See docs/obsolete-dev-scripts-retirement-policy.md

'@ -ForegroundColor Yellow
exit 1
