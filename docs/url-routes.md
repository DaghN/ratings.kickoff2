# URL routes

**Status:** Jun 2026 — legacy `server*`, `ranked*`, `individual*` page names replaced with semantic paths.

**Authority:** Runtime map in [`site/public_html/includes/k2_routes.php`](../site/public_html/includes/k2_routes.php). Hub IA labels in [`hub-ia-agreement.md`](hub-ia-agreement.md).

---

## Rules

1. **Canonical paths** — use `k2_route('route-key', $params)` or constants in `K2_ROUTES`; do not hardcode legacy filenames.
2. **Root-absolute URLs** — `k2_route()` returns paths like `/milestones.php` (leading `/`). Required so hub and cross-links work from `leaderboards/` and `player/` subfolders.
3. **Shared assets** — `/js/…`, `/stylesheets/…`, `/fonts/…` in `k2_head.php` and page templates (not bare `js/…` on subfolder pages).
4. **Amiga** — player universe under `amiga/player/`; route keys in `includes/k2_amiga_routes.php` (`k2_amiga_route()`). Legacy flat URLs 302 to canonical paths.

---

## Hub (site root)

| Route key | Path |
|-----------|------|
| `status` | `/status.php` |
| `activity` | `/activity.php` |
| `hall-of-fame` | `/hall-of-fame.php` |
| `games` | `/games.php` |
| `join` | `/join.php` |
| `milestones` | `/milestones.php` |
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
| `lb-activity-peaks` | `/leaderboards/activity-peaks.php` | Activity peaks |
| `lb-peak-rating` | `/leaderboards/peak-rating.php` | Peak rating |

---

## Player (`player/`)

| Route key | Path |
|-----------|------|
| `player-profile` | `/player/profile.php` |
| `player-games` | `/player/games.php` |
| `player-wdl` | `/player/wdl.php` |
| `player-goals` | `/player/goals.php` |
| `player-double-digits` | `/player/double-digits.php` |
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
| `server3.php` | `games.php` |
| `ranked1`–`ranked10` | `leaderboards/*` (see table) |
| `individual1`, `individual2a/b/c`, `individual3`, `individual_milestones` | `player/*` |
| `join_alt.php`, `server1-charts-lab.php`, `status-realm-lab.php` | removed |

**Kept at root:** `individual1-profile-lab1.php` … `lab4.php` (experiments only).

---

## Milestone href helpers

`k2_milestone_detail_href()`, `k2_milestones_recent_href()`, and `k2_milestones_catalog_href()` in `player_milestones_helpers.php` delegate to `k2_route()` — use these instead of string-built `milestone.php` links.
