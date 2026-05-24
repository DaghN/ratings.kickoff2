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

### Current state (post-Phase 1, 2026-05-24)

Use this table for day-to-day reference. The Phase 0 baseline inventory above is a **historical snapshot** (pre-cleanup).

| Page / include | Loads `elolist.js` | Typical `k2-table` classes |
|----------------|-------------------|----------------------------|
| `ranked1`–`ranked5`, `ranked7` | Yes | `ranked-pages-table ranked-table-pending table-autosort table-autorank` |
| `ranked8` + peak include | Yes | `table-autosort` (per peak table) |
| `server3.php` | Yes | `table-autosort` |
| `individual3.php` | Yes | `table-autosort table-autofilter table-autopage:100` + paging tfoot |
| `individual2a/b/c` | Yes | `table-autosort` |
| `game.php`, `server1.php`, `server2.php` | **No** | `k2-table` only |
| `status.php`, `individual1.php` | **No** | `k2-status-table` or no table |
| `period_activity_leaderboards_section.php` | Only if parent adds script | `k2-table` only (include not wired to a page in repo) |

**Site-wide:** no striping classes on tables. **`theme.css`** owns appearance; **`elolist.css`** + **`elolist.js`** own sort/cloak/filter behavior where loaded.

## Phase 2 - Shared Rated-Game Row Rendering

Goal: one source of truth for row content in `game.php` and Games tab.

- Introduce/extend shared include(s) for rated-game row rendering.
- Keep output parity while normalizing content consistency.
- Ensure escaping/date/winner/adjustment decisions are deliberate and consistent.

Definition of done:

- `game.php` and `server3.php` use shared row rendering path.
- Output parity verified visually.

## Phase 3 - Games Tab 7-Day Split

Goal: implement the agreed 7-table day-bucket design.

- Build seven date buckets (today to day-6).
- Render header + table per bucket.
- Render empty tables for no-game days.
- Keep day tables static (no client sorting/filter).

Definition of done:

- Games tab shows seven day sections in correct order.
- Empty day sections render correctly.
- Existing row format remains aligned with `game.php`.

## Phase 4 - Tailored JS Foundation (`k2-table.js`) Pilot

Goal: establish a maintainable modern JS table layer.

- Create minimal tailored script for required behavior.
- Start with pilot scope (likely one leaderboard page).
- Include active sorted-column indication hooks (`aria-sort`/classes).
- Consider `data-sort-value` strategy for faster sorting.

Definition of done:

- Pilot page works with tailored JS.
- Behavior parity for pilot (sort + autorank if applicable).
- Clear decision on migration viability.

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

Goal: expand adoption and reduce dependence on legacy script.

- Migrate additional eligible pages from `elolist.js` to tailored script profile.
- Keep legacy behavior where still needed until replaced.
- Remove legacy code only when no longer referenced.

Definition of done:

- Documented list of migrated pages.
- `elolist.js` usage reduced to intentional leftovers only.

## Phase 7 - Large Table Performance Track (`individual3.php`) [Later]

Goal: fix large-list responsiveness with a dedicated approach.

- Investigate server-side paging/sorting approach.
- Decide query + UX model for large player game history.
- **Retain column filter capability** (Result / Opponent narrowing) — UX required; **client vs server implementation TBD** — see baseline **Preserve: player games column filters**.
- Implement and validate performance improvements.

Definition of done:

- Sorting/paging is responsive on large histories.
- Approach documented separately as needed.

## Open Decisions (TBD)

- Exact persistence mechanism for leaderboard sort preference:
  - URL only,
  - URL + local/session storage,
  - cookie-backed server default,
  - hybrid combination.
- Exact active sort visual design (icon, underline, color treatment).
- Exact migration order for leaderboard pages after pilot.
- **`individual3` column filters:** client-side (elolist-style) vs server-side (query/API) vs hybrid — functionality required, approach open.
- Whether paging on `individual3` remains JS-side or moves server-side (likely server for performance).

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
