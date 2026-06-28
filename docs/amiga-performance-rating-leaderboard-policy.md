# Amiga performance rating leaderboard — policy

**Status:** **Implemented** (Jun 2026) — design authority.  
**Parent:** [`amiga-performance-rating.md`](amiga-performance-rating.md) · [`amiga-perfect-event-policy.md`](amiga-perfect-event-policy.md) · [`amiga-time-travel-policy.md`](amiga-time-travel-policy.md) · [`amiga-player-universe-contract.md`](amiga-player-universe-contract.md) §5.2  
**Related:** [`k2-page-structure-checklist.md`](k2-page-structure-checklist.md) · [`k2-nav-implementation-checklist.md`](k2-nav-implementation-checklist.md) · [`url-routes.md`](url-routes.md) · [`k2-table-implementation-checklist.md`](k2-table-implementation-checklist.md) · [`k2-tooltip-policy.md`](k2-tooltip-policy.md)

---

## 1. Executive summary

Expand **Leaderboards → Perf. rating** from a single page into a **foldered sub-wing family** with three segment tabs. All three read **event-local** facts from `amiga_player_event_snapshots` at the time-travel cutoff (no new stored tables).

| Sub-wing | Grain | Filter | Default order |
|----------|-------|--------|----------------|
| **Best** | One row per player | Imperfect perf (`performance_rating IS NOT NULL`, `games >= 2`) | Perf DESC → event games DESC → Elo DESC → `player_id` ASC |
| **Top 100** | One row per player×event (max 100 rows) | Same imperfect filter | **Fixed set:** 100 highest perf values at cutoff; tie-break perf → games → `tournament_id` → `player_id` |
| **Perfect** | One row per perfect participation | `is_perfect_event = 1` | `event_date` DESC → `event_chrono` DESC → `tournament_id` DESC |

**Imperfect** = events where a finite performance rating exists (at least one draw or loss in the event game set). **Perfect** = undefeated events with `games >= 2` — complement of imperfect TPR; see [`amiga-perfect-event-policy.md`](amiga-perfect-event-policy.md).

Shared table columns (all sub-wings): **# · Player · Elo · Perf. rating · Event games · W · D · L · Event · Date**. W-D-L from snapshot rollup columns (`wins`, `draws`, `losses`).

**No footnotes** on v1 ship — explanatory copy lives in per-page **lede** only. **No cross-links** to honours/catalog in v1.

---

## 2. Locked product decisions

| # | Decision | Rule |
|---|----------|------|
| **PR1** | **URL shape** | Subfolder `amiga/leaderboards/performance-rating/` — **one PHP file per sub-wing** (not `?view=`). |
| **PR2** | **Files** | `best.php` (default), `top.php`, `perfect.php`; `index.php` → redirect `best.php`. Legacy `performance-rating.php` → redirect `best.php`. |
| **PR3** | **Segment nav** | Small sub-nav row **above** page lede (Pattern **B** — sibling to content; reference `lb_activity_nav.php` / Amiga WC inner tabs). Active top-level LB pill stays **Perf. rating**. |
| **PR4** | **Tab labels** | **Best** · **Top 100** · **Perfect** (short chrome labels). |
| **PR5** | **Best sub-wing** | Same semantics as today’s single table — best imperfect event per player — plus W-D-L columns. |
| **PR6** | **Top 100 sub-wing** | **Fixed leaderboard:** SQL returns exactly the **100 highest** `performance_rating` values at cutoff; table sortable **only within that row set** (standard `k2-table` behaviour). |
| **PR7** | **Top 100 tie-break** | `performance_rating DESC`, `games DESC`, `tournament_id DESC`, `player_id ASC` (when selecting the 100; matches Best wing event-pick tie-break + player_id). |
| **PR8** | **Perfect sub-wing** | All `is_perfect_event = 1` rows at cutoff — **uncapped** row count (~183 present corpus). |
| **PR9** | **Perfect perf column** | Display **∞** (Unicode U+221E) in **Perf. rating** column — not NULL/em dash. Tooltip (`data-k2-help`) explains: all-win events cannot define a finite performance rating. Optional `visually-hidden` “Perfect record” for screen readers. |
| **PR10** | **Perfect default sort** | `event_date DESC`, `event_chrono DESC`, `tournament_id DESC` (newest events first; tournament id is tie-break only — ids are not chronological in corpus). |
| **PR11** | **Time travel** | All sub-wings: events with `(event_date, event_chrono, tournament_id) <= cutoff`; Elo from latest snapshot row per player at cutoff (same as today’s Best wing). Sub-nav `href`s use `amiga_url_with_context()`. |
| **PR12** | **Tournament visibility** | `amiga_tournament_public_visibility_where()` on all three queries. |
| **PR13** | **Footnotes** | None on v1 — no “N players…” footer lines. |
| **PR14** | **Cross-links** | Self-contained v1 — no links to tournament honours **Perfect** column or catalog filter. |
| **PR15** | **Stored truth** | Read-time SQL over snapshots only — **no new DDL** for this slice. |

