# K2 table implementation checklist

**For agents (mandatory before adding or refactoring any sortable/wide table).**  
Full behaviour contract and page inventory: [`k2-table-and-games-plan.md`](k2-table-and-games-plan.md). API: `site/public_html/includes/k2_table_helpers.php`.

**Do not invent a one-off table stack.** Find the closest **reference implementation** below, read that file, then copy the pattern.

---

## 1) Pick a reference (read one file first)

| Scenario | Reference include / page | Notes |
|----------|-------------------------|--------|
| Hub leaderboard wing (Rank + Player cols) | `includes/k2_lb_sortable_table_head.inc.php` + any `leaderboards/rating.php` | `$k2RankedCloak`; `k2_lb_table_sort_state()` + `k2_lb_th()` / `k2_lb_td()` SSR |
| Hub LB body | `leaderboards/rating.php` table markup | `k2_table_ranked_leaderboard_class()`; all columns via `k2_lb_th` / `k2_lb_td` |
| Wide sortable + filter pills (full page reload) | `amiga/tournaments.php` + `amiga_tournament_index_render_table()` in `amiga_profile_blocks.php` | Filter URLs merge `k2_table_sort_query_params()` |
| Player wing ledger (W/D/L · Goals · DDs) | `includes/player_opponents_tables.php` + `player_opponents_page.php` | Ledger-only cloak/assets; H2H unchanged |
| Player games (server sort + filters + pager) | `player/games.php` + `includes/k2_player_game_row.php` | **Server** sort — not `k2-table.js` sort |
| Realm games vault | `games/all.php` + `includes/k2_realm_games_all.php` | Server sort + 250-row pager |
| Games hub Recent (client sort, day buckets) | `games/recent.php` | Sortable + calm-stats |
| Tournament event stats (wide, SSR order = SQL) | `amiga_tournament_render_event_stats_table()` in `amiga_profile_blocks.php` | `data-k2-skip-initial-sort="1"` when SSR order matches default |
| Tournament standings + games (Amiga) | `amiga_tournament_render_standings_table()` / `amiga_tournament_render_games_table()` in `amiga_tournament_lib.php` | Page cloak on `amiga/tournament.php`; dynamic anchor col on games table |
| Amiga WC stats / players LB | `includes/amiga_world_cup_stats_table.php`, `includes/amiga_wc_players_table.php` | Shell: `amiga_wc_*_lb_shell_start.inc.php` |
| Amiga WC hub events catalog | `includes/amiga_world_cups_events_table.php` + `amiga/world-cups/index.php` | Tournaments-index columns minus Format; medal SVG headers; podium = flag + player link |
| League period games | `includes/k2_league_period_page.php` | Mirror + sortable |
| Static / header-help only | `game.php` | No sortable bundle |
| Status league (compact, no mirror) | `includes/k2_league_table_render.php` | Calm-stats; not hub LB |

If unsure which row applies: **grep** `k2_table_ranked_sortable_class` and `k2RankedCloak` in `site/public_html/` and open the nearest neighbour.

---

## 2) Sortable wide table — required stack (Jun 2026)

Use for any `data-k2-table="sortable"` table that users sort or that reloads with filters (flash = missing cloak + SSR).

### `<head>` (page or shell include)

```php
<?php $k2RankedCloak = true; include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_head.php'; ?>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_sortable_table_assets_head.inc.php'; ?>
```

Online hub LB wings may use `k2_lb_sortable_table_head.inc.php` instead of the generic assets include.

**Anti-pattern:** `k2_table_js_enqueue()` alone on a full sortable page — no cloak, no scroll mirror.

### PHP render function (preferred over inline markup in `.php` pages)

1. Constants: `*_ANCHOR_COL`, `*_DEFAULT_SORT_COL` (0-based).
2. `$defaultSortCol = k2_table_default_sort_col_from_request(...)`; same for dir.
3. `$tableClass = k2_table_ranked_sortable_class('k2-table--your-modifier')` (hub LBs: `k2_table_ranked_leaderboard_class()`).
4. `k2_table_wrap_open(true)` / `k2_table_wrap_close()` when table may overflow horizontally.
5. `<?php $lbSort = k2_lb_table_sort_state($defaultSortCol); ?>` immediately before `<table>` (hub LBs — required for `k2_lb_th` / `k2_lb_td` and table `data-k2-*` attrs).
6. Every `<th>`: hub LBs use `k2_lb_th($col, $lbSort, $extraClass)`; other wide tables use `k2_table_sortable_th_attr(...)`.
7. Every body `<td>`: hub LBs use `k2_lb_td($col, $lbSort, $extraClass)`; other wide tables use `k2_table_body_td_attr(...)`.
8. `data-k2-skip-initial-sort="1"` when **SQL row order already matches** default sort (avoids reorder flash). **Never** set skip when PHP row order uses a different `ORDER BY` than the wing’s default column (WC player sub-wings: per-view order in `amiga_lb_wc_slice_order_sql()`).
9. Filter / pill / chevron URLs: `array_merge($params, k2_table_sort_query_params())` or `k2_table_merge_sort_query_for_path()`.

### Anchor column

Exactly one editorial link column per table (`data-k2-anchor-col`). Map: [`k2-table-and-games-plan.md` § Anchor column map](k2-table-and-games-plan.md).

---

## 3) Before shipping — self-check

- [ ] Read reference file(s) from §1 (not only this checklist).
- [ ] Head uses cloak + sortable assets (or hub LB head include).
- [ ] Table class from `k2_table_ranked_sortable_class()` / `k2_table_ranked_leaderboard_class()` — includes `ranked-pages-table` + `ranked-table-pending`.
- [ ] SSR sort header + anchor/sorted body classes on first paint.
- [ ] Default sort col/dir matches SQL `ORDER BY` when using `skip-initial-sort`.
- [ ] Navigation that reloads the page preserves `k2_sort` / `k2_dir` where sortable.
- [ ] Hard refresh + filter/tab click: **no visible sort flash**.
- [ ] Run `python scripts/audit_k2_table_compliance.py` — no new Tier C files.
- [ ] Update [`k2-table-and-games-plan.md`](k2-table-and-games-plan.md) + `PROJECT_MEMORY.md` (Part A of [`UPDATE_DOCS.md`](UPDATE_DOCS.md)).

---

## 4) When *not* to use this stack

| Case | Pattern |
|------|---------|
| Server-paginated game lists | URL `sort`/`dir` + PHP `k2-table-col-sorted`; not client re-sort on full dataset |
| Compact status league tables | Calm-stats; no scroll mirror |
| Static single-game row | `k2-table` + optional header help only |
| Highlights compact boards | `games/highlights.php` — compact variant |

---

*Last updated: Jun 2026 — institutional checklist; keep in sync when a new table archetype ships.*
