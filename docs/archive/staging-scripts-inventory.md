# `staging-scripts/` inventory (retired)

**Status:** **Done — Jun 2026.** `site/public_html/staging-scripts/` was removed from the repo. Steady-state ladder ops live in **`site/public_html/ops/`** only.

**Replacements (summary):**

| Former runner | Use instead |
|---------------|-------------|
| `load_milestone_definitions.php` | `php ops/run_prepare.php seed-catalog --target …` |
| `run_league_awards_rebuild.php` | `php ops/run_finalize_league.php rebuild-all` |
| `run_player_milestones_rebuild.php` | `run_ops_sim.php` / post-game replay on work DB |
| `run_player_play_streaks_rebuild.php` | Post-game P7; local repair: `scripts/rebuild_player_play_streaks.php` |
| Surgical unlock / catalog patch one-offs | `scripts/oneoff/`; catalog via `apply_milestone_catalog_copy_patch.py` + `seed-catalog` |

**History:** See git history under `site/public_html/staging-scripts/` before this deletion.

**Active ops docs:** [`site/public_html/ops/README.md`](../../site/public_html/ops/README.md) · [`ladder-ops-platform.md`](../ladder-ops-platform.md) §6.