### Rejected alternatives

| Alternative | Why rejected |
|-------------|--------------|
| One long page with three stacked tables | Site policy: foldered modes + segment nav ([`k2-page-structure-checklist.md`](k2-page-structure-checklist.md)). |
| `?wing=` / `?tab=` for sub-modes | Navigation query params retired for modes. |
| Infer perfect from `performance_rating IS NULL` | Also matches all-loss and `< 2` games — use `is_perfect_event` only. |
| Persist “top 100” or “best per player” tables | Small corpus; snapshot scan at read time is acceptable ([`amiga-player-universe-contract.md`](amiga-player-universe-contract.md) §5.0 habit). |
| Em dash in Perfect perf column | Product chose **∞** to signal “unbounded / undefined rating” distinctly from missing data. |

---

## 3. Table contract

### 3.1 Column layout (0-based sort indices)

| Col | Header | Sort type | Notes |
|-----|--------|-----------|-------|
| 0 | # | number | `data-k2-autorank="true"` |
| 1 | Player | text | `k2_amiga_lb_player_cell()` |
| 2 | Elo | number | Rating at cutoff |
| 3 | Perf. rating | number | Best / Top 100: integer TPR. Perfect: **∞** with help tooltip |
| 4 | Event games | number | `games` on snapshot |
| 5 | W | number | `wins` |
| 6 | D | number | `draws` |
| 7 | L | number | `losses` |
| 8 | Event | text | `amiga_tournament_link()` |
| 9 | Date | number | `event_date` sort value |

Tooltips: `data-k2-help` on headers per [`k2-tooltip-policy.md`](k2-tooltip-policy.md). W-D-L headers reuse player-tournaments help text or thin wrappers in `lb_column_help.php`.

### 3.2 Default sort per sub-wing

| Sub-wing | `data-k2-default-sort` | `data-k2-default-direction` | `data-k2-anchor-col` |
|----------|------------------------|-----------------------------|----------------------|
| Best | 3 (Perf.) | desc | 0 |
| Top 100 | 3 (Perf.) | desc | 0 |
| Perfect | 9 (Date) | desc | 0 |

**Perfect:** default sort is newest-first via `event_date` / `event_chrono` on the Date column `data-k2-sort-value` (`event_chrono` primary, `tournament_id` tie-break).

### 3.3 Top 100 sortable-within-set

The PHP query **pre-filters** to 100 rows. Client-side `k2-table` may reorder those rows only — it does **not** fetch additional events when the user sorts by Date or Player.

---

## 4. Read path / SQL sketch

**Source table:** `amiga_player_event_snapshots` (+ `amiga_players`, `tournaments` for names/visibility).

**Imperfect predicate (Best + Top 100):**

