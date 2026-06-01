# One-off scripts register

**Policy:** Prefer [replay](replay-register.md) for anything derived from ordered game history. One-offs are for imports, fixes, or experiments — mark **superseded by replay** when migrated.

**Template:** `scripts/oneoff/_template.py` · **README:** `scripts/oneoff/README.md`

| ID | Script | Purpose | Local | Staging | Prod | Superseded by replay? | Notes |
|----|--------|---------|-------|---------|------|----------------------|-------|
| OO-001 | `scripts/throwaway_drop_playertable_kungfu_columns.php` | Drop unused `KungFu*` columns | Done | Done | Unknown | N/A | Historical; throwaway pattern — do not re-run blindly |
| OO-002 | *(template)* | — | — | — | — | — | Copy template; register here before asking Steve |
| OO-003 | `scripts/oneoff/apply_day_milestone_achieved_at_fix.py` | DELETE + re-INSERT `perfect_day` / `nightmare_day` with day-close `achieved_at` | Done | **Done** Jun 2026 | Pending | Staging SQL + Dagh UI smoke **done** Jun 2026; prod cutover still pending |

### Before asking Steve

1. Dry-run locally (`--dry-run`).
2. Document `DATABASE()`, row counts before/after, last 20 log lines.
3. Add run row to this table when executed.
