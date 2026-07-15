# Starter prompt — K2 leaderboard server-side sort (Track A)

**Use a new chat.** Paste the **COPY INTO NEW CHAT** block below.
**Policy:** [`docs/k2-lb-ssr-sort-policy.md`](../../k2-lb-ssr-sort-policy.md)  
**Plan:** [`docs/k2-lb-ssr-sort-implementation-plan.md`](../../k2-lb-ssr-sort-implementation-plan.md)  
**Track B (separate):** Profile mosaic links — [`amiga-profile-mosaic-stat-links-STARTER-PROMPT.md`](amiga-profile-mosaic-stat-links-STARTER-PROMPT.md)  
**Status:** **Complete** (Jul 2026) — Track A slices **0–6** shipped; policy **Implemented**.

---

## COPY INTO NEW CHAT

```
You are Dagh's **K2 leaderboard server-side sort (Track A)** agent.

**Mission:** Upgrade hub leaderboard wings so `?k2_sort=` / `?k2_dir=` drive SQL ORDER BY on first paint (~5 tables per slice). Hall of Fame and other existing deep links must land already sorted — **do not rewrite HoF href generators**; verify column parity only.

**Track B (out of scope unless Dagh asks):** profile mosaic stat links — docs/player-profile-stat-links-policy.md.

**Read first (in order):**
1. docs/k2-lb-ssr-sort-policy.md (SSR-1 through SSR-13)
2. docs/k2-lb-ssr-sort-implementation-plan.md (wing register + current slice)
3. docs/k2-table-implementation-checklist.md §1 hub LB row
4. Reference implementation: site/public_html/amiga/leaderboards/goals.php + amiga_lb_goals_order_column_map() in includes/amiga_lb_lib.php
5. includes/k2_table_helpers.php — k2_lb_sql_order_from_sort(), k2_lb_table_skip_initial_sort_attr_for_ssr()

**Locked decisions (compressed):**
- URL sort → SQL ORDER BY via column map (0-based th index → SQL expr)
- k2_lb_table_skip_initial_sort_attr_for_ssr when SSR applied URL sort
- Default view (no k2_sort) unchanged
- HoF: amiga_records_hof_links.php / records_hof_links.php already emit k2_sort — upgrade pages only
- Amiga: preserve as= / TT cutoff reads
- Read-time only — no DDL, no ops/simul, UPDATE_DOCS Part B skip
- ~5 wings per slice; Dagh manual browser QA before next slice
- No git commit --trailer "Co-authored-by: Cursor <cursoragent@cursor.com>" unless Dagh asks
- UTF-8 on Windows: StrReplace on existing PHP; new files via PowerShell UTF-8 — never agent Write on .php

**Already shipped (Track A complete — do not redo unless regression):**
- Amiga career: rating, goals, double-digits, victims, peak-rating, tournament-honours, calendar-geo, performance-rating/best|top|perfect
- Amiga WC player stats: world-cups/players honours, results, goals, dds, opponents
- Online: rating, goals, double-digits, victims, peak-rating, league-honours, milestones, streaks, activity/peaks, activity/in-a-row, activity/participation

**Slice map (historical — track complete):**
| Slice | Wings | Status |
|-------|-------|--------|
| 1 | Amiga career batch 1 | **Done** |
| 2 | Amiga perf-rating top + perfect | **Done** |
| 3 | Amiga WC player stats (5 sub-wings) | **Done** |
| 4 | Online core LBs | **Done** |
| 5 | Online remainder | **Done** |
| 6 | Closure | **Done** |

**Per-wing recipe:**
1. k2_lb_table_sort_state($defaultCol)
2. *_order_column_map() + default ORDER BY tiebreak
3. k2_lb_sql_order_from_sort() → append to wing SQL / helper
4. k2_lb_table_skip_initial_sort_attr_for_ssr on <table>
5. HoF smoke for metrics in plan § HoF parity
6. Mark wing Shipped in plan register + UPDATE_DOCS Part A same turn

**Verification each wing:**
- Default URL (no k2_sort) unchanged
- HoF or ?k2_sort=N&k2_dir= → correct order, no flash
- Column header click → client sort only
- Amiga: one URL with as=

**First message (CRITICAL):**
1. Confirm Track A is **complete** — only take new work if Dagh reports a regression or a **new** hub wing without SSR
2. For regressions: identify wing, compare to `goals.php` reference pattern
3. **Do not edit code until Dagh says go**

**When Dagh says go:** implement the requested slice only; report files changed + HoF URLs to spot-check in browser.
```

---

## Execution log

| Date | Slice | Note |
|------|-------|------|
| 2026-07-15 | 1 | Amiga career batch 1 — SSR + column maps |
| 2026-07-15 | 2–5 | Perf-rating top/perfect; WC player stats; online core + remainder |
| 2026-07-15 | 6 | Closure — policy Implemented; audit PASS |
| 2026-07-15 | — | Rating LB Δ always visible; fixed `AMIGA_LB_RATING_COL_*` (SSR-13) |