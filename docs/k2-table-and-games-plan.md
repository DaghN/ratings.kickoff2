# K2 Tables And Games Plan

**Status:** current table behavior contract, May 2026. Earlier phase diary has been compressed; use git history for detailed implementation logs.

**Purpose:** keep table behavior predictable while legacy table JS is replaced by small, page-specific patterns.

---

## Current State

| Page / include | Current behavior |
|----------------|------------------|
| `ranked1`-`ranked5`, `ranked7`, `ranked10`, league honours | `k2-table.js` sort + autorank; `data-k2-anchor-col` for one permanent link-star column per wing; lighter `k2-table-col-sorted` on the active sort column when it differs from the anchor. Optional deep link: `?k2_sort={col}&k2_dir=desc|asc` (Hall of Fame values via `records_hof_links.php`) — applies one client-side sort on init for `ranked-pages-table` only; does not change anchor column. |
| `ranked8` / `peak_period_leaderboards_section.php` | **Calendar** day/week/month/year: calm-stats, Games anchor (col 3), not sortable; **All time / Longevity**: calm-stats + sort + anchor (Games / Days). |
| `individual2a.php` (W/D/L) | `k2-table.js` sort; default **Games** desc; `k2-table--calm-stats` + `ranked-pages-table`; **Games** anchor (col 1, link-star); blue/red on W/L/ratios; other columns `k2-table-col-sorted` when active (Jun 2026). |
| `individual2b.php` (Goals) | Hub Goals LB parity: calm-stats (no blue/red), **Games** anchor (col 1), `lb_column_help` headers/tooltips; Win/Loss margin = `MAX` only on winning/losing games (not signed diffs on all games); Draw sort via `data-k2-sort-value`. |
| `individual2c.php` (DDs) | Hub DD LB parity (`ranked3`): headers/tooltips from `lb_column_help`, calm-stats (no blue/red), **Games** anchor (col 1); column order matches ranked3. |
| `server3.php` | Sub-nav **Recent** \| **Highlights** (`games_hub_nav.php`). **Recent:** 14 day buckets; `k2-table--calm-stats` + `ranked-pages-table` body ink; each day table uses `k2-table.js` on all columns, default **ID** desc (SQL still Date desc, id desc for fetch). **Highlights:** server-side top 100 per board (`games_highlights_helpers.php`); `k2-table--calm-stats` + `k2-games-highlights-table` (not `ranked-pages-table` — column widths); compact rows via `k2_rated_game_row` `variant=compact`; board segment + `k2-table.js` autorank on bounded table. |
| `game.php` | Static single-game table with `k2-table.js` header help only; no sorting. Below the table when a game exists: short “while we wait for browser replay” copy + 16:9 YouTube embed (2024 Online WC final placeholder). |
| `individual3.php` | Server-side Result/Opponent filters, URL sort links, 100-row slices, shared row renderer; `k2-table--calm-stats` + `k2-table--player-games` (secondary body, win/loss `.blue`/`.red` kept); PHP marks `k2-table-col-sorted` on active sort column; Result/Opponent filters use shared `k2-archive-listbox` + `k2-archive-listbox.js` (same as Status Leagues pickers, Jun 2026); Reset / Previous 100 / Next 100 use quiet action pills. |
| `status.php` | Active LB: sort + Elo anchor; league tables: calm-stats + Pts/Games anchors — **PHP** (`k2_league_table_render.php`) and **JS** (`status-period-competitions.js` inject + `k2TableApplyAnchors`). |
| `server2.php` | HoF record panels: calm-stats; Value column anchor; all values → leaderboard wings + `provisional=0` + `k2_sort`; dates keep `(New!)` / `(Legendary)` markers. |
| `server1.php`, `server2.php` | Activity summary cards plus static themed tables; no general table JS. |
| `individual1.php` | Profile/charts; no general data table behavior. |

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

`game.php`, `server3.php` (Recent), and `server3.php` Highlights share rated-game row rendering through `includes/k2_rated_game_row.php`. Highlights use `variant=compact` (no Elo/adjustment columns; optional **Peak** on one-side board).

Canonical row rules:

- ID is linked in lists and plain on the single-game page.
- Date is formatted consistently.
- Player names are escaped and linked.
- Winner cell shows winner link or `Draw`.
- ActualScore comparisons use tolerance.
- Ratings are rounded.
- `server3.php` uses sortable headers for every visible game-list column; date/Fav ES/adjustment cells provide raw sort values.
- Rating `Diff` displays rounded integer Elo difference; `Fav ES` shows expected score for the higher-rated player.
- Games-tab keeps header popups on all sortable columns; obvious labels can fall back to the shared `Click to sort.` action while abbreviation/context tooltips stay explicit. `game.php` mirrors only the useful non-sortable header help. Deep Elo explanation lives on `Fav ES` (expected-score formula/examples) and visible `Adjustment` (rating-change math with K = 32).
- Adjustment cells use the shared adjustment helper.

Do not fork Games-tab row markup unless the shared renderer is updated too.

