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
| Player milestones | `player/milestones/` | `garden.php`, `chronology.php` |

**Detail pages stay at root or a stable path** when they are not sub-nav peers — e.g. `milestone.php?key=`, `game.php?id=` (key/id are entity lookup, not hub mode). Inbound game links use `k2_game_page_url($id)` → `/game.php?id=` + `#k2-game` so the viewport lands on the game table (hub chrome stays above the fold).

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
| `player-milestones` | `/player/milestones/garden.php` (default Milestones tab — Garden) |
| `player-milestones-garden` | `/player/milestones/garden.php` |
| `player-milestones-chronology` | `/player/milestones/chronology.php` |

Legacy `/player/milestones.php?id=` → **302** to Garden (query preserved).

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
| `amiga-player-opponents-h2h` | `/amiga/player/opponents/h2h.php` (default Opponents tab) |
| `amiga-player-opponents-wdl` | `/amiga/player/opponents/wdl.php` |
| `amiga-player-opponents-goals` | `/amiga/player/opponents/goals.php` |
| `amiga-player-opponents-dds` | `/amiga/player/opponents/dds.php` |
| `amiga-world-cups` | `/amiga/world-cups/chronology.php` (Chronology default; `/amiga/world-cups/` 302) |
| `amiga-world-cups-chronology` | `/amiga/world-cups/chronology.php` (`chronology/index.php` 302 legacy) |
| `amiga-world-cups-stats` | `/amiga/world-cups/stats/participation.php` (Participation default; `stats.php` + `stats/index.php` 302) |
| `amiga-world-cups-stats-goals` | `/amiga/world-cups/stats/goals.php` |
| `amiga-world-cups-stats-dds` | `/amiga/world-cups/stats/dds.php` |
| `amiga-world-cups-stats-participation` | `/amiga/world-cups/stats/participation.php` |
| `amiga-world-cups-stats-geography` | `/amiga/world-cups/stats/geography.php` |
| `amiga-world-cups-stats-podium` | `/amiga/world-cups/stats/podium.php` → **302** Chronology (retired Jun 2026) |
| `amiga-world-cups-players` | `/amiga/world-cups/players/honours.php` (Player stats default) |
| `amiga-world-cups-players-honours` | `/amiga/world-cups/players/honours.php` |
| `amiga-world-cups-players-results` | `/amiga/world-cups/players/results.php` |
| `amiga-world-cups-players-goals` | `/amiga/world-cups/players/goals.php` |
| `amiga-world-cups-players-dds` | `/amiga/world-cups/players/dds.php` |
| `amiga-world-cups-players-opponents` | `/amiga/world-cups/players/opponents.php` |
| `amiga-lb-world-cups` | `/amiga/leaderboards/world-cups/honours.php` (LB entry — **same table body** as hub player stats) |
| `amiga-lb-world-cups-honours` | `/amiga/leaderboards/world-cups/honours.php` |
| `amiga-lb-world-cups-results` | `/amiga/leaderboards/world-cups/results.php` |
| `amiga-lb-world-cups-goals` | `/amiga/leaderboards/world-cups/goals.php` |
| `amiga-lb-world-cups-dds` | `/amiga/leaderboards/world-cups/dds.php` |
| `amiga-lb-world-cups-opponents` | `/amiga/leaderboards/world-cups/opponents.php` |

**Player stats dual surface:** hub `world-cups/players/*` and LB `leaderboards/world-cups/*` share `includes/amiga_wc_players_wing_body.inc.php` — [`amiga-world-cups-hub-policy.md`](amiga-world-cups-hub-policy.md) WCH9.

**Country stats (hub wing 4):** `world-cups/countries/*` — Honours default (`honours.php`); Results · Goals · DDs · Opponents; routes in `k2_amiga_routes.php`; no LB mirror — [`amiga-world-cups-country-slice-policy.md`](amiga-world-cups-country-slice-policy.md) §8.1.

Query `?id=` required on all player tabs.

**Legacy redirects (302, query preserved):** `/amiga/profile.php` → profile; `/amiga/games.php` → player games; `/amiga/player-tournaments.php` → tournaments. `/amiga/games.php` is reserved for a future realm-wide match log.

**Not under `player/`:** `/amiga/tournament/` (per-event detail — foldered tabs below), `/amiga/history.php` (301 → rating LB; legacy bookmarks), hub pages under `/amiga/` (including `/amiga/world-cups/`). Player Opponents wings: `amiga/player/opponents/*`.

### Amiga tournament detail (`amiga/tournament/`)

Per-event pages use **foldered tabs** (not `?view=`). Entity id stays in query; phase scope uses `scope` / `scope_key` filters.

| Route key | Path | Tab |
|-----------|------|-----|
| `amiga-tournament-event-stats` | `/amiga/tournament/event-stats.php` | Event stats (default landing) |
| `amiga-tournament-standings` | `/amiga/tournament/standings.php` | League table / groups / bracket (ordinary events) |
| `amiga-tournament-stages` | `/amiga/tournament/stages.php` | Stages + sub-nav (World Cups) |
| `amiga-tournament-games` | `/amiga/tournament/games.php` | Games |
| `amiga-tournament-videos` | `/amiga/tournament/videos.php` | Videos (when manifest has rows for `id`) |

Query `?id=` required on all tabs. Optional `?player=` on games; `?scope=` / `?scope_key=` on standings/stages. **Videos tab** appears only when `amiga_tournament_has_videos($id)` — grouped sections (final, knockout, side, ceremony, coverage); lazy YouTube embed.

**Entry redirects (302, query preserved):** `/amiga/tournament.php` (legacy `?view=`) → folder path; `/amiga/tournament/index.php` → `event-stats.php`. Nav hrefs use named files only — **not** bare `index.php` as a tab target (same habit as WC stats `participation.php`, Games `recent.php`).

### Amiga hub tabs (present order)

News · Leaderboards · **World Cups** (`/amiga/world-cups/chronology.php`) · **Countries** (`/amiga/countries/index.php`) · Activity · Hall of Fame · Tournaments · Live tournaments — [`amiga_hub_nav_lib.php`](../site/public_html/includes/amiga_hub_nav_lib.php). Time travel bar: Leaderboards · World Cups · **Countries** · Activity · Hall of Fame.

| Route key | Path |
|-----------|------|
| `amiga-countries` | `/amiga/countries/index.php` |
| `amiga-countries-roster` | `/amiga/countries/roster.php?country={token}` |

Policy: [`amiga-countries-hub-policy.md`](amiga-countries-hub-policy.md).

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
| `join_alt.php`, `server1-charts-lab.php`, `status-realm-lab.php`, `individual1-profile-lab1.php` … `lab4.php` | removed |

---

## Milestone href helpers

`k2_milestone_detail_href()`, `k2_milestones_recent_href()`, and `k2_milestones_catalog_href()` in `player_milestones_helpers.php` delegate to `k2_route()` — use these instead of string-built `milestone.php` links.
