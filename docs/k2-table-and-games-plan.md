# K2 Tables And Games Plan

**Status:** current table behavior contract, May 2026. Earlier phase diary has been compressed; use git history for detailed implementation logs.

**Purpose:** keep table behavior predictable while legacy table JS is replaced by small, page-specific patterns.

---

## Current State

| Page / include | Current behavior |
|----------------|------------------|
| `ranked1`-`ranked5`, `ranked7`, `ranked10`, league honours | `k2-table.js` sort + autorank; `data-k2-anchor-col` for one permanent link-star column per wing; lighter `k2-table-col-sorted` on the active sort column when it differs from the anchor. Optional deep link: `?k2_sort={col}&k2_dir=desc|asc` (Hall of Fame values via `records_hof_links.php`) — applies one client-side sort on init for `ranked-pages-table` only; does not change anchor column. |
| `leaderboards/activity/*.php` | Activity wing: Peaks · Participation · In a row; calm-stats; tooltips on peak/streak cells; peak counts link to player Games (`includes/player_games_from.php`). |
| `ranked8` / `peak_period_leaderboards_section.php` | **Calendar** day/week/month/year: calm-stats, Games anchor (col 3), not sortable; **All time / Longevity**: calm-stats + sort + anchor (Games / Days). |
| `player/wdl.php` (W/D/L) | `k2-table.js` sort; default **Games** desc; `k2-table--calm-stats` + `ranked-pages-table`; **Games** anchor (col 1, link-star); blue/red on W/L/ratios; other columns `k2-table-col-sorted` when active (Jun 2026). |
| `player/goals.php` (Goals) | Hub Goals LB parity: calm-stats (no blue/red), **Games** anchor (col 1), `lb_column_help` headers/tooltips; Win/Loss margin = `MAX` only on winning/losing games (not signed diffs on all games); Draw sort via `data-k2-sort-value`. |
| `player/double-digits.php` (DDs) | Hub DD LB parity (`ranked3`): headers/tooltips from `lb_column_help`, calm-stats (no blue/red), **Games** anchor (col 1); column order matches ranked3. |
| `games/` hub | **Hub tab** after Milestones (`hub_nav.php`). Sub-nav **Recent** \| **Highlights** \| **All games** (`games_hub_nav.php`). **Recent:** `games/recent.php` — 14 day buckets; `k2-table--calm-stats` + `ranked-pages-table`; **TS** column; SSR + JS `k2-table-col-sorted`. **Highlights:** `games/highlights.php` — top 100 boards; compact rows; JS sort column. **All games:** `games/all.php` — full table + **TS**; server sort; PHP `k2-table-col-sorted`; **filters** (Player / Opponent / Score-line / Year); 250-row chevron pager; **Reset filters** accent pill (`k2-player-games-reset`); filter block spaced below sub-nav. Status **Games →** secondary entry retained. |
| `player/games.php` | Server-side Result/Opponent/Goals scored/Goals conceded/Goal sum filters, URL sort links, **500-row** slices (`K2_PLAYER_GAMES_PAGE_SIZE`), shared row renderer; `k2-table--calm-stats` + `k2-table--player-games` (secondary body, win/loss `.blue`/`.red` kept); PHP marks `k2-table-col-sorted` on active sort column; filters use shared `k2-archive-listbox` + `k2-archive-listbox.js` — **blank default** trigger + first dropdown row when unfiltered; **active pick** trigger gets `k2-link-star`; **plain tab:** filter bar + table-meta (showing + page chevrons + Reset filters); **drill-down** (day / period / streak): context banner only — filter bar and reset hidden; table-meta + page chevrons only if total > 500; **day** banner with played-day chevrons; **week/month/year** via `period`+`anchor` (Activity peaks, played-weeks); streak run via `from_game`/`to_game`. |
| `game.php` | Static single-game table with `k2-table.js` header help only; no sorting; **TS** column after Sum. Below the table when a game exists: short “while we wait for browser replay” copy + 16:9 YouTube embed (2024 Online WC final placeholder). |
| `status.php` | Active LB: sort + Elo anchor; league tables: calm-stats + Pts/Games anchors — **PHP** (`k2_league_table_render.php`) and **JS** (`status-period-competitions.js` inject + `k2TableApplyAnchors`). |
| `hall-of-fame.php` | HoF record panels: calm-stats; Value column anchor; all values → leaderboard wings + `provisional=0` + `k2_sort`; dates keep `(New!)` / `(Legendary)` markers. |
| `activity.php`, `hall-of-fame.php` | Activity summary cards plus static themed tables; no general table JS. |
| `player/profile.php` | Profile/charts; no general data table behavior. |

