# K2 Tables And Games Plan

**Status:** current table behaviour contract, **Jun 2026**. Earlier phase diary compressed; use git history for May 2026 rollout detail.

**Agents — start here:** [`k2-table-implementation-checklist.md`](k2-table-implementation-checklist.md) (mandatory before new/refactored sortable tables; reference implementations by scenario).

**Compliance audit:** `python scripts/audit_k2_table_compliance.py` from repo root — see [§ Compliance backlog](#compliance-backlog) below.

**Purpose:** keep table behavior predictable while legacy table JS is replaced by small, page-specific patterns.

**Legacy filenames:** docs may still say `ranked1`–`ranked10`; live hub leaderboard paths are `leaderboards/*.php` (and `amiga/leaderboards/*.php`). Player opponent wings: `player/opponents.php` views via `includes/player_opponents_*.php` (not `individual2a/b/c`).

---

## Current State

| Page / include | Current behavior |
|----------------|------------------|
| `leaderboards/*.php` (online; legacy docs: `ranked1`–`ranked7`, `ranked10`) | `k2-table.js` sort + autorank; `$k2RankedCloak` + `k2_lb_sortable_table_head.inc.php`; `k2_table_ranked_leaderboard_class()`; `k2_table_wrap_open(true)`; `data-k2-anchor-col` on Player (col 2); default sort per wing. Optional `?k2_sort` / `k2_dir`. |
| `amiga/leaderboards/*.php` | Same hub LB stack as online; Amiga player links. |
| `leaderboards/league-honours.php` | Hub LB stack; `includes/league_honours_panel.php` body. |
| `leaderboards/activity/*.php` | Activity wing: Peaks · Participation · In a row; calm-stats; tooltips on peak/streak cells; peak counts link to player Games (`includes/player_games_from.php`). |
| `ranked8` / `peak_period_leaderboards_section.php` | **Calendar** day/week/month/year: calm-stats, Games anchor (col 3), not sortable; **All time / Longevity**: calm-stats + sort + anchor (Games / Days). |
| `player/opponents.php` (W/D/L · Goals · DDs) | Ledger views: `$k2RankedCloak` + sortable assets; `includes/player_opponents_tables.php` / Amiga twin; anchor col 1 (Games); scroll mirror; H2H poster unchanged. |
| `games/` hub | **Hub tab** after Milestones. Shell: `includes/games_hub_shell_start.inc.php` (`$k2RankedCloak` + sortable assets). Sub-nav **Recent** \| **Highlights** \| **All games**. **Recent:** 14 day buckets; `k2_table_ranked_sortable_class`. **Highlights:** compact boards (`games_highlights_helpers.php`). **All games:** server sort + filters + 250-row pager. |
| `player/games.php` | Server-side filters/sort/**500-row** slices; shared row renderer; calm-stats + player-games modifier; PHP `k2-table-col-sorted`; archive listbox filters. |
| `league.php` period games | `includes/k2_league_period_page.php` — sortable + mirror; scoped `k2_sort` via `data-k2-sort-scope`. |
| `game.php` | Static single-game table with `k2-table.js` header help only; no sorting; **TS** column after Sum. |
| `status.php` | Active LB: sort + Elo anchor; league tables: calm-stats + Pts/Games anchors — **PHP** (`k2_league_table_render.php`) and **JS** (`status-period-competitions.js`). |
| `hall-of-fame.php` | HoF record panels: calm-stats; Value column anchor; static/special table JS usage. |
| `activity.php` | Activity summary cards plus static themed tables; no general sortable table JS. |
| `player/profile.php` | Profile/charts; no general data table behavior. |
| **Amiga** `player/tournaments.php` | Full event history; cloak + SSR sort/anchor; filter pills carry `k2_sort`. |
| **Amiga** `tournaments.php` | Catalog index; format filter pills; `amiga_tournament_index_render_table()`. |
| **Amiga** `live-tournaments.php` | Live allowlist index; `amiga_live_tournament_index_render_table()`. |
| **Amiga** `tournament.php` | Event stats table: full Tier A stack. Games list + knockout standings: **partial** (see backlog). |
| **Amiga** WC hub / stats | Shell includes + `amiga_world_cup_stats_table.php` / `amiga_wc_players_table.php`. |

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

### Sort flash (FOUC) — Jun 2026

Filter/tab **full page reloads** on client-sortable tables flash when:

1. Table is visible before `k2-table.js` init re-sorts or restyles.
2. Head lacks **`$k2RankedCloak`** (`includes/ranked_table_cloak_head.php` → `html.ranked-js` hides `.ranked-table-pending`).
3. Table lacks **`ranked-pages-table`** + **`ranked-table-pending`** (via `k2_table_ranked_*_class()`).
4. SSR sort/anchor classes missing on first paint (optional but recommended for wide tables).
5. **`data-k2-skip-initial-sort="1"`** omitted when SQL order already matches default sort (JS reorders rows unnecessarily).

**Required for wide sortable + filter reload:** checklist §2 — cloak, sortable assets head, helpers, mirror wrap, filter URLs carry `k2_sort`.

**Hub LBs (Tier B):** cloak + LB head + leaderboard helper + mirror; SSR th/td not required yet (cloak covers init).

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

- Result filter: All / Win / Draw / Loss.
- Opponent filter: All / opponent list for this player.
- Goal filters: **GF** / **GA** / **GD** / **SUM** listboxes (`gf`, `ga`, `gd`, `gs`); GF/GA/SUM idle `-1`; **GD** is hero-signed (`GoalsA−GoalsB` or reverse) with `+N` / `−N` / `0` labels and empty-string idle (so `gd=0` is valid).
- **Faceted counts:** each listbox `meta` count reflects all *other* active filters (that dimension omitted). Numeric facets fill interior zero gaps between min/max values with games; tail zeros omitted. Filters are only cleared when a value never appears in the player's career — empty intersections (e.g. Draw + SUM 7) stay selected and return 0 rows. Implementation: `includes/k2_player_games_filter_facets.php`.
- Sort state in `sort` + `dir` query params; default **`id` desc** (newest games first; avoids highlighting Date on first paint).
- Filter listboxes: `$idleValue` on `k2_archive_listbox_render()` (`all` / `0` / `-1` / `''` per filter); empty idle labels in choice arrays.
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
  - **Opponent** — hidden until `player` set; search (`player_h2h_opponent_search` API) + **By games** + **A–Z** listboxes (H2H opponent set).
  - **Score-line** — `gd`, `gs`, `ts` listboxes (realm-wide distinct values + game counts). **Faceted counts (Jun 2026):** each listbox `meta` reflects other active filters (player, opponent, year, sibling score-line filters); absolute `GoalDifference` for GD; interior zero gaps kept. `k2_realm_games_filter_facets.php`.
  - **Year** — `year` + `year_mode` (`in` \| `since` \| `until`).
- **URL params:** `player`, `opponent`, `gd`, `gs`, `ts`, `year`, `year_mode`, `sort`, `dir`, `offset`.
- **JS:** `k2-realm-games-filters.js` + `k2-archive-listbox.js`; form `data-k2-carry-scroll`.
- **Filter listbox contract (Jun 2026):** `k2_archive_listbox_render(..., $idleValue)` — empty trigger at idle, link-star when active; JS reads `data-k2-listbox-idle-value`. **Year mode** row hidden until year chosen (`hidden`, like Opponent row); when visible, `$accentActive` (always accented).
- Sort/pager links preserve active filter params; filter change drops `offset`.
- **Active sort column:** PHP `k2-table-col-sorted` via `k2_rated_game_sort_col_index()`.

---

## `k2-table.js` Contract

Current supported behavior:

- `data-k2-table="sortable"`.
- `data-k2-sort="number|text"`.
- `data-k2-default-sort` / `data-k2-default-direction` for server-rendered default state.
- `data-k2-skip-initial-sort="1"` — trust SSR row order; JS applies sort **header chrome only** (no tbody reorder on init). Use when SQL `ORDER BY` matches default sort. When URL carries non-default `k2_sort`, omit so `applyUrlSortState` can run.
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

**Scroll mirror (overflow top bar):** `js/k2-table-scroll-mirror.js` activates only when a wrapped table’s `scrollWidth > clientWidth`. Head: `includes/k2_sortable_table_assets_head.inc.php` (mirror on by default; `$k2SortableTableScrollMirror = false` to opt out). Online hub LBs: `includes/k2_lb_sortable_table_head.inc.php`. Markup: `k2_table_wrap_open(true)` / `k2_table_wrap_close()`. Wide tables (hub LBs both realms, games hub Recent/All, league period games, player games, **player Opponents W/D/L · Goals · DDs**, **Amiga player tournament history**, **Amiga player Games**, **Amiga tournament catalog index**, **Amiga live tournaments index**, Amiga WC stats/players, tournament event-stats) use mirror; compact/narrow tables (status league, highlights) do not.

**Server-sorted game tables** (`player/games.php`, `games/all.php`, **`amiga/player/games.php`**): `$k2RankedCloak` + `k2_table_ranked_sortable_class(...)` with **`ranked-table-pending`** (default); no `data-k2-table="sortable"`. `k2-table.js` reveals remaining pending tables after anchor/tooltip init; when a scroll mirror wrap is present, reveal runs after mirror init (+ `document.fonts.ready` when available) so column widths and mirror chrome settle before first paint.

**Player Opponents ledger (W/D/L · Goals · DDs):** Both realms — `$k2RankedCloak` + sortable assets on ledger views only (H2H unchanged); `player_opponents_table_sort_state()` / Amiga twin; anchor col 1 (Games drill-down); `k2-table--player-matchup` on all three tables.

**Amiga player tournament history (`/amiga/player/tournaments.php`):** `$k2RankedCloak` + sortable assets; `amiga_profile_render_tournament_history_table()` — anchor col 1 (Tournament), default sort col 0 (Date desc); SSR `k2_table_sortable_th_attr` / `k2_table_body_td_attr`; `skip-initial-sort` when default Date desc (matches SQL order); filter pill URLs carry `k2_sort`.

**Amiga tournament catalog index (`/amiga/tournaments.php`):** same cloak/assets pattern; `amiga_tournament_index_render_table()` — anchor col 1 (Tournament), default sort col 0 (Date desc); format filter pills via `amiga_tournament_index_filter_url()` carry `k2_sort`.

**Amiga live tournaments index (`/amiga/live-tournaments.php`):** cloak/assets; `amiga_live_tournament_index_render_table()` — anchor col 0 (Tournament), default sort col 1 (Date desc, matches `started_at`/`event_date` SQL order); `skip-initial-sort` on default.

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
| Player Opponents ledger (W/D/L · Goals · DDs) | 1 — Games |
| Amiga player tournament history | 1 — Tournament |
| Amiga tournament catalog index | 1 — Tournament |
| Amiga live tournaments index | 0 — Tournament |
| Amiga tournament event stats | 0 — Player |
| Amiga WC stats table | (see `AMIGA_WC_STATS_ANCHOR_COL` in include) |

Elo is **not** an anchor on Goals/DD/Streaks/Victims/Milestones wings (context column only).

All hub sortable LB rows above use **`k2-table--calm-stats`**: neutral body cells (no `.blue`/`.red`); active sort = weight 600 on primary text.

| Surface | Anchor (`data-k2-anchor-col`) | Sortable |
|---------|------------------------------|----------|
| `ranked8` period panels (day/week/month/year/all-time) | 3 — Games | Calendar grid: no; All-time panel: yes |
| `ranked8` Longevity | 4 — Days | yes |
| Status points league | 9 — Pts | no |
| Status activity league | 2 — Games | no |
| `hall-of-fame.php` records (both panels) | 1 — Value column | no |

`data-k2-anchor-col` on non-sortable tables is applied on load via `k2-table.js` `initAnchorTables()`. Legacy `individual2a/b/c` paths retired — use player Opponents wing.

---

## Compliance backlog

Run: `python scripts/audit_k2_table_compliance.py` (exit 1 while Tier C remains).

| Tier | Meaning |
|------|---------|
| **A** | Full Jun 2026 wide-table stack (helpers + SSR th/td + mirror + cloak on page/shell) |
| **B** | Acceptable legacy (parent-owned head, opponents ledger without SSR th, league period include) |
| **exception** | Documented special case (highlights compact, status league, milestones digest) |
| **C** | Migrate or document |

**Open Tier C (Jun 2026):** none — `python scripts/audit_k2_table_compliance.py` PASS.

**Tier B (acceptable):** player Opponents ledger includes (`player_opponents_tables.php`, `amiga_player_opponents_tables.php` — cloak on parent; SSR th optional), `k2_league_period_page.php`, games hub Recent day buckets.

**Hub leaderboard wings (online + Amiga):** `$k2RankedCloak` + `k2_lb_sortable_table_head.inc.php`; `k2_lb_table_sort_state()` + `k2_lb_th()` / `k2_lb_td()` SSR on all columns; `k2_table_skip_initial_sort_attr()` when SQL order matches default.

**Amiga tournament.php tables:** `amiga_tournament_render_standings_table()`, `amiga_tournament_render_games_table()`, `amiga_tournament_render_event_stats_table()` — Tier A stack in `amiga_tournament_lib.php` / `amiga_profile_blocks.php`.

When a slice clears backlog: re-run audit, update this section, checklist §1 if a new archetype shipped.

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
| 2026-06-23 | Scroll mirror + shared sortable head includes; hub LBs + games/league/player-games migrated to `k2_table_wrap_open(true)`. |
| 2026-06-23 | Opponents ledger (both realms), Amiga tournament surfaces, catalog/live indexes — cloak + SSR sort stack. |
| 2026-06-24 | Agent checklist + `scripts/audit_k2_table_compliance.py` + `.cursor/rules/k2-table-php.mdc`; games hub shell `$k2RankedCloak`. |
| 2026-06-24 | Hub LB wings Tier B→A (`k2_lb_th` / `k2_lb_td` SSR); Amiga `tournament.php` standings + games tables migrated; audit PASS (0 Tier C). |

---

## Testing Checklist

- Sortable tables: default active header, click sort, same-column asc/desc toggle, rank renumber where expected.
- Games tab: 14 sections, empty-day state, newest-first rows/default Date highlight, all-column sorting, row parity with `game.php`.
- Player Games: filter result/opponent, sort links, Previous/Next, URL reload fallback.
- Static tables: no unexpected JS errors or script requirements.

*Last pruned: Jun 2026 — checklist + compliance audit added; Amiga tournament tables documented.*
