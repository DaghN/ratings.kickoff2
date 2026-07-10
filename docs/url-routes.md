# URL routes

**Status:** Jun 2026 — legacy `server*`, `ranked*`, `individual*` page names replaced with semantic paths.

**Authority:** Runtime map in [`site/public_html/includes/k2_routes.php`](../site/public_html/includes/k2_routes.php). Hub IA labels in [`hub-ia-agreement.md`](hub-ia-agreement.md). **New paths:** [`k2-page-structure-checklist.md`](k2-page-structure-checklist.md).

---

## Rules

1. **Canonical paths** — use `k2_route('route-key', $params)` or constants in `K2_ROUTES`; do not hardcode legacy filenames.
2. **Root-absolute URLs** — `k2_route()` returns paths like `/milestones/recent.php` (leading `/`). Required so hub and cross-links work from `leaderboards/` and `player/` subfolders.
3. **Shared assets** — `/js/…`, `/stylesheets/…`, `/fonts/…` in `k2_head.php` and page templates (not bare `js/…` on subfolder pages).
4. **Amiga** — player universe under `amiga/player/`; route keys in `includes/k2_amiga_routes.php` (`k2_amiga_route()`). Legacy flat URLs 302 to canonical paths.
5. **Foldered sub-hubs, not query-param modes** — see [§ Sub-hub navigation](#sub-hub-navigation-foldered-not-view) below. **Do not** add new `?view=`, `?wing=`, `?tab=`, or similar switches for “where am I?” navigation.

---

## Sub-hub navigation (foldered, not `?view=`)

**Product rule (Jun 2026):** When a page has internal tabs or modes (Recent · Catalog · Highlights · All games, etc.), prefer **one PHP file per mode under a folder**, not a single root file with a query-param mode switch (`?view=`, `?wing=`, `?tab=`, …).

| Kind | URL shape | Examples |
|------|-----------|----------|
| **Where am I?** (tab / sub-hub mode) | Folder path | `/games/recent.php`, `/milestones/catalog.php`, `/player/opponents/h2h.php` |
| **What am I filtering?** (state on that page) | Query params | `?tier=`, `?board=`, `?sort=`, `?opponent=`, `?id=` |

**Why:** Clearer mental model for Dagh and agents; matches **leaderboards/** and **player/opponents/**; bookmarks and cross-links read as places, not switch statements.

**Implementation habit:**

- Thin entry files (`games/recent.php`, …) + shared shell include (`*_hub_shell_start.inc.php`, sub-nav include).
- Register each mode in `K2_ROUTES` (`games-recent`, `milestones-catalog`, …); hub default key keeps short name (`games` → recent, `milestones` → recent).
- Sub-nav hrefs via `k2_route()` only — never bare relative `recent.php` on subfolder pages.
- **Do not** add new query-param mode switches for navigation. Legacy `?view=` / `?wing=` on old URLs 302 to folder paths — not a pattern to copy.

**Pre-public refactors:** Update on-site links via `k2_route()` / href helpers; **no** bookmark redirect layer required until the site is public with stable external URLs.

**Shipped examples:**

| Area | Folder | Modes |
|------|--------|-------|
| Games hub | `games/` | `recent.php`, `highlights.php`, `all.php` |
| Amiga Games hub | `amiga/games/` | `recent.php`, `highlights.php`, `all.php` |
| Milestones hub | `milestones/` | `recent.php`, `catalog.php` |
| Leaderboards | `leaderboards/` | one wing per file |
| Player opponents | `player/opponents/` | `h2h.php`, `wdl.php`, … |
| Player milestones | `player/milestones/` | `garden.php`, `chronology.php` |
| Amiga tournament Videos (nested under entity tab) | `amiga/tournament/videos/` | `games.php`, `atmosphere.php` |

**Detail pages stay at root or a stable path** when they are not sub-nav peers — e.g. `milestone.php?key=`, `game.php?id=` (key/id are entity lookup, not hub mode). Inbound game links use `k2_game_page_url($id)` → `/game.php?id=` + `#k2-game` so the viewport lands on the game table (hub chrome stays above the fold). Inbound milestone links use `k2_milestone_detail_href($key)` → `/milestone.php?key=` + `#k2-ms-detail-spotlight` (spotlight card; `$k2ScrollTargetId` on `milestone.php` for bare key-only URLs). Amiga: `k2_amiga_game_page_url($id)` → `/amiga/game.php?id=` + hash from manifest — `#k2-game` when no video; `#k2-amiga-game-videos-caption` (1 clip) or `#k2-amiga-game-videos-menu` (2+ clips). `$k2ScrollTargetId` on the page matches the same rule for bare id-only URLs.

**Entity vs hub naming ([`navigation-model.md`](navigation-model.md) NM3/NM4):** an **entity page** (a single game / player / tournament / country / milestone) lives at the realm root as its **own singular namespace** — leaf file if single-page (`game.php`), folder if it has tabs (`player/`, `tournament/`). It is **never** nested inside a **hub-tab folder** (the **plural** form: `tournaments.php`, `countries.php`, `world-cups/`, `leaderboards/`). Entity pages show **no active hub pill** (NM2).

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
| `amiga-player-videos` | `/amiga/player/videos.php` |
| `amiga-player-opponents-h2h` | `/amiga/player/opponents/h2h.php` (default Opponents tab) |
| `amiga-player-opponents-wdl` | `/amiga/player/opponents/wdl.php` |
| `amiga-player-opponents-goals` | `/amiga/player/opponents/goals.php` |
| `amiga-player-opponents-dds` | `/amiga/player/opponents/dds.php` |
| `amiga-player-opponents-country-h2h` | `/amiga/player/opponents/country/h2h.php` (country grain — default drill-down `country={token}`) |
| `amiga-player-opponents-country-wdl` | `/amiga/player/opponents/country/wdl.php` |
| `amiga-player-opponents-country-goals` | `/amiga/player/opponents/country/goals.php` |
| `amiga-player-opponents-country-dds` | `/amiga/player/opponents/country/dds.php` |
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
| `amiga-lb-world-cups` | **Deprecated alias** → `/amiga/world-cups/players/honours.php` |
| `amiga-lb-world-cups-honours` | **Deprecated alias** → `/amiga/world-cups/players/honours.php` |
| `amiga-lb-world-cups-results` | **Deprecated alias** → `/amiga/world-cups/players/results.php` |
| `amiga-lb-world-cups-goals` | **Deprecated alias** → `/amiga/world-cups/players/goals.php` |
| `amiga-lb-world-cups-dds` | **Deprecated alias** → `/amiga/world-cups/players/dds.php` |
| `amiga-lb-world-cups-opponents` | **Deprecated alias** → `/amiga/world-cups/players/opponents.php` |
| `amiga-lb-performance-rating` | `/amiga/leaderboards/performance-rating/best.php` (Perf. rating default — Best) |
| `amiga-lb-performance-rating-best` | `/amiga/leaderboards/performance-rating/best.php` |
| `amiga-lb-performance-rating-top` | `/amiga/leaderboards/performance-rating/top.php` |
| `amiga-lb-performance-rating-perfect` | `/amiga/leaderboards/performance-rating/perfect.php` |
| `amiga-games` | `/amiga/games/recent.php` (Recent default; `/amiga/games.php` 302) |
| `amiga-games-recent` | `/amiga/games/recent.php` |
| `amiga-games-highlights` | `/amiga/games/highlights.php` |
| `amiga-games-all` | `/amiga/games/all.php` |
| `amiga-activity` | `/amiga/activity/growth.php` (Activity hub default — Growth; `/amiga/activity.php` 302) |
| `amiga-activity-growth` | `/amiga/activity/growth.php` |
| `amiga-activity-people` | `/amiga/activity/people.php` |
| `amiga-activity-geography` | `/amiga/activity/geography/hosts.php` (Geography default — Host nations) |
| `amiga-activity-geography-hosts` | `/amiga/activity/geography/hosts.php` |
| `amiga-activity-geography-nations` | `/amiga/activity/geography/nations.php` |
| `amiga-activity-world-cups` | `/amiga/activity/world-cups.php` |
| `amiga-activity-texture` | `/amiga/activity/texture.php` |
| `amiga-activity-shape` | `/amiga/activity/shape.php` |

**Activity hub (Jul 2026):** foldered sub-hub, six wings (Growth · People · Geography · World Cups · Texture · Shape); Geography has a nested Host nations · Nationalities segment. `/amiga/activity.php` → **302** Growth (query preserved). Chart selector state (`hosts=`, `nats=`) is a filter param, not navigation — [`amiga-activity-charts-policy.md`](amiga-activity-charts-policy.md). **Track complete** — **49 panels / 50 ship IDs** (incl. Nations player grains Q-GEO-016…018 + WC avg games/participant Q-WC-012); World Cups wing **7 panels** (participants · nations first); see [`amiga-activity-charts-implementation-plan.md`](amiga-activity-charts-implementation-plan.md).

**Player stats:** hub `world-cups/players/*` only — [`amiga-world-cups-hub-policy.md`](amiga-world-cups-hub-policy.md) WCH9. Legacy `/amiga/leaderboards/world-cups/*` → **302** hub paths.

**Country stats (hub wing 4):** `world-cups/countries/*` — Honours default (`honours.php`); Results · Participation · Goals · DDs · Opponents; routes in `k2_amiga_routes.php`; no LB mirror — [`amiga-world-cups-country-slice-policy.md`](amiga-world-cups-country-slice-policy.md) §8.1.

Query `?id=` required on all player tabs.

**Legacy redirects (302, query preserved):** `/amiga/profile.php` → profile; `/amiga/games.php?id=` → player games; bare `/amiga/games.php` → Games hub Recent; `/amiga/player-tournaments.php` → tournaments; `/amiga/leaderboards/performance-rating.php` → `performance-rating/best.php`; `/amiga/leaderboards/world-cups/*` → matching `world-cups/players/*` hub path (Jun 2026).

**Not under `player/`:** `/amiga/tournament/` (per-event detail — foldered tabs below), `/amiga/history.php` (301 → rating LB; legacy bookmarks), hub pages under `/amiga/` (including `/amiga/world-cups/`). Player Opponents: `amiga/player/opponents/*` (**player vs player**) · `amiga/player/opponents/country/*` (**player vs country** — [`amiga-opponents-country-grain-policy.md`](amiga-opponents-country-grain-policy.md)). Country entity Rivals: `amiga/country/rivals/*` (**country vs country** — [`amiga-country-rivals-policy.md`](amiga-country-rivals-policy.md) §1.1).

### Amiga tournament detail (`amiga/tournament/`)

Per-event pages use **foldered tabs** (not `?view=`). Entity id stays in query; phase scope uses `scope` / `scope_key` filters.

| Route key | Path | Tab |
|-----------|------|-----|
| `amiga-tournament-event-stats` | `/amiga/tournament/event-stats.php` | Event stats (default landing) |
| `amiga-tournament-standings` | `/amiga/tournament/standings.php` | **302 →** `stages.php` (legacy bookmarks) |
| `amiga-tournament-stages` | `/amiga/tournament/stages.php` | Stages tab + sub-nav (all events with league/KO scopes) |
| `amiga-tournament-games` | `/amiga/tournament/games.php` | Games |
| `amiga-tournament-videos` | `/amiga/tournament/videos/games.php` | Videos — Games (default; when manifest has rows for `id`) |
| `amiga-tournament-videos-games` | `/amiga/tournament/videos/games.php` | Videos — Games |
| `amiga-tournament-videos-atmosphere` | `/amiga/tournament/videos/atmosphere.php` | Videos — Atmosphere |

Query `?id=` required on all tabs. Optional `?player=` on games; `?scope=` / `?scope_key=` on standings/stages. **Videos modes** are folder paths (not `?wing=`). **Deep links (WC spotlight, live):** `?v={youtube_id}`, optional `?game={amiga_game_id}` on Games mode, future `?t=` seconds — see [`k2-embedded-video-page-policy.md`](k2-embedded-video-page-policy.md). Tab appears only when `amiga_tournament_has_videos($id)`.

**Legacy redirect (302):** `/amiga/tournament/videos.php` → `videos/games.php` (or `videos/atmosphere.php` when legacy `?wing=extras`).

**Entry redirects (302, query preserved):** `/amiga/tournament.php` (legacy `?view=`) → folder path; `/amiga/tournament/index.php` → `event-stats.php`. Nav hrefs use named files only — **not** bare `index.php` as a tab target (same habit as WC stats `participation.php`, Games `recent.php`).

### Amiga hub tabs (present order)

News · Leaderboards · **World Cups** (`/amiga/world-cups/chronology.php`) · Tournaments · **Countries** (`/amiga/countries.php`) · Games · **Activity** (`/amiga/activity/growth.php`) · Hall of Fame · Live — [`amiga_hub_nav_lib.php`](../site/public_html/includes/amiga_hub_nav_lib.php). Time travel bar: Leaderboards · World Cups · Tournaments · **Countries** · Games · Activity · Hall of Fame (editorial present-only: News · Live).

A single country is an **entity page** ([`navigation-model.md`](navigation-model.md) NM3): it lives in the singular `country/` namespace with a **Roster · Rivals** segment (NM6), not inside the plural `countries/` hub folder.

| Route key | Path | Segment |
|-----------|------|---------|
| `amiga-countries` | `/amiga/countries.php` | Countries **hub place** (plural leaf, like `tournaments.php`) — keeps active pill |
| `amiga-country-roster` | `/amiga/country/roster.php?country={token}` | Roster (default; career roster table) |
| `amiga-country-rivals` | `/amiga/country/rivals.php?country={token}` | Rivals legacy redirect → `rivals/h2h.php` |
| `amiga-country-rivals-h2h` | `/amiga/country/rivals/h2h.php?country={token}` | Rivals — Head-to-head (`rival={token}` drill-down; domestic A→A excluded) |
| `amiga-country-rivals-wdl` | `/amiga/country/rivals/wdl.php?country={token}` | Rivals — W/D/L |
| `amiga-country-rivals-goals` | `/amiga/country/rivals/goals.php?country={token}` | Rivals — Goals |
| `amiga-country-rivals-dds` | `/amiga/country/rivals/dds.php?country={token}` | Rivals — DDs |

**Rivals filter params (not navigation):** `rival=` = opponent nation on H2H and games links; `country=` on `/amiga/games/all.php` pairs with `rival=` for nation-pair game lists. Do **not** reuse `opponent=` (player id) on Rivals URLs.

`k2_amiga_country_roster_href()` / `k2_amiga_country_rivals_href()` build these; every flag cell ([`amiga-countries-hub-policy.md`](amiga-countries-hub-policy.md) CH9) routes through the roster helper. Legacy `amiga-countries-roster` (`/amiga/countries/roster.php`) is now a **302** to `/amiga/country/roster.php` (preserves `country` + `as`). Legacy `countries/index.php` **302** to `countries.php`. The **Countries** pill is active only on `countries.php` (NM2); country entity pages carry no active pill. Policy: [`amiga-countries-hub-policy.md`](amiga-countries-hub-policy.md).

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
