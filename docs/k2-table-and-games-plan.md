# K2 Tables and Games Plan

## Purpose

This document is a working guide for improving table behavior and table consistency across the Online realm, with immediate focus on:

- Games tab (`server3.php`)
- Single-game page (`game.php`)
- Leaderboards (`ranked1.php` to `ranked5.php`, `ranked7.php`)

It is intentionally written as guidelines, not an immutable spec.

## Guiding Rules (Important)

1. Treat this plan as direction, not law.
2. Every phase begins with a deep investigation of current code/state before edits.
3. At phase start, flag any reasons to deviate from this plan.
4. Prefer small, reviewable changes over big-bang rewrites.
5. Keep visual styling in CSS and behavioral interactivity in JS.

## Phase Ritual (Per Session / Per Phase)

Before implementing a phase:

- Re-read the relevant section in this doc.
- Re-audit current usage in code (classes, scripts, includes, page-specific behavior).
- Write a short kickoff note:
  - assumptions,
  - risks,
  - expected touched files,
  - any proposed deviation.

After implementing a phase:

- Write a short close note:
  - what shipped,
  - what changed vs plan,
  - follow-ups / open questions.

## Scope and Non-goals

### In scope

- Games tab 7-day table layout and behavior.
- Shared row rendering between `game.php` and Games tab.
- Table class cleanup and no-striping direction.
- A tailored `k2-table.js` direction for modern behavior.
- Leaderboard hybrid strategy (server-backed default + instant JS sorting + persistence).

### Explicitly out of scope for early phases

- Player-profile games table redesign (`individual3.php`) as part of initial package.
- Database schema changes.
- Any mandatory all-pages rewrite in one go.

`individual3.php` performance and server-side paging/sorting are a later dedicated phase.

## Architecture Direction

### Visual contract (CSS-owned)

- `k2-table-wrap` + `k2-table` remain the shared visual foundation.
- Hairlines, spacing, header treatment, and hover states stay in `theme.css`.
- No zebra striping site-wide.

### Behavioral contract (JS-owned, page-selective)

Tables opt into behavior by profile:

- static table (no JS sorting/filter/paging)
- sortable table
- sortable + autorank (leaderboards)
- sortable + page/filter (legacy transitional profile where still needed)

Avoid copy-pasting large legacy class bundles to every table by default.

## Agreed Product Decisions So Far

### Games tab (`server3.php`)

- Replace current recent-games block with 7 day buckets:
  - today first, then yesterday, through 6 days ago.
- Each day has its own header and table.
- Empty days must still render header + empty table.
- Header date formatting:
  - day 0: `Today · M j, Y`
  - day 1: `Yesterday · M j, Y`
  - day 2-6: `M j, Y`
- Games table ordering for each day: newest first.
- No client-side sorting needed for these day tables.

### Row content consistency (`game.php` + Games tab)

- Use shared PHP rendering so row content/format stays aligned.
- Adjustment cell format is already shared and should remain shared.

### Striping

- No striping desired anywhere.
- Remove striping actions/classes that provide no visual value.

### Leaderboards

- Keep instant client-side sorting behavior (no forced reload on header click).
- Move toward a hybrid model for default/persistence:
  - server/URL/cookie-backed default sort for first paint and reload/shareability,
  - JS for instant re-sort interactions,
  - persisted user choice across sessions.

## Phased Execution Plan

## Phase 0 - Planning and Baseline

Goal: lock shared direction and baseline inventory.

- Create/maintain this plan document.
- Record inventory of current table behavior by page.
- Capture baseline UX notes (sort speed, current active-state visuals, class usage).

Definition of done:

- This document exists and is accepted as active guide.
- Baseline findings captured in phase kickoff/close notes.

### Phase 0 baseline (completed 2026-05-24)

Investigation pass over `site/public_html` (code audit; no runtime timing benchmarks in this session).

#### Assets and layering

| Layer | File | Role |
|-------|------|------|
| Global CSS | `includes/k2_head.php` → `stylesheets/elolist.css` | Sortable cursor, ranked column min-width, ranked FOUC cloak |
| Global CSS | `includes/k2_head.php` → `stylesheets/theme.css` | All visible `k2-table` design (hairlines, header rule, hover, wrap card) |
| Behavior JS | `js/elolist.js` (~1,070 lines, Matt Kruse Table.js + KOOL fork) | Loaded per-page via `<script src="js/elolist.js">` — not global |

