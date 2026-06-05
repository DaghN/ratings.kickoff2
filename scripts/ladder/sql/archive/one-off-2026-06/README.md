# One-off SQL (Jun 2026)

| File | What | Superseded by |
|------|------|----------------|
| `player_milestones_fix_day_close.sql` | Surgical `perfect_day` / `nightmare_day` rows on frozen **`kooldb`** (113 INSERTs, midnight `achieved_at`) | **`FinalizeUtcDay`** — `site/public_html/ops/includes/day_close_milestones.php` + ops simul |

**Do not run on work DB or prod cutover.** Regenerate only for historical audit: `python scripts/oneoff/apply_day_milestone_achieved_at_fix.py`.
