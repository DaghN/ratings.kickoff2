# RETIRED (Jun 2026) — obsolete dev scripts retirement, slice 1.
# Batch website-derived fill on ko2unity_db is not a supported path.
#
# Use instead (online work / kooldb1 sign-off):
#   php site/public_html/ops/run_prepare.php zero-derived --target local-work
#   php site/public_html/ops/run_ops_sim.php run --target local-work
#   php site/public_html/ops/run_verify_ops_sim.php --target local-work
#
# Policy: docs/obsolete-dev-scripts-retirement-policy.md
# Archived SQL: docs/archive/batch-rebuild-sql-2026-05/

param()

$ErrorActionPreference = 'Stop'
Write-Host @'

[RETIRED] scripts/rebuild_website_derived_data_local.ps1

This batch SQL chain was dev-era repair only. It is not holy ops and must not
fill work DB or staging sign-off databases.

Happy path: zero-derived -> run_ops_sim.php -> run_verify_ops_sim.php
See docs/obsolete-dev-scripts-retirement-policy.md

'@ -ForegroundColor Yellow
exit 1