`elolist.js` does **not** provide visual striping under current theme: `table-autostripe` toggles class `alternate`, but `theme.css` gives `tr` and `tr.alternate` the **same** background.

#### Behavior profiles (target taxonomy vs today)

| Profile | Intended use | elolist classes (today) |
|---------|----------------|-------------------------|
| **static** | No client table behavior | `k2-table` only |
| **sortable** | Header click sort | `table-autosort` + `table-sortable:*` on `<th>` |
| **sortable + autorank** | Leaderboards: renumber col 0 after sort | above + `table-autorank` |
| **sortable + page** | Large lists, client page size | above + `table-autopage:N` + `<tfoot>` with `table-page:*` and `#tablepage` / `#tablepages` |
| **sortable + filter** | Column dropdown filters | above + `table-autofilter` + `table-filterable` on specific `<th>` |

Legacy bundle copied on many pages: `table-stripeclass:alternate table-autostripe table-rowshade-alternate` (striping ineffective; `table-rowshade-alternate` not referenced in `elolist.js`).

#### Page inventory

| Page / include | Loads `elolist.js` | `k2-table` | Effective elolist features | Notes |
|----------------|-------------------|------------|----------------------------|-------|
| `ranked1.php`–`ranked5.php`, `ranked7.php` | Yes | Yes | Sort, autorank, ranked cloak | PHP `ORDER BY rating DESC`; `$k2RankedCloak = true`; `table-autofilter` + rowcount classes are **no-op** (no `table-filterable`, no `#tablefiltercount` in DOM) |
| `ranked8.php` (Hall of Fame) | Yes | Yes (via `peak_period_leaderboards_section.php` ×3) | Sort, stripe (invisible) | No cloak; smaller tables (~50 rows each) |
| `server3.php` (Games tab) | Yes | Yes | Sort only (meaningful) | `table-autofilter` / rowcount **no-op**; query: 7-day window if ≥50 games else last 50 by id; adjustment cell shared via `k2_game_rating_adjustment.php` |
| `game.php` | Yes | Yes | Sort/page classes present, **pointless** (one row) | Row differs from Games tab in several columns (see gaps below) |
| `individual3.php` (player games) | Yes | Yes | Sort + page (100) + **column filters** | `SELECT *` for player — **no LIMIT**; primary **performance risk**; see **Preserve: player games column filters** below |
| `individual2a.php`, `individual2b.php`, `individual2c.php` | Yes | Yes | Sort; autofilter no-op | Player sub-stats tables |
| `server1.php` (Activity) | Yes | Yes | Mostly **no-op** | Small stats table; `table-autopage:100` + paging IDs but no sortable headers / no tfoot paging UI |
| `server2.php` (Records) | Yes | Yes | Mostly **no-op** | No `table-autosort`; page/rowcount refs unused |
| `status.php` | Yes | Yes (`k2-status-table` in include) | **None** — dead script | Static status tables; no elolist classes |
| `individual1.php` | Yes | **No** | **None** — dead script | Charts/profile only |
| `includes/peak_period_leaderboards_section.php` | Parent page | Yes | Sort if parent loads `elolist.js` | Used on `ranked8.php` |
| `includes/period_activity_leaderboards_section.php` | — | Yes | Stripe only (no `elolist.js` on parent found) | Include exists; **not referenced** by any `site/public_html/*.php` page in repo audit — tables inert for stripe without JS |
| `includes/status_room_section.php` | — | Yes | Static | Compact lobby/leaderboard snippets |

#### Ranked-page cloak

- Enabled: `ranked1`–`ranked5`, `ranked7` via `$k2RankedCloak` → `ranked_table_cloak_head.php` + `ranked-table-pending` + `html.ranked-js` CSS in `elolist.css`.
- Purpose: hide table until `Table.auto()` finishes (avoids flash before JS enhancement).
- Not used on Games, `game.php`, or `ranked8` peak tables.

#### Active sort indication (UX baseline)