Site-wide table styling belongs to `stylesheets/theme.css`; simple sortable behavior belongs to `js/k2-table.js`.

`elolist.js` is no longer part of the active migrated leaderboard/player-games paths. `elolist.css` has been removed; the ranked FOUC cloak now lives in `theme.css`.

---

## Shared Contracts

### Visual

- Use `k2-table-wrap` + `k2-table` as the shared foundation.
- No zebra striping.
- Table headers stay muted; hover/active sort states use the shared theme tokens.
- Use utility/data attributes instead of legacy class bundles for new sortable tables.

### Behavior

Tables opt in explicitly:

| Profile | Pattern |
|---------|---------|
| Static | `class="k2-table"` only |
| Sortable | `data-k2-table="sortable"` and `data-k2-sort` on headers |
| Sortable + autorank | Above + `data-k2-autorank="true"` |
| Server-filtered large table | URL/query-driven PHP, not all rows in DOM |

---

## Games Row Contract

`game.php`, `games/recent.php`, `games/all.php`, and Highlights share rated-game row rendering through `includes/k2_rated_game_row.php`. Highlights use `variant=compact` (no Elo/adjustment columns; **TS** column on top-score board). Full rows (Recent, All, single-game) always include **TS** after Sum.

Canonical row rules:

- ID is linked in lists and plain on the single-game page.
- Date is formatted consistently.
- Player names are escaped and linked.
- Winner cell shows winner link or `Draw`.
- ActualScore comparisons use tolerance.
- Ratings are rounded.
- `games.php` uses sortable headers for every visible game-list column; date/Fav ES/adjustment cells provide raw sort values.
- **TS (top score)** — `max(GoalsA, GoalsB)`; column header **TS**, tooltip label **Top score**. Prose elsewhere keeps *goals scored in a game*; sort/filter param `top_score`.
- Rating `Diff` displays rounded integer Elo difference; `Fav ES` shows expected score for the higher-rated player.
- Games-tab keeps header popups on all sortable columns; obvious labels can fall back to the shared `Click to sort.` action while abbreviation/context tooltips stay explicit. `game.php` mirrors only the useful non-sortable header help. Deep Elo explanation lives on `Fav ES` (expected-score formula/examples) and visible `Adjustment` (rating-change math with K = 32).
- Adjustment cells use the shared adjustment helper.

Do not fork Games-tab row markup unless the shared renderer is updated too.

---

## Player Games Contract

`player/games.php` is intentionally server-side now:

- Result filter: All / Wins / Draws / Losses.
- Opponent filter: All / opponent list for this player.
- Sort state in `sort` + `dir` query params; default **`id` desc** (newest games first; avoids highlighting Date on first paint).
- 500-row slices with chevron page nav when more rows exist (plain Games tab; drill-down only when total > 500).
- Shared row renderer: `includes/k2_player_game_row.php`.

Product requirement: keep Result and Opponent narrowing; normal URL flow is the contract.

---

## All Games Contract (phase 2)

`games/all.php` + `includes/k2_realm_games_all.php` + `includes/k2_realm_games_all_filters_ui.php`:

- **Sort:** server-side whitelist incl. `top_score` → `GREATEST(GoalsA, GoalsB)`; default `id` desc.
- **Pagination:** 250 rows; `offset` param; Reset clears sort, offset, and filters.
- **Shared WHERE:** `includes/k2_ratedresults_games_filters.php` — also used by `player/games.php`. `player_id = 0` = realm-wide.
- **Filter UI (four rows):**
  - **Player** — search (`player-search.js` filter mode) + **Rating** listbox (name, rating meta; sort name → rating) + **A–Z** listbox; realm `playertable` (`Display = 1`).
  - **Opponent** — muted until `player` set; search (`player_h2h_opponent_search` API) + **By games** + **A–Z** listboxes (H2H opponent set).
  - **Score-line** — `gd`, `gs`, `ts` listboxes (realm-wide distinct values + game counts).
  - **Year** — `year` + `year_mode` (`in` \| `since` \| `until`).
- **URL params:** `player`, `opponent`, `gd`, `gs`, `ts`, `year`, `year_mode`, `sort`, `dir`, `offset`.
- **JS:** `k2-realm-games-filters.js` + `k2-archive-listbox.js`; form `data-k2-carry-scroll`.
- Sort/pager links preserve active filter params; filter change drops `offset`.
- **Active sort column:** PHP `k2-table-col-sorted` via `k2_rated_game_sort_col_index()`.

---

## `k2-table.js` Contract

Current supported behavior:

- `data-k2-table="sortable"`.
- `data-k2-sort="number|text"`.
- `data-k2-default-sort` / `data-k2-default-direction` for server-rendered default state.
- `data-k2-anchor-col` (0-based column index) for one permanent wing anchor styled as link-star in body cells; optional — omit on tables without an editorial hero column.
- `data-k2-autorank="true"` for first-column rank renumbering.
- `data-k2-help` / `data-k2-tooltip-label` for shared header help; help can exist without sorting, and the “Click to sort.” hint appears only on sortable headers. Avoid `data-k2-help` that only repeats the visible column label. Hub wing copy: `site/public_html/includes/lb_column_help.php` (May 2026).
- `data-k2-help-html="1"` + `data-k2-tooltip-hide-title="1"` for body-cell tooltips (Activity peaks / in-a-row).
- `aria-sort`, keyboard Enter/Space, and sortable header help behavior.
- Same-column toggles flip asc/desc via a full re-sort (stable tie order), not DOM reverse only.
- Deep link / filter persistence: `?k2_sort={col}&k2_dir=desc|asc` on load (`ranked-pages-table` only). Column sort updates the URL via `history.replaceState` and refreshes **Include inactive / provisional** toggle hrefs on the same wing; `k2_lb_filter_toggle_href()` merges sort params server-side. Wing tab links do **not** carry `k2_sort` (column indices differ per page).

Do not grow this into a generic table framework unless a real repeated need appears.

### Leaderboard column layout

**PHP helpers (`k2_table_helpers.php`):** `k2_table_ranked_sortable_class()` — sortable bundle (`ranked-pages-table`, cloak, calm-stats, auto column widths). `k2_table_ranked_leaderboard_class()` — adds **`k2-table--hub-rank-player-cols`** (Rank col 1 = 2.7em, Player col 2 min 9.1em) for hub LB wings only. Status league tables, games hub, tournament stats, opponents, etc. use the sortable helper without hub cols.

**Scroll mirror (overflow top bar):** `js/k2-table-scroll-mirror.js` activates only when a wrapped table’s `scrollWidth > clientWidth`. Head: `includes/k2_sortable_table_assets_head.inc.php` (mirror on by default; `$k2SortableTableScrollMirror = false` to opt out). Online hub LBs: `includes/k2_lb_sortable_table_head.inc.php`. Markup: `k2_table_wrap_open(true)` / `k2_table_wrap_close()`. Wide tables (hub LBs both realms, games hub Recent/All, league period games, player games, Amiga WC stats/players, tournament event-stats) use mirror; compact/narrow tables (status league, highlights) do not.

