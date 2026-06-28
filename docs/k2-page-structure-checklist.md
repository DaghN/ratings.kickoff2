# K2 page structure checklist

**For agents (mandatory before adding a new page, hub tab, entity tab, or internal mode).**

Route authority: [`url-routes.md`](url-routes.md) § Sub-hub navigation · placement: [`navigation-model.md`](navigation-model.md) NM1–NM6 · nav chrome: [`k2-nav-implementation-checklist.md`](k2-nav-implementation-checklist.md).

**Do not invent URL shapes or query-param mode switches.** Find the closest **reference** below, read that entry file + shell include, then copy the pattern.

---

## 1) Decision tree (30 seconds)

| You are adding… | URL rule | Query params |
|-----------------|----------|--------------|
| **Hub / sub-hub tab or mode** (Recent · Catalog · H2H · …) | **One PHP file per mode under a folder** | Filters only: `?tier=`, `?sort=`, `?id=`, … |
| **Entity with internal tabs** (player, tournament, country, …) | **Singular namespace at realm root** — folder if multiple tabs (`player/`, `tournament/`) | Entity lookup: `?id=`, `?country=`; scope filters: `?scope=` |
| **Nested modes inside one entity tab** (e.g. Videos Games / Atmosphere) | **Subfolder under that tab**: `tournament/videos/games.php` | Playback / row state: `?v=`, `?game=` — not `?wing=` |
| **Single-page entity** (one game, one milestone) | Leaf file: `game.php?id=` | Lookup key only |

**Anti-patterns (do not add new ones):**

- `?view=` — retired Jun 2026 (`games/recent.php` not `games.php?view=recent`)
- `?wing=`, `?tab=`, `?mode=` — for navigation (collides with time-travel legacy `wing` + `at`)
- Entity page nested inside a **plural hub folder** (`countries/roster.php` for a country entity — use `country/roster.php`)

**Feature policy docs describe product; URL shape defers here and [`url-routes.md`](url-routes.md).** Do not lock query-param tabs in a feature spec without cross-checking this checklist.

---

## 2) Pick a reference (read one entry file first)

| Scenario | Entry path(s) | Shell / nav include |
|----------|---------------|---------------------|
| Online Games hub modes | `games/recent.php`, `highlights.php`, `all.php` | `games_hub_shell_*.inc.php`, `games_hub_nav.php` |
| Milestones hub modes | `milestones/recent.php`, `catalog.php` | `milestones_hub_shell_*.inc.php`, `milestones_hub_nav.php` |
| Leaderboards wing | `leaderboards/rating.php`, `goals.php`, … | `lb_nav.php` |
| LB Activity sub-modes | `leaderboards/activity/participation.php`, `peaks.php`, … | `lb_activity_nav.php` |
| Player opponents modes | `player/opponents/h2h.php`, `wdl.php`, … | `player_opponents_page.php`, `player_opponents_nav.php` |
| Player milestones modes | `player/milestones/garden.php`, `chronology.php` | `player_milestones_page.php`, `player_milestones_nav.php` |
| Amiga player wings | `amiga/player/profile.php`, `games.php`, … | `amiga_player_nav.php` |
| Amiga tournament entity tabs | `amiga/tournament/event-stats.php`, `games.php`, … | `amiga_tournament_page.php` |
| Amiga tournament Videos sub-modes | `amiga/tournament/videos/games.php`, `atmosphere.php` | same shell; `amiga_tournament_videos_wc_render.inc.php` mode nav |
| Amiga country entity segment | `amiga/country/roster.php`, `rivals.php` | `amiga_country_page.php`, `amiga_country_nav.php` |
| Amiga WC hub wings | `amiga/world-cups/chronology.php`, `players/honours.php`, … | `amiga_world_cups_hub_shell_*.inc.php`, wing nav includes |

If unsure: **grep** `k2_amiga_route(` / `k2_route(` in the nearest neighbour and open its thin entry file.

---

## 3) Implementation habit

1. **Thin entry file** per mode — set view variables; `require` shared shell (one-liner pattern like `event-stats.php`).
2. **Register routes** — `K2_ROUTES` (online) or `K2_AMIGA_ROUTES` + `k2_route()` / `k2_amiga_route()`; hub default key points at the default mode file.
3. **Href helpers** — build URLs via route helpers + time-travel wrappers (`amiga_tournament_href`, `amiga_url_with_context`); no hardcoded relative paths from subfolders.
4. **Sub-nav hrefs** — full paths from route keys only.
5. **Legacy redirect (302)** when replacing a query-param mode — preserve `id`, filters, `as=`; drop retired mode keys (`view`, `wing`).
6. **Nav chrome** — then read [`k2-nav-implementation-checklist.md`](k2-nav-implementation-checklist.md) for markup/spacing.
7. **Tables on the page** — [`k2-table-implementation-checklist.md`](k2-table-implementation-checklist.md).

---

## 4) Entity vs hub (NM3–NM4 quick check)

| | Hub **place** (active pill OK) | **Entity** page (no active pill) |
|--|-------------------------------|----------------------------------|
| Naming | Plural: `tournaments.php`, `countries/`, `leaderboards/` | Singular: `tournament/`, `country/`, `player/` |
| Example | `/amiga/countries/index.php` | `/amiga/country/roster.php?country=` |
| Hub bar | Present; pill lit on hub places only | Present; **no** active pill (NM2) |

Full rules: [`navigation-model.md`](navigation-model.md).

---

## 5) Before shipping — self-check

- [ ] Each new tab/mode has its **own PHP file** under a folder (not `?view=` / `?wing=` / `?tab=`).
- [ ] Route registered; links use `k2_route()` / `k2_amiga_route()` or domain href helper.
- [ ] Active pill / page placement matches NM1–NM6.
- [ ] Nav copied from nearest reference ([`k2-nav-implementation-checklist.md`](k2-nav-implementation-checklist.md) §1).
- [ ] [`url-routes.md`](url-routes.md) updated (route table + shipped examples if new area).
- [ ] Part A: [`UPDATE_DOCS.md`](UPDATE_DOCS.md) — `PROJECT_MEMORY.md` line.

---

## Related

- [`url-routes.md`](url-routes.md) — canonical route map
- [`navigation-model.md`](navigation-model.md) — hub vs entity invariants
- [`k2-nav-implementation-checklist.md`](k2-nav-implementation-checklist.md) — chrome tabs markup
- [`hub-ia-agreement.md`](hub-ia-agreement.md) — hub tab order and labels