- `elolist.js` adds `table-sorted-asc` / `table-sorted-desc` on active `<th>` after sort.
- **`theme.css` has no rules** for those classes — only generic `th.table-sortable:hover` (accent underline).
- No sort persistence (`localStorage` / URL / cookie) for table columns anywhere (site uses storage for realm/hub nav/accent only).

#### Leaderboards (hybrid prep notes)

- Server default: all wing pages `ORDER BY rating DESC`; rank column is PHP 1…n in that order.
- Client: instant re-sort on header click; `table-autorank` rewrites column 0 to match visible order (and page offset when paged).
- New `lb_player_filters.php` + wing toggles filter **player pool** via query string — separate from column sort state.

#### Games tab + `game.php` row parity (Phase 2 input)

Shared: `k2_game_rating_adjustment.php` for adjustment column.

Still divergent (as of baseline):

| Column / behavior | `game.php` | `server3.php` |
|-------------------|------------|---------------|
| ID | Plain int | Link to `game.php` |
| Date | Raw DB string + escaped | `M d Y, H:i` |
| Names | `htmlspecialchars` | Unescaped |
| Draw in Winner col | `Draw` | `-` |
| Win/loss test | Float tolerance | `== 1` / `== 0` |

Games data rule (pre–Phase 3): last 7 calendar days if count ≥ 50, else newest 50 games overall.

#### Performance notes (qualitative)

| Context | Expected sort UX | Why |
|---------|------------------|-----|
| Leaderboards (~hundreds of rows, simple cells) | Fast / instant | DOM size moderate; acceptable for client sort |
| `game.php` | N/A | Single row |
| `individual3.php` | **Slow for prolific players** | All games in one tbody; sort scans DOM via `getCellValue()`; paging hides rows but does not remove them from sort work |
| Peak / period tables | Fast | Small row counts |

Phase 7 (`individual3` server paging/sort) remains the real fix for large histories; faster JS alone is insufficient at thousands of rows.

#### Preserve: player games column filters (`individual3.php`)

**Product requirement: keep the functionality.** Users must be able to narrow the player games list by **Result** and **Opponent** (today via per-column dropdowns in the filter header row).

**Implementation: undecided.** We have not chosen whether filters should stay **client-side** (current elolist behavior: dropdowns built from visible cell values, rows hidden in DOM), move **server-side** (query params + `WHERE` / paginated API), or use a **hybrid**. Phase 7 kickoff should compare options against large-table performance goals; do not assume “keep elolist as-is” is the long-term answer.

**Current behavior (baseline reference)** — the **only** table on the site using elolist column filters for real:

- `table-autofilter` on the table.
- `table-filterable` on **two** header cells in the extra filter row (columns **Result** and **Opponent** — 7th and 8th data columns).
- `elolist.js` injects `<select class="table-autofilter">` per distinct cell values; choosing a value hides non-matching rows (client-side).

Also on this page (lower priority / partially dormant):

- **Paging** — `table-autopage:100`, tfoot Previous/Next, `#tablepage` / `#tablepages` (keep unless Phase 7 replaces with server paging).
- **Filtered row count** — tfoot markup for `#tablefiltercount` / `#tableallcount` exists but is **HTML-commented out**; decide in Phase 7 whether to restore or drop.

**Not the same as** leaderboard wing filters (`lb_player_filters.php`), which change the **SQL player pool**, not per-column values in an already-rendered games table.

When trimming no-op `table-autofilter` elsewhere (Phase 1), **do not** remove filter wiring from `individual3.php` until a replacement exists. When designing `k2-table.js` (Phase 4+) and `individual3` (Phase 7), treat **filter UX as required** and **client vs server implementation as TBD** — not dropped by accident during cleanup or migration.

#### Phase 0 → Phase 1 handoff (actionable)

1. Remove striping classes site-wide (`table-autostripe`, `table-stripeclass:alternate`, `table-rowshade-alternate`).
2. Remove dead `elolist.js` from `status.php`, `individual1.php`.
3. Strip no-op `table-autofilter` / `table-filtered-rowcount:*` / `table-rowcount:*` where no `table-filterable` or count elements exist.
4. Review `server1.php` / `server2.php` / `game.php` for pointless `table-autopage` / sort classes.
5. Do **not** remove `elolist.js` from leaderboards, `individual3`, or `ranked8` until `k2-table.js` migration (Phase 4+).

