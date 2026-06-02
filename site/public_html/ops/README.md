# `ops/` — server operations (not the public site)

**Audience:** Dagh, Steve, Cursor agents.

**Deploy:** Included when you WinSCP-sync `site/public_html/` → staging `public_html/`.

**Authority:** [`docs/ladder-ops-platform.md`](../../../docs/ladder-ops-platform.md)

## Purpose

Home for **future** runnable PHP commands, **SQL mirrors**, and ladder modules:

- Post-game derived truth (`ProcessCompletedGame` — planned)
- Periodic jobs (league finalize, rating fade, …)
- Schema expand, work-DB reset, chronological sim, parity checks

**Not** for browser traffic — `.htaccess` denies web access.

**Today:** folder scaffold only (`modules/`, `sql/`). No `dispatch.php` yet — see platform doc implementation order.

## Layout (target)

| Path | Role |
|------|------|
| `dispatch.php` | Thin entry — `CMD=…` (Steve + CLI) → modules (**planned**) |
| `modules/` | Derived-truth logic (post-game, periodic, orchestration) |
| `sql/migrations/` | Mirror of `schema/migrations/` for staging sync |
| `sql/rebuild/` | One-shot REP SQL (optional mirror of `scripts/ladder/sql/`) |

## Legacy (until migrated)

Older runners still live in `../staging-scripts/`. New work goes under `ops/`. Do not delete legacy paths without a migration slice.
