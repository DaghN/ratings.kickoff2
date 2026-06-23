# One-off batch SQL — Jun 2026 (archived)

Moved from `scripts/ladder/sql/archive/one-off-2026-06/` during [obsolete dev scripts retirement](../obsolete-dev-scripts-retirement-policy.md) slice 1.

| File | What | Superseded by |
|------|------|----------------|
| `player_milestones_fix_day_close.sql` | Surgical `perfect_day` / `nightmare_day` on frozen **`kooldb`** | **`FinalizeUtcDay`** — `day_close_milestones.php` |
| `player_activity_participation_rebuild.sql` | Dev activity wing batch | Ops simul + post-game writers |

**Do not run on work DB or prod cutover.**
