# Batch rebuild SQL — May 2026 (archived)

**Not used for cutover or `kooldb1` ops simul.**

| Use | Command |
|-----|---------|
| **Happy path** | `php site/public_html/ops/run_ops_sim.php run` — [`docs/coordination/ops-simul-runbook.md`](../../../../docs/coordination/ops-simul-runbook.md) |
| **Dev repair (`ko2unity_db`)** | `scripts/rebuild_website_derived_data_local.ps1` (reads this folder) |
| **Python ladder `run` tail** | `period_aggregates.py`, `period_activity.py`, `milestones.py` (oracle / repair only) |

**Deleted Jun 2026 (never executed):** `league_period_awards_rebuild.sql`, `player_league_slice_totals_rebuild.sql`, `player_play_streaks_rebuild.sql`, `server_daily_activity_rebuild_raw.sql` — league/streaks use PHP ops instead.

**Regenerate milestone splices:** `scripts/oneoff/gen_milestone_*.py` → writes here.
