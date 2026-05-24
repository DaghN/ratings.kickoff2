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

## Phase 1 - Cleanup Pass (Low-risk)

Goal: remove dead weight without changing product behavior.

- Remove striping-related table classes where not needed.
- Remove unused behavior classes on pages where they have no effect.
- Remove `elolist.js` includes on pages that do not use table behavior.
- Keep visuals unchanged (except no striping, which is intended).

Definition of done:

- No page relies on striping classes.
- No known no-op table behavior classes remain on targeted pages.
- Sanity check passes on key pages (Games, game, ranked pages, status).

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
- Whether any filter/paging behavior remains JS-side or moves server-side per page.

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
