# Archived incremental migrations (014–023)

**Status:** **Upgrade-only archive** — not used by `import_access.apply_schema()` (Jun 2026).

Fresh installs use the **nuclear reset** bundle: `001`–`013`, `019`, `024` in [`import_access.py`](../../import_access.py). Base CREATE files (`002`, `004`, `006`, `009`, `010`, `011`, …) already include the end-state DDL.

These files remain in [`scripts/amiga/sql/`](../) for archaeology and one-off upgrades of **pre-Jun-2026** databases only. **Do not** run them after `import --recreate-schema`.

| File | Superseded by (fresh bundle) |
|------|------------------------------|
| `014_participation_event_points.sql` | `010` (`event_points`) |
| `015_performance_rating.sql` | `009`, `010` |
| `016_participation_avg_goals.sql` | `010` |
| `017`–`018` event finish | `010` (no `overall_position`) |
| `019_tournament_finish_override.sql` | `sql/ground/002_tournament_finish_override.sql` (L3 bundle) |
| `020_unify_league_standings_scope.sql` | `002`, `004` (`league` enum) |
| `021`–`022` medals v2 | `010`, `011` |
| `023_unify_stage_types.sql` | `006` (`round_robin` \| `knockout`) |
| `003_knockout_scope.sql` | **Retired** — regressed `002`; do not apply |

**Proof path:** `python -m scripts.amiga prove` — see [`scripts/amiga/README.md`](../../README.md).