```text
performance_rating IS NOT NULL
AND games >= 2
AND public_visibility(tournament)
AND event_tuple <= cutoff
```

**Perfect predicate:**

```text
is_perfect_event = 1
AND public_visibility(tournament)
AND event_tuple <= cutoff
```

**Best:** `ROW_NUMBER() OVER (PARTITION BY player_id ORDER BY performance_rating DESC, games DESC, tournament_id DESC)` → `rn = 1`.

**Top 100:** same base filter, `ORDER BY performance_rating DESC, games DESC, tournament_id DESC, player_id ASC LIMIT 100`.

**Perfect:** all matching rows; `ORDER BY event_date DESC, event_chrono DESC, tournament_id DESC` (server default; UI default sort on Date).

**Elo at cutoff:** reuse `amiga_lb_performance_rating_rows_at_cutoff()` join pattern (latest snapshot row per player ≤ cutoff).

**Lib home:** extend `amiga_player_tournament_lib.php` / `amiga_lb_snapshot_lib.php` with three functions (names TBD in implementation plan).

---

## 5. Navigation & routes

### 5.1 Paths

| Route key (proposed) | Path |
|----------------------|------|
| `amiga-lb-performance-rating` | `/amiga/leaderboards/performance-rating/best.php` |
| `amiga-lb-performance-rating-best` | `/amiga/leaderboards/performance-rating/best.php` |
| `amiga-lb-performance-rating-top` | `/amiga/leaderboards/performance-rating/top.php` |
| `amiga-lb-performance-rating-perfect` | `/amiga/leaderboards/performance-rating/perfect.php` |

Register in `k2_amiga_routes.php` + [`url-routes.md`](url-routes.md) § Amiga leaderboards.

### 5.2 Chrome stack (top → bottom)

1. Amiga hub nav (`leaderboards` active)
2. Amiga LB wing nav (`performance-rating` active)
3. **Perf. rating segment nav** (new include, e.g. `amiga_lb_performance_rating_nav.php`)
4. Page lede (`k2-hub-page-intro` or chapter lede — copy per sub-wing)
5. Sortable table

### 5.3 Include contract

```php
$k2AmigaLbPerfRatingView = 'best' | 'top' | 'perfect';
include '.../amiga_lb_performance_rating_nav.php';
```

Segment `href`s pass through `amiga_url_with_context()`.

---

## 6. Lede copy (starter — editable at implementation)

| Sub-wing | Starter lede |
|----------|----------------|
| **Best** | Best single-event performance rating per player. Events need at least two games and at least one draw or loss — perfect win records cannot define a performance rating. |
| **Top 100** | The hundred highest single-event performance ratings. A player may appear more than once. |
| **Perfect** | Every perfect tournament run: at least two games, all wins. These events have no finite performance rating (∞). |

---

## 7. Time travel

Follow [`amiga-time-travel-policy.md`](amiga-time-travel-policy.md): `amiga_lb_context($con)` on each page; snapshot event tuple cutoff on participation rows; player Elo/identity from cutoff snapshot, not `amiga_player_current` when `as=` active.

---

## 8. Non-goals (v1)

- HoF row or deep links for best perf / perfect counts
- Phase-scoped perf or group-only TPR tables
- Footnote counts under tables
- Cross-links to tournament honours or catalog perfect filter
- Pagination on Perfect (full list; ~low hundreds of rows)
- New stored aggregate tables

---

## 9. Implementation pointer

See [`amiga-performance-rating-leaderboard-implementation-plan.md`](amiga-performance-rating-leaderboard-implementation-plan.md) for slices, file list, and verification.

---

## 10. Changelog

| Date | Note |
|------|------|
| 2026-06-28 | Policy locked — three foldered sub-wings, W-D-L columns, ∞ on Perfect, Top 100 fixed set, TT-sensitive reads. |
| 2026-06-28 | **Implemented** — pages + read lib + routes; local smoke: best 428, top 100, perfect 183. |