All hub wing tables use **`ranked-pages-table`**: uniform `8px` horizontal cell padding in `theme.css`; **no** per-column `k2-table-cell--pad-left-*` on headers (legacy header-only pads widened whole columns when labels were renamed). Optional future refactor: shared PHP column registry + responsive short/long labels via CSS, not table-by-table padding tweaks. Profile opponent tables (`individual2a/b/c`) still use pad utilities for now.

### Anchor column map (0-based `data-k2-anchor-col`)

| Table | Anchor column |
|-------|----------------|
| `leaderboards/peak-rating.php` (Rating) | 4 — Peak (default sort; current Elo col 2 is neutral) |
| `leaderboards/goals.php` (Goals) | 4 — Scored |
| `leaderboards/double-digits.php` (DD) | 4 — Double Digits |
| `leaderboards/streaks.php` (Streaks) | 4 — Wins |
| `leaderboards/victims.php` (Victims) | 5 — Victims |
| `leaderboards/rating.php` (Results) | 2 — Elo |
| `leaderboards/milestones.php` (Milestones) | 8 — Milestones total |
| `leaderboards/activity/peaks.php` | 2 — ELO rating |
| `leaderboards/activity/participation.php` | 2 — ELO rating |
| `leaderboards/activity/in-a-row.php` | 2 — ELO rating |
| `league_honours_panel.php` | 4 — Gold |
| Status active leaderboard | 2 — Elo |

Elo is **not** an anchor on Goals/DD/Streaks/Victims/Milestones wings (context column only).

All hub sortable LB rows above use **`k2-table--calm-stats`**: neutral body cells (no `.blue`/`.red`); active sort = weight 600 on primary text.

| Surface | Anchor (`data-k2-anchor-col`) | Sortable |
|---------|------------------------------|----------|
| `ranked8` period panels (day/week/month/year/all-time) | 3 — Games | Calendar grid: no; All-time panel: yes |
| `ranked8` Longevity | 4 — Days | yes |
| Status points league | 9 — Pts | no |
| Status activity league | 2 — Games | no |
| `hall-of-fame.php` records (both panels) | 1 — Value column | no |

`data-k2-anchor-col` on non-sortable tables is applied on load via `k2-table.js` `initAnchorTables()`. Profile opponent tables (`individual2a/b/c`) unchanged.

---

## Completed Milestones

| Date | Milestone |
|------|-----------|
| 2026-05-24 | Baseline audit recorded legacy `elolist.js` usage, no-op classes, row parity gaps. |
| 2026-05-24 | Cleanup removed dead striping/no-op table classes and unused `elolist.js` includes. |
| 2026-05-24 | `game.php` and `games.php` moved to shared rated-game row rendering. |
| 2026-05-25 | Games tab became seven static day buckets. |
| 2026-05-25 | `k2-table.js` introduced and migrated simple leaderboards plus `ranked8`. |
| 2026-05-25 | `individual2a/b/c.php` migrated to `k2-table.js`. |
| 2026-05-25 | `player/games.php` moved to server-side filters/sort/100-row slices and shared row rendering. |
| 2026-05-25 | Sortable header help tooltip and same-column reverse-sort tie handling shipped. |
| 2026-05-25 | `elolist.css` removed; ranked cloak moved into `theme.css`. |
| 2026-05-26 | Inline table layout cleanup removed legacy table `&nbsp;` / inline `text-align` spacing hacks from active ranked, player-games, game, server stats/records/activity, and Status table paths. |
| 2026-05-26 | `games.php` Games became 14-day, fully sortable daily tables; rated-game row now exposes `GD`, integer `Elo Diff`, `Fav ES`, and sortable adjustment columns. |

---

## Testing Checklist

- Sortable tables: default active header, click sort, same-column asc/desc toggle, rank renumber where expected.
- Games tab: 14 sections, empty-day state, newest-first rows/default Date highlight, all-column sorting, row parity with `game.php`.
- Player Games: filter result/opponent, sort links, Previous/Next, URL reload fallback.
- Static tables: no unexpected JS errors or script requirements.

*Last pruned: May 2026 — current behavior separated from completed phase diary.*
