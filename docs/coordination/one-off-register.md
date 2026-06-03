# One-off scripts register

**Policy:** Prefer [replay](replay-register.md) for anything derived from ordered game history. One-offs are for imports, fixes, or experiments — mark **superseded by replay** when migrated.

**Template:** `scripts/oneoff/_template.py` · **README:** `scripts/oneoff/README.md`

| ID | Script | Purpose | Local | Staging | Prod | Superseded by replay? | Notes |
|----|--------|---------|-------|---------|------|----------------------|-------|
| OO-001 | `scripts/throwaway_drop_playertable_kungfu_columns.php` | Drop unused `KungFu*` columns | Done | Done | Unknown | N/A | Historical; throwaway pattern — do not re-run blindly |
| OO-002 | *(template)* | — | — | — | — | — | Copy template; register here before asking Steve |
| OO-003 | `scripts/oneoff/apply_day_milestone_achieved_at_fix.py` | DELETE + re-INSERT `perfect_day` / `nightmare_day` with day-close `achieved_at` | Done | **Done** Jun 2026 | Pending | Staging SQL + Dagh UI smoke **done** Jun 2026; prod cutover still pending |
| OO-004 | `staging-scripts/run_player_milestones_diversity_merchant_fix.php` | REP-008b diversity_merchant surgical SQL | N/A | **Done** May 2026 | N/A | **Archived** — do not re-run |
| OO-005 | `staging-scripts/run_milestone_play_streak_100_unlock.php` | play_streak_100 unlock splice | N/A | **Done** May 2026 | N/A | **Archived** — optional; 0 rows on May 2026 import |
| OO-006 | `staging-scripts/run_milestone_year_in_heaven_unlock.php` | year_in_heaven unlock splice | N/A | Handoff | N/A | **Archived** — see `milestones-year-in-heaven-handoff.md` |
| OO-007 | `staging-scripts/patch_milestone_catalog_copy.php` | Batch catalog copy patches (no TRUNCATE) | N/A | As needed | N/A | **Archived** — prefer `scripts/oneoff/apply_milestone_catalog_copy_patch.py` locally |

### Before asking Steve

1. Dry-run locally (`--dry-run`).
2. Document `DATABASE()`, row counts before/after, last 20 log lines.
3. Add run row to this table when executed.
