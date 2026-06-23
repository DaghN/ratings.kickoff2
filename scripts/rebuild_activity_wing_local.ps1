# RETIRED (Jun 2026) — obsolete dev scripts retirement, slice 1.
# Activity wing batch fill on ko2unity_db depended on the retired batch rebuild chain.
#
# Use instead (work / kooldb1):
#   php site/public_html/ops/run_ops_sim.php run --target local-work
#   php site/public_html/ops/run_verify_ops_sim.php --target local-work
#
# Policy: docs/obsolete-dev-scripts-retirement-policy.md

param()

$ErrorActionPreference = 'Stop'
Write-Host @'

[RETIRED] scripts/rebuild_activity_wing_local.ps1

This one-shot dev fill assumed batch period aggregates on ko2unity_db.
Use holy ops simul on ko2unity_work / kooldb1 instead.

See docs/obsolete-dev-scripts-retirement-policy.md

'@ -ForegroundColor Yellow
exit 1
