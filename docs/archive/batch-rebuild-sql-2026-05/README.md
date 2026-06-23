# Batch rebuild SQL — May 2026 (archived)

**Retired Jun 2026** — moved from `scripts/ladder/sql/archive/batch-2026-05/` during [obsolete dev scripts retirement](../obsolete-dev-scripts-retirement-policy.md) slice 1.

**Not used for cutover or `kooldb1` ops simul.**

| Use | Command |
|-----|---------|
| **Happy path** | `php site/public_html/ops/run_ops_sim.php run` — [`ops-simul-runbook.md`](../coordination/ops-simul-runbook.md) |
| **Dev repair (`ko2unity_db`)** | **Retired** — was `scripts/rebuild_website_derived_data_local.ps1` |
| **Python ladder `run` tail** | **Retiring** — `period_aggregates.py`, `period_activity.py`, `milestones.py` |

**Deleted Jun 2026 (never executed):** `league_period_awards_rebuild.sql`, `player_league_slice_totals_rebuild.sql`, `player_play_streaks_rebuild.sql`, `server_daily_activity_rebuild_raw.sql` — league/streaks use PHP ops instead.

**Regenerate milestone splices (historical only):** `scripts/oneoff/gen_milestone_*.py` — output path updated to this folder.
