# Amiga TT query optimization playbook

**Status:** Active method doc (2026-07-04) · **Audience:** agents doing TT performance slices in the Amiga realm
**Evidence base:** F6 rating LB + three slow wings + World Cups hub fixes — see
[`tt-chrome-baseline-f6-attempt-log.md`](orchestration/tt-chrome-baseline-f6-attempt-log.md) (Phase 0 audit, Attempts 4-5, "Three slow LB wings", "World Cups hub") and handoff [`2026-07-04-003`](orchestration/agent-handoffs/2026-07-04-003-f6-rating-lb-tt-nav-flawless.md).

---

## 1. Why TT pages "vanish" — the budgets that matter

A slow TT query is not just "slow" — it breaks navigation chrome. Three hard numbers:

| Budget | Value | What happens past it |
|--------|-------|----------------------|
| TTFB | ~60 ms | Header + TT ribbon bytes stream immediately; everything below waits on the blocking queries |
| Chrome paint holding (Chrome/Edge) | ~500 ms | The y=0 chrome gate holds the **old page** on screen only this long; past it the user sees theme fill |
| `MAX_CLOAK_MS` (`k2_carry_scroll_restore.php`) | 700 ms | Both the y=0 TT chrome gate and the y>0 carry cloak **time out and reveal onto a half-empty page** — the "complete vanish, then slow draw" symptom |

**Target: keep the blocking segment (header context -> hub chapter emit) under ~200-250 ms at the worst cutoff.** Once every wing query is tens of ms, the carry/cloak machinery works as designed and nothing visibly vanishes.

A page that "feels broken, maybe memory" is almost always MySQL materializing a wide temp table server-side (see anti-pattern A below). Check PHP `memory_get_peak_usage()` in a probe before suspecting PHP — in every case so far it was 4-6 MB, i.e. fine.

---

## 2. Method (do these in order)

1. **Probe, don't guess.** Write a oneoff PHP CLI probe (`scripts/oneoff/amiga_*_tt_probe.php`) that calls the page's actual lib functions per cutoff and prints per-call ms. Template: `scripts/oneoff/amiga_lb_slow_wings_tt_probe.php`. Essentials:
   - PHP CLI path: `C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe`
   - Set `$_GET['as']` then `amiga_snapshot_context_reset()` + `amiga_snapshot_context_from_request($con)` per scenario
   - Test **early and late cutoffs**: `year:2001` / `month:2002-06` (small), `month:2014-07`, `event:589` / `month:2025-09` / `year:2024` (late = worst, most rows <= cutoff). Beware `event:22` = Athens XCI **2025** (late despite low id — fractional-chrono import).
2. **Find the shape.** Grep the lib for the slow function's SQL. The offenders so far all match one of the anti-patterns in §3.
3. **Fix with the matching pattern** (§3). Prefer reusing `amiga_lb_snapshot_from_sql()` over writing a new copy.
4. **Parity oracle — non-negotiable.** Keep the old SQL in a oneoff probe, run old vs new across 5-7 cutoffs (include a no-history early cutoff and a late one), compare **full row sets** e.g. `json_encode` of stringified rows. Template: `scripts/oneoff/amiga_lb_peak_rating_parity_probe.php`. Byte-identical or explain why not.
5. **Curl sweep** the real pages (TT + present): status 200, total time, `<tr` count, no `Warning:|Fatal error|Deprecated:` in body.
6. **Docs Part A** same turn (attempt log entry if TT-chrome-related, MEMORY line, handoff note).

---

## 3. Fix patterns (ranked by impact so far)

### A. Wide-row ROW_NUMBER window -> narrow window + PK join-back

**Smell:** `ROW_NUMBER() OVER (PARTITION BY ...)` selecting `snap.*` / `s.*` / `x.*` from a **wide** table (`amiga_player_event_snapshots` = 174 cols) to get "latest row per player <= cutoff". MySQL materializes *every* column of *every* row <= cutoff into a temp table before filtering `rn = 1`. Cost: 0.5-2 s per call; scales with cutoff lateness.