#### Phase 0 close

- Baseline recorded; no code changes in this phase.
- **Next recommended phase:** Phase 1 (cleanup pass), using handoff list above.

## Phase 1 - Cleanup Pass (Low-risk)

**Status: completed 2026-05-24**

Goal: remove dead weight without changing product behavior.

- Remove striping-related table classes where not needed.
- Remove unused behavior classes on pages where they have no effect.
- Remove `elolist.js` includes on pages that do not use table behavior.
- Keep visuals unchanged (except no striping, which is intended).

Definition of done:

- No page relies on striping classes.
- No known no-op table behavior classes remain on targeted pages.
- Sanity check passes on key pages (Games, game, ranked pages, status).

### Phase 1 close (2026-05-24)

**Shipped:**

- Removed striping classes from all `k2-table` markup (`table-autostripe`, `table-stripeclass:alternate`, `table-rowshade-alternate`).
- Removed dead `elolist.js` from `status.php`, `individual1.php`, `game.php`, `server1.php`, `server2.php`.
- Stripped no-op `table-autofilter` and `table-filtered-rowcount` / `table-rowcount` from pages without wired filter UI.
- Simplified static tables: `game.php`, `server1.php`, `server2.php` → `class="k2-table"` only.
- `server3.php` → `k2-table table-autosort` (sort kept until Phase 3).
- Leaderboards → `k2-table ranked-pages-table ranked-table-pending table-autosort table-autorank`.
- `individual3.php` → kept `table-autofilter`, sort, paging; removed rowcount classes (filter count tfoot still commented out).

**Unchanged on purpose:** `elolist.js` still loaded on leaderboards, `individual3`, `server3`, `individual2a/b/c`, `ranked8` (peak sort).

**Next:** Phase 2 — shared rated-game row rendering.

### Current state (post-player stat table migration, 2026-05-25)

Use this table for day-to-day reference. The Phase 0 baseline inventory above is a **historical snapshot** (pre-cleanup).

| Page / include | Loads `elolist.js` | Typical `k2-table` classes |
|----------------|-------------------|----------------------------|
| `ranked1`–`ranked5`, `ranked7` | **No** (`js/k2-table.js`) | `ranked-pages-table ranked-table-pending` + `data-k2-table="sortable"` / `data-k2-autorank="true"` / ELO default sort marker |
| `ranked8` + peak include | **No** (`js/k2-table.js`) | `data-k2-table="sortable"` per peak table; no autorank |
| `server3.php` | **No** | `k2-table` per seven day buckets |
| `individual3.php` | **No** | Server-side Result/Opponent filters, URL sort links, 100-row slices; no table JS |
| `individual2a/b/c` | **No** (`js/k2-table.js`) | `data-k2-table="sortable"` + Games default sort marker |
| `game.php`, `server1.php`, `server2.php` | **No** | `k2-table` only |
| `status.php`, `individual1.php` | **No** | `k2-status-table` or no table |
| `period_activity_leaderboards_section.php` | Only if parent adds script | `k2-table` only (include not wired to a page in repo) |

**Site-wide:** no striping classes on tables. **`theme.css`** owns appearance; **`k2-table.js`** owns simple leaderboard/player-stat sorting/autorank. **`elolist.js`** is no longer used by the migrated leaderboard/player games paths.

## Phase 2 - Shared Rated-Game Row Rendering

**Status: completed 2026-05-24**

Goal: one source of truth for row content in `game.php` and Games tab.

- Introduce/extend shared include(s) for rated-game row rendering.
- Keep output parity while normalizing content consistency.
- Ensure escaping/date/winner/adjustment decisions are deliberate and consistent.

Definition of done:

- `game.php` and `server3.php` use shared row rendering path.
- Output parity verified visually.

### Phase 2 kickoff — investigation (2026-05-24)

**Scope**

- **In:** `game.php` (single-game detail), `server3.php` (Games tab list).
- **Out:** `individual3.php` (different columns: Result, Opponent, player-centric ratings) — not part of Phase 2.
- **Future:** Phase 3 will call the same row renderer inside seven day buckets (plan for reuse now).

