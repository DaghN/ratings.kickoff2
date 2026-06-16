# URL routes

**Status:** Jun 2026 — legacy `server*`, `ranked*`, `individual*` page names replaced with semantic paths.

**Authority:** Runtime map in [`site/public_html/includes/k2_routes.php`](../site/public_html/includes/k2_routes.php). Hub IA labels in [`hub-ia-agreement.md`](hub-ia-agreement.md).

---

## Rules

1. **Canonical paths** — use `k2_route('route-key', $params)` or constants in `K2_ROUTES`; do not hardcode legacy filenames.
2. **Root-absolute URLs** — `k2_route()` returns paths like `/milestones/recent.php` (leading `/`). Required so hub and cross-links work from `leaderboards/` and `player/` subfolders.
3. **Shared assets** — `/js/…`, `/stylesheets/…`, `/fonts/…` in `k2_head.php` and page templates (not bare `js/…` on subfolder pages).
4. **Amiga** — player universe under `amiga/player/`; route keys in `includes/k2_amiga_routes.php` (`k2_amiga_route()`). Legacy flat URLs 302 to canonical paths.
5. **Foldered sub-hubs, not `?view=`** — see [§ Sub-hub navigation](#sub-hub-navigation-foldered-not-view) below.

---

## Sub-hub navigation (foldered, not `?view=`)

**Product rule (Jun 2026):** When a page has internal tabs or modes (Recent · Catalog · Highlights · All games, etc.), prefer **one PHP file per mode under a folder**, not a single root file with `?view=`.

| Kind | URL shape | Examples |
|------|-----------|----------|
| **Where am I?** (tab / sub-hub mode) | Folder path | `/games/recent.php`, `/milestones/catalog.php`, `/player/opponents/h2h.php` |
| **What am I filtering?** (state on that page) | Query params | `?tier=`, `?board=`, `?sort=`, `?opponent=`, `?id=` |

**Why:** Clearer mental model for Dagh and agents; matches **leaderboards/** and **player/opponents/**; bookmarks and cross-links read as places, not switch statements.

**Implementation habit:**

- Thin entry files (`games/recent.php`, …) + shared shell include (`*_hub_shell_start.inc.php`, sub-nav include).
- Register each mode in `K2_ROUTES` (`games-recent`, `milestones-catalog`, …); hub default key keeps short name (`games` → recent, `milestones` → recent).
- Sub-nav hrefs via `k2_route()` only — never bare relative `recent.php` on subfolder pages.
- **Do not** add new `?view=` for navigation. Legacy `?view=` on old URLs is retired, not a pattern to copy.

**Pre-public refactors:** Update on-site links via `k2_route()` / href helpers; **no** bookmark redirect layer required until the site is public with stable external URLs.

**Shipped examples:**

| Area | Folder | Modes |
|------|--------|-------|
| Games hub | `games/` | `recent.php`, `highlights.php`, `all.php` |
| Milestones hub | `milestones/` | `recent.php`, `catalog.php` |
| Leaderboards | `leaderboards/` | one wing per file |
| Player opponents | `player/opponents/` | `h2h.php`, `wdl.php`, … |

**Detail pages stay at root or a stable path** when they are not sub-nav peers — e.g. `milestone.php?key=`, `game.php?id=` (key/id are entity lookup, not hub mode).

---

## Hub (site root)

Hub tab order: Status · Activity · Leaderboards · Milestones · **Games** · Hall of Fame · Play & Setup.

| Route key | Path |
|-----------|------|
| `status` | `/status.php` |
| `activity` | `/activity.php` |
| `lb-rating` | `/leaderboards/rating.php` (Leaderboards tab default) |
| `milestones` | `/milestones/recent.php` (hub default) |
| `milestones-recent` | `/milestones/recent.php` |
| `milestones-catalog` | `/milestones/catalog.php` |
| `games` | `/games/recent.php` (hub default) |
| `games-recent` | `/games/recent.php` |
| `games-highlights` | `/games/highlights.php` |
| `games-all` | `/games/all.php` |
| `hall-of-fame` | `/hall-of-fame.php` |
| `join` | `/join.php` |
| `milestone` | `/milestone.php` |
| `game` | `/game.php` |
| `league` | `/league.php` |

`index.php` redirects to `/status.php`.

**Standalone (no route key yet):** `/boxart.php` — box-art story page, linked directly (`href="/boxart.php"`) from the Status heritage box. Add to `K2_ROUTES` if it gains more inbound links.

---

## Leaderboards (`leaderboards/`)

| Route key | Path | Wing label |
|-----------|------|------------|
| `lb-rating` | `/leaderboards/rating.php` | Rating (hub default) |
| `lb-goals` | `/leaderboards/goals.php` | Goals |
| `lb-double-digits` | `/leaderboards/double-digits.php` | DDs & CSs |
| `lb-streaks` | `/leaderboards/streaks.php` | Streaks |
| `lb-victims` | `/leaderboards/victims.php` | Victims & Culprits |
| `lb-league-honours` | `/leaderboards/league-honours.php` | League honours |
| `lb-milestones` | `/leaderboards/milestones.php` | Milestones (meta LB) |
| `lb-activity` | `/leaderboards/activity/participation.php` | Activity (default Participation) |
| `lb-activity-peaks` | `/leaderboards/activity/peaks.php` | Activity — Peaks (legacy key) |
| `lb-activity-participation` | `/leaderboards/activity/participation.php` | Activity — Participation |
| `lb-activity-in-a-row` | `/leaderboards/activity/in-a-row.php` | Activity — In a row |
| `lb-peak-rating` | `/leaderboards/peak-rating.php` | Peak rating |

---

## Player (`player/`)

| Route key | Path |
|-----------|------|
| `player-profile` | `/player/profile.php` |
| `player-games` | `/player/games.php` |
| `player-opponents` | `/player/opponents/h2h.php` (default Opponents tab — Head-to-head) |
| `player-opponents-h2h` | `/player/opponents/h2h.php` (optional `opponent={id}`) |
| `player-opponents-wdl` | `/player/opponents/wdl.php` |
| `player-opponents-goals` | `/player/opponents/goals.php` |
| `player-opponents-dds` | `/player/opponents/dds.php` |
| `player-milestones` | `/player/milestones.php` |

Query `?id=` required on all player tabs.

---

## Amiga player (`amiga/player/`)

Registry: [`site/public_html/includes/k2_amiga_routes.php`](../site/public_html/includes/k2_amiga_routes.php).

| Route key | Path |
|-----------|------|
| `amiga-game` | `/amiga/game.php` |
| `amiga-player-profile` | `/amiga/player/profile.php` |
| `amiga-player-games` | `/amiga/player/games.php` |
| `amiga-player-tournaments` | `/amiga/player/tournaments.php` |

Query `?id=` required on all player tabs.

**Legacy redirects (302, query preserved):** `/amiga/profile.php` → profile; `/amiga/games.php` → player games; `/amiga/player-tournaments.php` → tournaments. `/amiga/games.php` is reserved for a future realm-wide match log.

**Not under `player/`:** `/amiga/h2h.php` (pair page), `/amiga/tournament.php`, hub pages under `/amiga/`.

---

## Retired (Jun 2026)

| Old | Replacement |
|-----|-------------|
| `server1.php` | `activity.php` |
| `server2.php` | `hall-of-fame.php` |
| `server3.php` | `games/recent.php` |
| `games.php`, `games.php?view=highlights` | `games/recent.php`, `games/highlights.php` |
| `milestones.php`, `milestones.php?view=catalog` | `milestones/recent.php`, `milestones/catalog.php` |
| `ranked1`–`ranked10` | `leaderboards/*` (see table) |
| `individual1`, `individual2a/b/c`, `individual3`, `individual_milestones` | `player/*` |
| `join_alt.php`, `server1-charts-lab.php`, `status-realm-lab.php` | removed |

**Kept at root:** `individual1-profile-lab1.php` … `lab4.php` (experiments only).

---

## Milestone href helpers

`k2_milestone_detail_href()`, `k2_milestones_recent_href()`, and `k2_milestones_catalog_href()` in `player_milestones_helpers.php` delegate to `k2_route()` — use these instead of string-built `milestone.php` links.
