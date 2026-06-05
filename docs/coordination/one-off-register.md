# One-off scripts register

**Policy:** Prefer **ops simul** ([`ops-simul-runbook.md`](ops-simul-runbook.md)) for derived history. Batch REP: [`../archive/replay-register-2026-05.md`](../archive/replay-register-2026-05.md) (historical). One-offs are for imports, fixes, or experiments — mark **superseded by replay** when migrated.

**Template:** `scripts/oneoff/_template.py` · **README:** `scripts/oneoff/README.md`

| ID | Script | Purpose | Local | Staging | Prod | Superseded by replay? | Notes |
|----|--------|---------|-------|---------|------|----------------------|-------|
| OO-001 | `scripts/throwaway_drop_playertable_kungfu_columns.php` | Drop unused `KungFu*` columns | Done | Done | Unknown | N/A | Historical; throwaway pattern — do not re-run blindly |
| OO-002 | *(template)* | — | — | — | — | — | Copy template; register here before asking Steve |
| OO-003 | `scripts/oneoff/apply_day_milestone_achieved_at_fix.py` | DELETE + re-INSERT `perfect_day` / `nightmare_day` with day-close `achieved_at` | Done | **Done** Jun 2026 | Pending | Staging SQL + Dagh UI smoke **done** Jun 2026; prod cutover still pending |
| OO-004 | *(removed)* `staging-scripts/…diversity_merchant_fix` | REP-008b diversity_merchant surgical SQL | N/A | **Done** May 2026 | N/A | **Archived** — script deleted Jun 2026 |
| OO-005 | *(removed)* `staging-scripts/…play_streak_100_unlock` | play_streak_100 unlock splice | N/A | **Done** May 2026 | N/A | **Archived** — script deleted Jun 2026 |
| OO-006 | *(removed)* `staging-scripts/…year_in_heaven_unlock` | year_in_heaven unlock splice | N/A | Handoff | N/A | **Archived** — see `milestones-year-in-heaven-handoff.md` |
| OO-007 | *(removed)* `staging-scripts/patch_milestone_catalog_copy` | Batch catalog copy patches | N/A | As needed | N/A | **Archived** — use `scripts/oneoff/apply_milestone_catalog_copy_patch.py` + `ops/run_prepare.php seed-catalog` |

### Before asking Steve

1. Dry-run locally (`--dry-run`).
2. Document `DATABASE()`, row counts before/after, last 20 log lines.
3. Add run row to this table when executed.