**Existing shared piece**

- `includes/k2_game_rating_adjustment.php` — adjustment column only; keep and call from the full-row renderer.

**Proposed include**

- `includes/k2_rated_game_row.php` with:
  - `k2_rated_game_normalize_row(array $row): array` — accept `ratedresults` **assoc** row (from `mysqli_fetch_assoc`); callers stop using numeric `$row[21]` indices in templates.
  - `k2_rated_game_winner_html(array $game): string` — winner cell (link or `Draw`).
  - `k2_rated_game_es_winner_html(array $game): string` — expected-score % cell.
  - `k2_rated_game_row_html(array $game, array $options = []): string` — full `<tr>…</tr>` (14 columns, same order as today).
  - Optional later: `k2_rated_game_thead_html()` if thead duplication becomes annoying (not required for first implementation).

**Options for `$options`**

| Key | Values | Purpose |
|-----|--------|---------|
| `id_mode` | `'link'` (default), `'plain'` | Games list links ID → `game.php`; detail page shows plain id |
| `date_format` | `'display'` (default), `'raw'` | Reserved; default applies `M d Y, H:i` |

### Phase 2 — decisions (canonical behavior)

These apply to **both** pages unless `id_mode` says otherwise. This **intentionally changes** some current `server3.php` output (called out below).

| Column / rule | Canonical choice | Was on `game.php` | Was on `server3.php` |
|---------------|------------------|-------------------|----------------------|
| **ID** | Link if `id_mode=link`, else plain int | Plain | Link |
| **Date** | `M d Y, H:i` via `strtotime`, escaped spacing as today | Raw DB string | Formatted |
| **Team A / B** | Linked names, `htmlspecialchars` | Yes | Unescaped |
| **Goals, diff, sum** | Integers as today | Same | Same |
| **Winner** | Winner link or **`Draw`** (not `-`) | `Draw` + float tolerance | `-` + `== 1` / `== 0` |
| **Win/loss/draw test** | Float tolerance on `ActualScore` | Yes | No |
| **Ratings** | `round()` to int | Yes | Yes |
| **Rating diff** | `number_format(abs(...), 1)` | Yes | Yes |
| **ES Winner %** | Winner’s ES, or `min(A,B)` on draw | Yes | Same logic, int comparison |
| **Adjustment** | `k2_game_rating_adjustment_html()` | Yes | Yes |

**Rationale:** Games tab and game detail should match; `game.php` was already stricter on escaping and draws. List view keeps ID as link for navigation.

**Data loading (implementation note)**

- `server3.php`: switch loop to `mysqli_fetch_assoc` and pass row to normalize (or normalize accepts assoc only).
- `game.php`: already assoc; pass `$row` through.

**Sorting (unchanged in Phase 2)**

- `server3.php` still uses `table-autosort` until Phase 3 removes it; formatted dates must remain parseable by elolist `table-sortable:date` (current `M d Y, H:i` pattern is OK).

**Risks / follow-ups**

- Visual diff on Games tab: draw rows show `Draw`, names escaped (no behavior change if names are safe ASCII).
- Phase 3: reuse `k2_rated_game_row_html()` per day; drop client sort on Games tables.
- Do not refactor `individual3` row markup in this phase.

### Phase 2 close (2026-05-24)

**Shipped:**

- `includes/k2_rated_game_row.php` — normalize, winner, ES %, date, id, full row; uses `k2_game_rating_adjustment.php`.
- `game.php` — `k2_rated_game_row_html($row, ['id_mode' => 'plain'])`.
- `server3.php` — `mysqli_fetch_assoc` loop + `['id_mode' => 'link']`.

**Canonical rules now live on both pages** (formatted date, escaped names, `Draw`, float tolerance).

**Next:** Phase 3 — seven day buckets on Games tab; reuse `k2_rated_game_row_html()` per table.

## Phase 3 - Games Tab 7-Day Split

**Status: completed 2026-05-25**

Goal: implement the agreed 7-table day-bucket design.

- Build seven date buckets (today to day-6).
- Render header + table per bucket.
- Render empty tables for no-game days.
- Keep day tables static (no client sorting/filter).

Definition of done:

