# RETIRED (Jun 2026) — obsolete dev scripts retirement, slice 2.
# Was: full ladder replay on frozen ko2unity_db via python -m scripts.ladder run
#
# Policy: docs/obsolete-dev-scripts-retirement-policy.md

param()

$ErrorActionPreference = 'Stop'
Write-Host @'

[RETIRED] scripts/run_local_replay.ps1

Dev Elo replay on ko2unity_db is not a supported path. Frozen dev should not be
replay-mutated. Fill derived truth on work DB via holy ops simul.

  php site/public_html/ops/run_ops_sim.php run --target local-work

See docs/obsolete-dev-scripts-retirement-policy.md

'@ -ForegroundColor Yellow
exit 1