**Fix:** window over the key columns only, then join back to the wide row by PRIMARY KEY. Canonical helper (use it, don't copy it):

```
amiga_lb_snapshot_from_sql('s')   -- includes/amiga_lb_snapshot_lib.php
```

Gives `FROM amiga_players p INNER JOIN (narrow rn=1 scan) s_latest INNER JOIN amiga_player_event_snapshots s ON PK`. Callers keep the `s.` alias and `'sdi'` bind params — most rewrites are: delete the inline subquery, splice in the helper, move `WHERE ... > 0` filters from the inner `x.` to outer `s.`, aliases in ORDER BY from `t.` to `s.`/`p.`. Result: ~40-70 ms. Examples: `amiga_lb_query_career`, `amiga_lb_honours_rows_at_cutoff`, `amiga_lb_calendar_geo_rows_at_cutoff` (10-30x each).

### B. Dense per-finalize table -> event-equality read (even better)

**Smell:** same latest-per-player window, but over a table that writes **one row per debuted entity at every finalize** — e.g. `amiga_player_elo_rank_at_event` (173k rows, 7 cols). Then "latest row per player <= cutoff" is *by construction* identical to `WHERE tournament_id = <cutoff tournament id>`. No window at all.

**Verify density first** (one-time, per table): rows-per-event == cumulative debuted entities at that event, for **all** events. For the er table: 173,004 rows == sum of per-event debut counts across all 605 events (see attempt log § Three slow LB wings). If a table is sparse (participation-only snapshots like `amiga_player_event_snapshots`), pattern B is **wrong** — use A.

Numbers: old window 1.7-3.4 s; narrow+join-back (pattern A) still 0.9-2.3 s because the window itself is big; **dense equality 10-15 ms**. Example: `amiga_lb_query_peak_rating()` er-join.

### C. Missing metric-first index on big fact tables

**Smell:** "latest fact <= cutoff" lookups on a large composite-PK fact table where every index starts with `tournament_id` — the `tournament_id <= ?` range makes all later PK columns unusable, so metric filters full-scan. Example: `amiga_community_stat_facts` (446k rows) in `amiga_community_year_realm_games_at_cutoff()` — 2,581 ms.

**Fix:** covering index with the equality columns first and `tournament_id` **last**: `idx_community_facts_metric_period (period_type, slice_type, slice_key, metric_key, count_basis, period_key, tournament_id)` -> 48 ms. DDL goes in `scripts/amiga/sql/<nnn>_*.sql` **and** the `sql/derived/` mirror; ALTER the local `ko2amiga_db` directly; staging inherits via the next `export_ko2amiga_db.ps1` export.

### D. Same query run twice per request -> request-scoped static cache

**Smell:** hub shell (chapter counts) and page body compute the same rows/count on **separate connections**, or a footer count re-runs the page's main query. Examples: `amiga_world_cup_stats_rows()` (shell + body), `amiga_lb_games_count()` (footer + lede), `amiga_lb_honours_rows_at_cutoff()` (rows + count).

**Fix:** `static $cache = []` keyed by the cutoff tuple (`event_date|chrono|tournament_id`, `'present'` when inactive). Data-only caching is connection-independent, so it works across the shell/body connection split. Make count helpers `count()` the cached rows.

### E. Do not recompute what the page query already produced

**Smell:** a secondary decoration (delta map, baseline map) internally re-runs the full ladder resolve the page just did. Fix: narrow purpose-built map helpers (e.g. `amiga_rating_history_rating_map_at_cutoff()`), and derive prev-step keys from the context's unfiltered catalog (careful: `as_with` filters `prevKey()` semantics — see attempt log Attempt 4).

---

## 4. Where to hunt next (inventory 2026-07-04)

`ROW_NUMBER() OVER` sites in `site/public_html/includes/` (grep it fresh — this list ages):

| Site | Table / shape | Likely severity |
|------|---------------|-----------------|
| `amiga_community_histogram_lib.php` | `raw_world_cups_played` still uses inline slice window join | Low unless Activity histogram API flagged |
| `amiga_matchup_snapshot_lib.php`, `amiga_player_snapshot_lib.php`, `amiga_player_rank_history_lib.php`, `amiga_elo_rank_lib.php`, `amiga_player_h2h_pair_lib.php` | windows filtered by `player_id = ?` (single player / pair) | Cheap; leave unless probed slow |
| **F20 Countries rivals H2H + wdl/goals/dds** | ~~double `rivals_rows` + wide matchup window + perf batch on all table wings~~ | **Fixed 2026-07-04** — cache + narrow window + scoped perf; Goals/DDs skip perf batch; H2H pair games memo shared with perf + chart payloads |
| **`amiga/player/games.php` (busy player)** | DB **~15 ms** after player-scoped scan; curl **~1.4 s** = ~1500-row HTML + filter chrome, not query vanish | Do not re-window; next lever is render/pagination (product) |
| **`amiga/games/all.php` present-only** | score-line facets still one game scan (~190 ms) when no TT cutoff | TT cutoffs use catalog path (~235 ms total probe) |
| `peak_month_leaderboard_query.php`, `lb_activity_lib.php`, `league_standings.php`, `player_milestones_helpers.php` | **online realm** | Out of Amiga TT scope |

**Fixed this sweep (do not redo):** WC player/country slice narrow window + cache; countries `attach_elo_ranks` dense equality; games all/recent lean paths; player games inner scan; **F20 country rivals** (narrow matchup window, request cache, H2H dedupe, TT perf batch country-col fix).

---

## 5. Guardrails

- **Parity before ship, always.** A fast wrong ladder is worse than a slow right one. Full row-set compare, not spot checks.
- **Verify density before pattern B.** Sparse tables (participation-only) will silently drop players if you use event equality.
- **Keep aliases and bind signatures stable** when splicing in `amiga_lb_snapshot_from_sql()` — callers and `'sdi'` params should not need changes (peak-rating's `'sdii'` was the exception: er window params replaced by one tournament id).
- **Do not add new `SELECT snap.*` window copies.** If you need latest-at-cutoff career rows, use the shared helper; if you find another inline copy, that is the bug.
- **Index DDL** = `scripts/amiga/sql/` + `sql/derived/` mirror + local ALTER; never edit prod/staging directly (staging = export + browser import per [`amiga-staging-handoff.md`](amiga-staging-handoff.md)).
- **Probes stay in `scripts/oneoff/`** with `amiga_` prefixes; they are the parity oracles for the next agent.
- The chrome-side machinery (y=0 chrome gate, carry cloak, `$k2RankedCloak`) is **not** the knob to turn for slow queries — fix the query. Chrome policy: [`amiga-tt-chrome-sticky-invariants.md`](amiga-tt-chrome-sticky-invariants.md) + attempt log.

---

## 6. Results achieved with these patterns (for calibration)

| Surface | Before (worst TT) | After | Patterns |
|---------|-------------------|-------|----------|
| Rating LB career query | 503-2,020 ms | 34-49 ms | A |
| Rating LB blocking segment | 710-906 ms | 165-230 ms | A + D + E |
| Tournament honours (rows + count) | 586 ms x2 | 43 ms x1 | A + D |
| Calendar-geo | 1,072 ms | 41 ms | A |
| Peak rating | 2,248-3,437 ms | 71-120 ms | A + B |
| WC share-of-year lookup | 2,581 ms | 48 ms | C |
| WC chronology page (curl) | ~5 s | ~0.5 s | C + D |
| WC player slice rows @ cutoff | ~80-250 ms (wide window) | ~40-90 ms | A + D |
| WC country slice rows @ cutoff | ~80-120 ms (wide window) | ~40-80 ms | A + D |
| Countries roster elo attach | window on er table | equality on `tournament_id` | B |
| Games All @ TT cutoff (lib probe) | ~1800 ms | ~235 ms | lean scan + catalog stats + single-pass facets |
| Games All curl @ `year:2024` | ~2.0 s | ~0.08 s | above |
| Games Recent curl @ `year:2024` | ~1.0 s | ~0.6 s | lean tournament fetch |
| Player games query (id=382) | full-realm subquery scan | ~15 ms | player-scoped inner scan |