- Games tab shows seven day sections in correct order.
- Empty day sections render correctly.
- Existing row format remains aligned with `game.php`.

### Phase 3 close (2026-05-25)

**Shipped:**

- `server3.php` now queries the current seven calendar days (`CURDATE()` through day-6), buckets rows by date, and renders Today / Yesterday / previous-day sections in newest-first order.
- Empty days render their own header and static table with a muted empty row.
- `server3.php` no longer loads `elolist.js`; Games tab day tables use `class="k2-table"` only.
- `theme.css` adds small Games-day spacing and empty-state styling.

**Unchanged on purpose:** row content still comes from `k2_rated_game_row_html()`, so `server3.php` remains aligned with `game.php`.

**Next:** Phase 4 — `k2-table.js` pilot for a leaderboard-style table, leaving `individual3.php` filters/paging on legacy behavior until a dedicated Phase 7 replacement exists.

## Phase 4 - Tailored JS Foundation (`k2-table.js`) Pilot

**Status: completed 2026-05-25**

Goal: establish a maintainable modern JS table layer.

- Create minimal tailored script for required behavior.
- Start with pilot scope (likely one leaderboard page).
- Include active sorted-column indication hooks (`aria-sort`/classes).
- Consider `data-sort-value` strategy for faster sorting.

Definition of done:

- Pilot page works with tailored JS.
- Behavior parity for pilot (sort + autorank if applicable).
- Clear decision on migration viability.

### Phase 4 kickoff — investigation (2026-05-25)

**Pilot scope:** `ranked7.php` (default Leaderboards / Results wing). It is the safest leaderboard pilot because it needs only header sorting, rank renumbering, and ranked-table cloak reveal. It does **not** use column filters or paging.

**Assumptions / risks:**

- First-click sort remains descending for new columns, matching the KOOL fork default.
- `ranked-table-pending` cloak can be shared by both `elolist.js` and `k2-table.js`.
- `individual3.php` stays on `elolist.js`; its Result / Opponent filters and paging are not part of this pilot.
- `ranked1`–`ranked5` and `ranked8` stay legacy until the pilot is reviewed.

### Phase 4 close (2026-05-25)

**Shipped:**

- Added `js/k2-table.js`, a small opt-in table script for `data-k2-table="sortable"` tables.
- Supports `data-k2-sort="number|text"`, stable sorting for new columns, same-column reversal when toggling direction, first-click descending, `aria-sort`, keyboard Enter/Space sorting, and `data-k2-sort-value` for future fast/explicit values.
- Supports `data-k2-default-sort` / `data-k2-default-direction` to light up a server-rendered default sort column without re-sorting the DOM on load.
- Supports `data-k2-autorank="true"` by renumbering the first visible column after sort.
- `ranked7.php` now loads `k2-table.js` instead of `elolist.js` and uses data attributes instead of legacy `table-sortable:*` classes.
- `theme.css` adds active sorted-column styling for `k2-table.js`; ranked cloak comments now describe table JS generically.

**Migration viability decision:** viable for simple leaderboard tables (`ranked1`–`ranked5`, likely `ranked8`) after browser review. Not yet a replacement for `individual3.php`, because filters/paging need a separate Phase 7 decision.

**Next:** Phase 5 can layer server/default/persistence behavior onto the new leaderboard script, or Phase 6 can migrate the remaining eligible non-profile tables if any.

### Phase 4 expansion — simple leaderboard migration (2026-05-25)

After browser-checking `ranked7.php`, the same `k2-table.js` profile was applied to the remaining simple leaderboard pages:

- `ranked1.php`–`ranked5.php`: sort + autorank + tab-specific default sort indicator.
- `ranked8.php` / `peak_period_leaderboards_section.php`: sort only + Games default sort indicator; existing static rank behavior preserved (no autorank).

**Default server-rendered sort by tab (agreed):**

| Page | Default sort | Rank header |
|------|--------------|-------------|
| `ranked1.php` | Peak | `#` |
| `ranked7.php` | Rating | `Rank` |
| `ranked2.php` | GF | `#` |
| `ranked3.php` | DD | `#` |
| `ranked4.php` | LWS | `#` |
| `ranked5.php` | Victims | `#` |
| `ranked8.php` | Games | `#` |

