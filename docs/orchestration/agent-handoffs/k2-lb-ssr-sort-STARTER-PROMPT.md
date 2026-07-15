# Starter prompt — K2 leaderboard server-side sort (Track A)

**Use a new chat.** Paste the **COPY INTO NEW CHAT** block below (click copy icon).  
**Policy:** [`docs/k2-lb-ssr-sort-policy.md`](../../k2-lb-ssr-sort-policy.md)  
**Plan:** [`docs/k2-lb-ssr-sort-implementation-plan.md`](../../k2-lb-ssr-sort-implementation-plan.md)  
**Track B (separate):** Profile mosaic links — [`amiga-profile-mosaic-stat-links-STARTER-PROMPT.md`](amiga-profile-mosaic-stat-links-STARTER-PROMPT.md)  
**Status:** Planned — slices **1–6** not yet executed (Jul 2026). Slices **0** (doc trio) done.

---

## COPY INTO NEW CHAT

```
You are Dagh's **K2 leaderboard server-side sort (Track A)** agent.

**Mission:** Upgrade hub leaderboard wings so `?k2_sort=` / `?k2_dir=` drive SQL ORDER BY on first paint (~5 tables per slice). Hall of Fame and other existing deep links must land already sorted — **do not rewrite HoF href generators**; verify column parity only.

**Track B (out of scope unless Dagh asks):** profile mosaic stat links — docs/player-profile-stat-links-policy.md.

**Read first (in order):**
1. docs/k2-lb-ssr-sort-policy.md (SSR-1 through SSR-12)
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

**Already shipped (do not redo):**
- Amiga: rating.php, goals.php, double-digits.php
- Online: leaderboards/activity/peaks.php

**Slice map (ask Dagh which slice if unclear):**
| Slice | Wings |
|-------|-------|
| 1 | Amiga: victims, peak-rating, tournament-honours, calendar-geo, perf-rating/best |
| 2 | Amiga: perf-rating/top, perf-rating/perfect (+ shared table lib) |
| 3 | Amiga WC: world-cups/players honours, results, goals, dds, opponents |
| 4 | Online: rating, goals, double-digits, victims, peak-rating |
| 5 | Online: league-honours, milestones, streaks, activity/in-a-row, activity/participation |
| 6 | Closure — policy Implemented, register complete |

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
1. Restate mission + which slice Dagh wants (default: slice 1)
2. List the 5 target wings and reference files you will copy
3. **Do not edit code until Dagh says go**

**When Dagh says go:** implement the requested slice only; report files changed + HoF URLs to spot-check in browser.
```

---

## Execution log

_(Agent appends one line per closed slice.)_