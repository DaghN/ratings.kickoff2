# K2 Tables And Games Plan

**Status:** current table behavior contract, May 2026. Earlier phase diary has been compressed; use git history for detailed implementation logs.

**Purpose:** keep table behavior predictable while legacy table JS is replaced by small, page-specific patterns.

---

## Current State

| Page / include | Current behavior |
|----------------|------------------|
| `ranked1`-`ranked5`, `ranked7` | `k2-table.js` sort + autorank; server-rendered default sort indicator per tab. |
| `ranked8` / `peak_period_leaderboards_section.php` | Activity tab has **Calendar** day/week/month/year tables capped at 20, 2×2 on desktop, non-sortable but with header help + static Games-desc indicator; All time / Longevity remain sortable with raw date sort values. |
| `individual2a/b/c.php` | `k2-table.js` sort; Games default sort indicator. |
| `server3.php` | 14 day buckets; each day table uses `k2-table.js` on all columns, default Date desc matching SQL order. |
| `game.php` | Static single-game table with `k2-table.js` header help only; no sorting. |
| `individual3.php` | Server-side Result/Opponent filters, URL sort links, 100-row slices, shared row renderer; no table JS. |
| `status.php` | Active leaderboard uses `k2-table.js` sort + autorank; league/status mini tables remain static. |
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

`game.php` and `server3.php` share rated-game row rendering through `includes/k2_rated_game_row.php`.

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
- Sort state in `sort` + `dir` query params.
- 100-row slices with Previous/Next links when available.
- Shared row renderer: `includes/k2_player_game_row.php`.

Product requirement: keep Result and Opponent narrowing; normal URL flow is the contract.

---

## `k2-table.js` Contract

Current supported behavior:

- `data-k2-table="sortable"`.
- `data-k2-sort="number|text"`.
- `data-k2-default-sort` / `data-k2-default-direction` for server-rendered default state.
- `data-k2-autorank="true"` for first-column rank renumbering.
- `data-k2-help` / `data-k2-tooltip-label` for shared header help; help can exist without sorting, and the “Click to sort.” hint appears only on sortable headers. Avoid `data-k2-help` that only repeats the visible column label.
- `aria-sort`, keyboard Enter/Space, and sortable header help behavior.
- Same-column toggles reverse current row order so tied groups invert correctly.

Do not grow this into a generic table framework unless a real repeated need appears.

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

- Sortable tables: default active header, click sort, same-column reverse, rank renumber where expected.
- Games tab: 14 sections, empty-day state, newest-first rows/default Date highlight, all-column sorting, row parity with `game.php`.
- Player Games: filter result/opponent, sort links, Previous/Next, URL reload fallback.
- Static tables: no unexpected JS errors or script requirements.

*Last pruned: May 2026 — current behavior separated from completed phase diary.*