`ranked8.php` now renders all player rows for the day/month/year Hall of Fame tables (`$k2PeakPeriodLimit` default `0` = no LIMIT), instead of defaulting to 50.

Follow-up fix: same-column toggles in `k2-table.js` reverse current row order instead of re-stable-sorting, so tied Games groups on `ranked8.php` invert correctly when switching between descending and ascending.

**Still legacy on purpose:** `individual3.php`. The profile games table still owns the real filter/paging problem and remains Phase 7.

## Phase 5 - Leaderboard Hybrid Default + Persistence

Goal: implement agreed hybrid model end-to-end.

- Define whitelist of allowed sortable columns for server-side default handling.
- Add default sort state from URL/cookie/session choice (as decided in phase kickoff).
- Keep instant JS header sorting.
- Persist user sort choice across sessions.
- Ensure active column indication is consistent.

Definition of done:

- First paint default is deterministic and correct.
- Header click sorting remains instant.
- Reload and revisit behavior follows persisted choice.

## Phase 6 - Wider Migration + Legacy Reduction

**Status: simple ranked pages and `individual2a/b/c` completed 2026-05-25; profile game history still legacy.**

Goal: expand adoption and reduce dependence on legacy script.

- Migrate additional eligible pages from `elolist.js` to tailored script profile.
- Keep legacy behavior where still needed until replaced.
- Remove legacy code only when no longer referenced.

Definition of done:

- Documented list of migrated pages.
- `elolist.js` usage reduced to intentional leftovers only.

## Phase 7 - Large Table Performance Track (`individual3.php`)

Goal: fix large-list responsiveness with a dedicated approach.

**Product stance (agreed 2026-05-25):** keep the ability to narrow by **Result** and **Opponent**, but stop treating “all rows in the DOM plus client paging” as the long-term answer for prolific players.

### Phase 7A - Server-side filter/sort/limit, normal page reloads

**Status: completed 2026-05-25**

Goal: get the performance win first with the simplest robust interaction model.

- Render only the first matching slice (default: latest 100 games).
- Replace `elolist.js` dropdown filters with normal form controls:
  - Result: All / Wins / Draws / Losses.
  - Opponent: All / opponent list for this player.
- Express state in query params: `id`, `result`, `opponent`, `sort`, `dir`, optional `offset`.
- Header sort links should sort the **filtered full result set** server-side, then render the first slice.
- Do not add hidden persistence; URL state only.
- Keep a short status line such as “Showing 100 of N matching games” if count is cheap enough.
- Start without classic page 1/2/3 pagination; add “Next 100” only if missed.

### Phase 7A close (2026-05-25)

- `individual3.php` no longer loads `elolist.js`.
- The table query now renders only a 100-row slice, defaulting to latest games (`Date` desc, `id` desc tie-break).
- Result (`All/Wins/Draws/Losses`) and Opponent filters are normal GET form controls backed by SQL `WHERE` clauses; dropdown changes auto-submit, with Reset as the escape hatch.
- Header sorting is server-side via whitelisted URL params (`sort` + `dir`), with the active sorted header using the shared k2 underline/colour state.
- A compact status line reports the visible slice and total matches; `Previous 100` / `Next 100` links appear only when available.
- `theme.css` adds small controls/status styling and makes k2 header sort links inherit table-header styling.

**Deliberate trade-off:** this phase prioritizes first-paint/runtime performance and shareable URL state over in-place filtering polish. AJAX remains optional Phase 7C after browser review.

### Phase 7B - Shared row/table renderer

**Status: completed 2026-05-25**

Goal: avoid two rendering paths before any richer interaction.

- Extract `individual3` row rendering into a small include/helper.
- The normal PHP page should use this renderer.
- Any future endpoint should reuse the same renderer if it returns HTML.
- Keep perspective rules from the current `individual3.php` (watched player’s W/L, F/A, ratings, ES, adjustment).

### Phase 7B close (2026-05-25)