---

## Player Games Contract

`individual3.php` is intentionally server-side now:

- Result filter: All / Wins / Draws / Losses.
- Opponent filter: All / opponent list for this player.
- Sort state in `sort` + `dir` query params; default **`id` desc** (newest games first; avoids highlighting Date on first paint).
- 100-row slices with Previous/Next links when available.
- Shared row renderer: `includes/k2_player_game_row.php`.

Product requirement: keep Result and Opponent narrowing; normal URL flow is the contract.

---

## `k2-table.js` Contract

Current supported behavior:

- `data-k2-table="sortable"`.
- `data-k2-sort="number|text"`.
- `data-k2-default-sort` / `data-k2-default-direction` for server-rendered default state.
- `data-k2-anchor-col` (0-based column index) for one permanent wing anchor styled as link-star in body cells; optional — omit on tables without an editorial hero column.
- `data-k2-autorank="true"` for first-column rank renumbering.
- `data-k2-help` / `data-k2-tooltip-label` for shared header help; help can exist without sorting, and the “Click to sort.” hint appears only on sortable headers. Avoid `data-k2-help` that only repeats the visible column label. Hub wing copy: `site/public_html/includes/lb_column_help.php` (May 2026).
- `aria-sort`, keyboard Enter/Space, and sortable header help behavior.
- Same-column toggles flip asc/desc via a full re-sort (stable tie order), not DOM reverse only.
- Deep link / filter persistence: `?k2_sort={col}&k2_dir=desc|asc` on load (`ranked-pages-table` only). Column sort updates the URL via `history.replaceState` and refreshes **Include inactive / provisional** toggle hrefs on the same wing; `k2_lb_filter_toggle_href()` merges sort params server-side. Wing tab links do **not** carry `k2_sort` (column indices differ per page).

Do not grow this into a generic table framework unless a real repeated need appears.

### Leaderboard column layout

All hub wing tables use class **`ranked-pages-table`**: uniform `8px` horizontal cell padding in `theme.css`; **no** per-column `k2-table-cell--pad-left-*` on headers (legacy header-only pads widened whole columns when labels were renamed). Optional future refactor: shared PHP column registry + responsive short/long labels via CSS, not table-by-table padding tweaks. Profile opponent tables (`individual2a/b/c`) still use pad utilities for now.

### Anchor column map (0-based `data-k2-anchor-col`)

| Table | Anchor column |
|-------|----------------|
| `ranked1.php` (Rating) | 4 — Peak (default sort; current Elo col 2 is neutral) |
| `ranked2.php` (Goals) | 4 — Scored |
| `ranked3.php` (DD) | 4 — Double Digits |
| `ranked4.php` (Streaks) | 4 — Wins |
| `ranked5.php` (Victims) | 5 — Victims |
| `ranked7.php` (Results) | 2 — Elo |
| `ranked10.php` (Milestones) | 8 — Milestones total |
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
| `server2.php` records (both panels) | 1 — Value column | no |

`data-k2-anchor-col` on non-sortable tables is applied on load via `k2-table.js` `initAnchorTables()`. Profile opponent tables (`individual2a/b/c`) unchanged.

---

## Completed Milestones

| Date | Milestone |
|------|-----------|
| 2026-05-24 | Baseline audit recorded legacy `elolist.js` usage, no-op classes, row parity gaps. |
| 2026-05-24 | Cleanup removed dead striping/no-op table classes and unused `elolist.js` includes. |
| 2026-05-24 | `game.php` and `server3.php` moved to shared rated-game row rendering. |
| 2026-05-25 | Games tab became seven static day buckets. |
| 2026-05-25 | `k2-table.js` introduced and migrated simple leaderboards plus `ranked8`. |
| 2026-05-25 | `individual2a/b/c.php` migrated to `k2-table.js`. |
| 2026-05-25 | `individual3.php` moved to server-side filters/sort/100-row slices and shared row rendering. |
| 2026-05-25 | Sortable header help tooltip and same-column reverse-sort tie handling shipped. |
| 2026-05-25 | `elolist.css` removed; ranked cloak moved into `theme.css`. |
| 2026-05-26 | Inline table layout cleanup removed legacy table `&nbsp;` / inline `text-align` spacing hacks from active ranked, player-games, game, server stats/records/activity, and Status table paths. |
| 2026-05-26 | `server3.php` Games became 14-day, fully sortable daily tables; rated-game row now exposes `GD`, integer `Elo Diff`, `Fav ES`, and sortable adjustment columns. |

---

## Testing Checklist

- Sortable tables: default active header, click sort, same-column asc/desc toggle, rank renumber where expected.
- Games tab: 14 sections, empty-day state, newest-first rows/default Date highlight, all-column sorting, row parity with `game.php`.
- Player Games: filter result/opponent, sort links, Previous/Next, URL reload fallback.
- Static tables: no unexpected JS errors or script requirements.

*Last pruned: May 2026 — current behavior separated from completed phase diary.*