- Added `includes/k2_player_game_row.php` with `k2_player_game_row_html()`.
- `individual3.php` now delegates each game row to that helper instead of carrying the player-perspective row rendering inline.
- The helper keeps the Phase 7A perspective rules: watched player's result, F/A, rating columns, ES, and adjustment; positive adjustments display with an explicit `+`.
- This sets up any future AJAX endpoint to reuse the same HTML row renderer rather than duplicating table logic.

### Phase 7C - Optional AJAX enhancement

Goal: recover in-place filter/sort polish only after 7A proves the query model.

- Add an endpoint with the same query params, e.g. `api/player_games.php?id=...&result=...&opponent=...&sort=...&dir=...&limit=100&offset=0`.
- Update only the table body/status/paging controls in place.
- Preserve bookmark/share behavior by updating the URL (`history.pushState`) if AJAX is used.
- Provide loading/error states and keep the normal PHP page as fallback.

**Decision:** AJAX is on the roadmap as progressive enhancement, not the first implementation step. The core fix is server-side filtering/sorting/limiting.

Definition of done:

- Sorting/paging is responsive on large histories.
- Result and Opponent narrowing are preserved.
- Default latest-games view does not ship thousands of rows to the browser.
- If AJAX is added, normal URL reloads still work as fallback.

## Open Decisions (TBD)

- Exact persistence mechanism for leaderboard sort preference:
  - URL only,
  - URL + local/session storage,
  - cookie-backed server default,
  - hybrid combination.
- Exact active sort visual beyond the Phase 4 underline/colour pilot (icon vs no icon).
- Exact migration order for leaderboard pages after pilot.
- **`individual3` Phase 7A review:** browser-check filter/sort URLs on prolific players and decide whether the light Previous/Next 100 links are useful enough to keep.
- **`individual3` Phase 7C detail:** whether AJAX is worth adding after normal server-side filtering is proven.

## Testing Checklist (Reusable)

- Visual consistency:
  - table wrapper, borders/hairlines, spacing, hover.
- Functional consistency:
  - sorting behavior,
  - rank renumber behavior (leaderboards),
  - persistence behavior after reload.
- Games tab specifics:
  - seven day sections order,
  - empty-day rendering,
  - row format parity with `game.php`.
- Regression checks:
  - no broken scripts on pages that no longer use legacy classes.

## Change Log / Decisions Log

Use this section to track meaningful plan updates over time.

- 2026-05-24: Initial plan drafted. Framework-first approach agreed; execution details to be finalized per phase kickoff.
- 2026-05-24: Phase 0 baseline completed (page inventory, elolist vs CSS roles, row parity gaps, Phase 1 handoff).
- 2026-05-24: Document preserve requirement for `individual3.php` Result/Opponent filters (functionality required; client vs server TBD).
- 2026-05-24: Phase 1 cleanup completed (striping, dead scripts, no-op elolist classes).
- 2026-05-24: Phase 2 kickoff — shared row API sketch + canonical column decisions (game.php + server3.php).
- 2026-05-24: Phase 2 implemented — `k2_rated_game_row.php`, wired in `game.php` and `server3.php`.
- 2026-05-25: Phase 3 implemented — Games tab seven day buckets; `server3.php` removed from `elolist.js`.
- 2026-05-25: Phase 4 pilot implemented — `ranked7.php` moved from `elolist.js` to `k2-table.js` for sort + autorank.
- 2026-05-25: Simple leaderboard migration completed — `ranked1`–`ranked5`, `ranked7`, and `ranked8` now use `k2-table.js`; profile stat/history tables remain legacy.
- 2026-05-25: Player stat table migration completed — `individual2a/b/c` now use `k2-table.js`; `individual3.php` remains legacy for filters/paging.
- 2026-05-25: Phase 7 plan framed — `individual3.php` performance path is 7A server-side URL filters/sort/limit, 7B shared renderer, 7C optional AJAX enhancement.
- 2026-05-25: Phase 7A implemented — `individual3.php` uses server-side Result/Opponent filters, URL sort links, and 100-row slices; `elolist.js` removed from the page.
- 2026-05-25: Phase 7B implemented — `individual3.php` row rendering moved to `includes/k2_player_game_row.php` for reuse by any future player-games endpoint.
- 2026-05-25: `k2-table.js` same-column reverse-sort tie handling fixed after `ranked8.php` Games ascending exposed tied rank groups staying in original order.
