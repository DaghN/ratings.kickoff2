# F6 — Rating LB flawless TT navigation (handoff · iter 3d+)

**Date:** 2026-07-04  
**Track:** Amiga TT chrome baseline · carry-scroll · F6  
**Failure targeted:** **F6** (sub-ribbon blank at scroll top) · **F18** (whole-page blank on slow hub hops at y>0) · related mid-scroll regressions  
**Status:** **Ready for new agent** — audit-first, then design fix  
**Prior chat transcript:** Cursor agent transcript `108487a8-411e-42bd-8b0c-28274d176aa8` (search `F6`, `3b`, `3a`, `flush`, `Type B`)

---

## Starter prompt (paste into a new Agent chat)

```
Today: F6 — flawless TT navigation on Amiga Rating LB only (narrow scope).

Read first (cold start):
1. PROJECT_MEMORY.md → AGENTS.md → docs/PROJECT_MAP.md
2. This handoff: docs/orchestration/agent-handoffs/2026-07-04-003-f6-rating-lb-tt-nav-flawless.md
3. docs/amiga-tt-chrome-sticky-invariants.md (F6, F18, smokes S1/S1b)
4. docs/orchestration/tt-chrome-baseline-f6-attempt-log.md (Attempts 1–3, 3b reverted)
5. docs/amiga-time-travel-policy.md §3 + docs/with-player-stepper-policy.md (URL carry)

Narrow test URL (Dagh anchor):
http://ratingskickoff.test/amiga/leaderboards/rating.php?as=month%3A2014-07

Success bar (non-negotiable — Dagh):
- TT navigation on this page must be **flawless**: hub chapter, hub tabs, wing tabs, TT ribbon, filters/anchors — everything **below site header + TT ribbon stack** stays visually stable and updates in place.
- **Only the ranked table body** may disappear/replace (Type C `$k2RankedCloak` is OK).
- **No** sub-ribbon vanish (void below ribbon).
- **No** whole-page vanish (Type A body cloak visible as empty viewport).
- Dagh has had this state before — treat as achievable, not “good enough”.

Phase 0 (this session — before coding a fix):
1. **Audit TT queries** for rating LB hot path: header snapshot chrome, hub nav lede, `amiga_lb_query_career`, delta maps, early vs late cutoff, event/month/year wings. Build timings table (local `ko2amiga_db`). Extend or add `scripts/oneoff/amiga_rating_lb_tt_audit_probe.php` if useful.
2. **Analyze all prior work** in attempt log + slice-0 handoff — iter 1, iter 2 (reverted), iter 3a (partial), iter 3b (failed + reverted `989054c`).
3. **Think deeply** — classify each repro axis (y=0 vs y>0, wing, nav type, early vs late cutoff) as Type A / B / C per attempt log framework.
4. **Propose a fix plan** (likely iter 3d carry reveal gate + query speed + possibly shell-stable chrome — **not** naive PHP flush). Get Dagh alignment before large code.

Docs: follow docs/UPDATE_DOCS.md Part A when you ship a slice (same turn as code). Attempt log + handoff update for audit; invariants only if symptoms change.

Do NOT: remove carry-scroll on realm/hub pills; re-ship iter 3b PHP flush without new evidence; expand scope beyond rating LB until this page passes all S1 matrix cells.
```

---

## Goal (product)

Make **Amiga Leaderboards → Rating** work flawlessly in **time travel** across all navigation variables. Dagh’s bar is **not** “a bit better” — it is **only the table** that may blink/replace; all other chrome below the TT ribbon must feel like an in-place update.

**Anchor URL:**

`http://ratingskickoff.test/amiga/leaderboards/rating.php?as=month%3A2014-07`

Also exercise: early cutoff (`as=event:22`), late cutoff (`as=event:589`, `as=month:2025-09`), present day (no `as=`).

---

## Test matrix (rating LB only — prove all before widening)

| Variable | Values to test |
|----------|----------------|
| **Scroll Y** | `y ≈ 0` (primary F6 surface) · `y > 0` (mid-scroll — must not regress) |
| **TT wing** | Event · Month · Year (on rating LB URL) |
| **Cutoff** | Early (few games) · late (heavy query) |
| **Nav type** | TT ribbon chevrons · period picker · wing tabs Event/Month/Year · hub bar (Leaderboards ↔ Countries ↔ WC) · LB wing tabs (Rating ↔ Goals ↔ …) |
| **Mode** | TT (`as=` present) · Present (no `as=`) control |

**Smokes:** S1, S1b from [`amiga-tt-chrome-sticky-invariants.md`](../amiga-tt-chrome-sticky-invariants.md). Extend matrix above for sign-off.

---

## Success criteria (locked)

| OK | Not OK |
|----|--------|
| Table rows swap / brief ranked-table cloak | Hub chapter, hub tabs, wing tabs, TT stepper/picker vanish |
| TT ribbon + stamp stable (known F19 LED may co-wait with table — separate) | Whole viewport empty (Type A) |
| In-place feel — chrome updates without old→void→new | “Mostly good” on some nav types only |

---

## Context — everything done in prior chat (2026-07-04)

### Baseline

- **C02 TT ribbon pin removed** — in-flow baseline only (`amiga_snapshot_chrome.php`, `theme.css`; no `k2-amiga-time-travel-pin.js`).
- Failures registered: **F6**, **F18**, **F19**, **F20** in invariants doc.

### Attempt 1 (iter 1, `cec928f`) — partial

- Skip body cloak when `y=0` without anchor; `carrySubRibbonReady()` for hub chapter.
- Chevrons OK when query fast; **pickers bad**; month wing worse.

### Attempt 2 (iter 2, `1d875ed`) — reverted

- `k2-carry-cloak-top` — hide all body except header/stamp/ribbon until reveal.
- **Worse:** every TT nav blanked sub-ribbon; **realm switch regression** (Online body hidden).
- **Lesson:** y=0 cloak fights streaming; do not hide sub-ribbon intentionally.

### Attempt 3a (y=0 noop destination, `5862cde`) — partial, **keep**

- Destination: if `payload.y === 0` && no hash → clear payload, skip cloak (normal load at top).
- **Result:** non-TT / realm **good**; TT y=0 still bad (Type B); TT y>0 mostly OK except slow hub hops (Countries F18).

### Attempt 3b (PHP flush) — **failed, reverted `989054c`**

- Moved LB wing nav before heavy query + `flush()` on 7 wings + Countries.
- **Ship bug:** infinite `ob_flush()` loop hung page after ribbon (hotfixed to `ob_end_flush` before revert).
- **Dagh retest:** present LB felt slower; TT y=0 sub-ribbon blank **unchanged**; y>0 Countries full-page blank **unchanged**.
- **Conclusion:** wrong layer — curl TTFB improved but browser UX did not; perceptual present regression.

### Related perf (same chat — keep, separate from F6)

| Fix | Effect |
|-----|--------|
| Month/year catalog in-memory | ~283 ms → ~2 ms header catalog |
| Countries index SQL GROUP BY | ~515 ms → ~137 ms TT |
| Country roster country-filtered fetch | ~845 ms → ~208 ms TT |

Probes: `scripts/oneoff/amiga_tt_perf_probe.php`, `amiga_countries_index_parity_probe.php`, `amiga_country_roster_audit_probe.php`.

### Framework (Type A / B / C) — use for every repro

From [`tt-chrome-baseline-f6-attempt-log.md`](../tt-chrome-baseline-f6-attempt-log.md):

| Type | When | User sees |
|------|------|-----------|
| **A — Carry cloak** | TT carry nav **`y > 0`** | Whole viewport hidden until reveal (~700 ms) |
| **B — PHP streaming gap** | **`y = 0`** (no cloak after 3a) | Ribbon stable; void below until PHP emits hub chapter / wing nav / table |
| **C — Table cloak** | `$k2RankedCloak` | Only table hidden until `k2-table.js` |

**Inconsistency Dagh reports** (full page vs sub-ribbon) = different types on different axes — not one bug.

### PHP page order today (rating.php)

```
1. <head> + k2_carry_scroll_restore.php (cloak if y>0)
2. site_header → amiga_snapshot_chrome (TT: context + catalog DB)
3. amiga_hub_nav.php
4. BLOCK: amiga_lb_query_career + delta maps + game count
5. amiga_lb_nav.php (hub chapter + wing tabs + table anchor)
6. table ($k2RankedCloak)
```

At **y=0**, steps 1–3 may paint, then **gap at 4–5**. At **y>0**, step 1 cloaks entire body until reveal — if reveal fires before step 5 parsed, viewport at carried Y shows empty band (looks like full-page blank).

---

## Code touchpoints (rating LB)

| Layer | Path |
|-------|------|
| Page | `site/public_html/amiga/leaderboards/rating.php` |
| TT chrome | `includes/amiga_snapshot_chrome.php`, `includes/amiga_snapshot_context.php` |
| Hub / wing nav | `includes/amiga_hub_nav.php`, `includes/amiga_lb_nav.php` |
| LB query | `includes/amiga_lb_lib.php` — `amiga_lb_query_career`, `amiga_lb_context`, `amiga_lb_chapter_lede_html_for_request` |
| Snapshot / delta | `includes/amiga_lb_snapshot_lib.php`, `includes/amiga_rating_history_lib.php` |
| Carry-scroll | `includes/k2_carry_scroll_restore.php`, `js/k2-carry-scroll.js` |
| Table cloak | `$k2RankedCloak` in head, `js/k2-table.js` |

---

## Phase 0 deliverables (audit session)

1. **Query audit table** — ms per phase for present vs `month:2014-07` vs `event:22` vs `event:589` vs `month:2025-09`:
   - Snapshot header (`amiga_snapshot_context_from_request`, catalog for wing)
   - Hub chapter lede (`amiga_lb_chapter_lede_html_for_request`)
   - Main career query + delta map + game count
   - Full page curl TTFB vs total
2. **Classification matrix** — map each test-matrix cell to Type A/B/C with evidence (manual + probe).
3. **Fix proposal** — ranked options with rejected alternatives. Likely candidates:
   - **3d:** carry `reveal()` gate until `.k2-hub-chapter` (and TT wing nav) exist when `y > 0` on rating LB family
   - **Query:** shrink blocking time before hub chapter emits (not necessarily flush — maybe dedupe header+page context, cache lede counts)
   - **Shell-stable chrome:** explore how Dagh’s prior “flawless” state was achieved (grep git history pre-Jun 2026 TT slices? prior carry-scroll behaviour?)
4. **No fix required in audit slice** unless Dagh approves plan — but agent may ship small probe scripts.

Optional probe: `scripts/oneoff/amiga_rating_lb_tt_audit_probe.php`.

---

## Documentation process (mandatory when shipping)

Follow [`docs/UPDATE_DOCS.md`](../../UPDATE_DOCS.md):

| When | Action |
|------|--------|
| End of audit slice | Part A: `PROJECT_MEMORY` one line; extend this handoff or attempt log with timings |
| After verified fix | Part A: attempt log + slice handoff sections **Cause / Fix / Smokes**; invariants F6 row link only |
| DB / stored truth changed | Part B registers (unlikely for chrome slice) |

**Authority:** Dagh’s latest message > attempt log > invariants (symptoms only).

---

## Rejected / do not repeat

| Idea | Verdict |
|------|---------|
| Remove carry-scroll on realm or hub pills | **Rejected** |
| `k2-carry-cloak-top` (hide all body except ribbon) | **Rejected** (iter 2) |
| PHP flush before heavy query (iter 3b) | **Failed — reverted** |
| “Good enough” partial improvement on S1 | **Rejected by Dagh** |

---

## Git state (handoff author)

- **main @ `989054c`** — iter 3b reverted; iter 3a (`5862cde`) still in tree.
- Uncommitted / parallel tracks may exist: F20 rivals handoff, `scripts/oneoff/amiga_country_rivals_h2h_audit_probe.php` — ignore unless scope expands.

---

## Out of scope (until rating LB flawless)

- Other LB wings (Goals, Peak, …) — same pattern later
- Countries / WC hub tabs (F18) — note cross-hub from rating LB in matrix but fix after rating LB passes
- F20 country roster / rivals H2H — [`2026-07-04-002-f20-country-rivals-h2h-audit.md`](2026-07-04-002-f20-country-rivals-h2h-audit.md)
- TT ribbon pin / sticky dock (CD track) — separate policy

---

## Suggested next slices (after audit approval)

| Slice | Hypothesis |
|-------|------------|
| **3d-a** | Carry reveal gate: no `reveal()` until `.k2-hub-chapter` + `.k2-amiga-lb-tabs` in DOM when `y > 0` |
| **3d-b** | Query dedupe on rating LB path (shared context, lede counts, snapshot catalog cache per request) |
| **3d-c** | y=0 path: investigate prior flawless behaviour — may need stable chrome shell without full page replace feel (harder; document findings) |

---

## Phase 0 audit — RESULT (2026-07-04)

Full numbers: [attempt log § F6 Phase 0](../tt-chrome-baseline-f6-attempt-log.md). Headline:

1. **Root cause of the blocking segment:** `amiga_lb_snapshot_from_sql()` window-scans **`snap.*`** over the **174-column** `amiga_player_event_snapshots` — MariaDB materializes the full wide table before `rn = 1`. **500–2,000 ms** per call. Narrow window scan (`player_id` + `tournament_id`) + join back to the wide row = **~45–70 ms**, byte-identical (parity probe OK). Shared by all LB wings + Countries + histograms + realm snapshot reads.
2. **Δ map re-scans the ladder** it could get from the career query (~+150–230 ms, incl. present-day WC-start map at 183 ms).
3. **Games count computed twice** (footer + lede) and the lede opens a **third DB connection**.
4. Header ctx build is now cheap on all wings (15–25 ms) — no month-wing asymmetry left.
5. Blocking segment today: **~700–900 ms TT** (~300 ms present) vs curl TTFB ~60 ms — that window is exactly F6's void and it **straddles `MAX_CLOAK_MS` (700 ms)**, which is why y>0 sometimes reveals onto a short document (Type A band).
6. **Test-set correction:** `event:22` = Athens XCI **2025-04-05** (late cutoff, fractional-chrono import id). Use `month:2002-06` (~324 ms, 67 rows) as the genuine early cell.

### Proposed fix plan (iter 3d, ranked)

| # | Slice | Change | Expected |
|---|-------|--------|----------|
| **1** | **3d-b1 narrow snapshot join** | `amiga_lb_snapshot_from_sql()` → narrow ROW_NUMBER subquery + PRIMARY-key join-back; parity oracle re-run | Career query 500–2,000 → ~50 ms on every TT read using the helper |
| **2** | **3d-b2 Δ map slim** | Rating LB path: reuse career-query ratings; fetch only prev-ladder rating map + event participants (skip current-ladder re-scan). Present: WC-start baseline map narrows the same way | Δ map ~175–210 → ~80 ms TT; present block ~300 → ~120 ms |
| **3** | **3d-b3 count dedupe** | Compute `amiga_lb_games_count` once per request (static cache like the lede) and let the lede reuse the page connection/counts | −~35 ms + one connection |
| **4** | **3d-a reveal gate** | Only if a residual y>0 band remains after 1–3: gate `carryReady()` on destination chrome marker when payload came from TT/hub nav | Insurance; likely unnecessary once block ≪ 700 ms |

**Rejected for this iteration:** PHP flush (3b — failed); carry-scroll surgery at y=0 (3a already correct); shell-stable chrome rewrite (unnecessary if block ~150 ms — the "prior flawless state" was most likely simply a fast page).

---

## Iter 3d-b — SHIPPED (2026-07-04, Dagh-approved plan)

**Failure targeted:** F6 (+ F18/F19 same clock) · **Cause (verified):** blocking DB segment (~700–900 ms TT) between hub nav and hub chapter emit, dominated by the 174-column `snap.*` window materialization; segment straddled the 700 ms carry cloak timeout (Type B at y=0, Type A reveal race at y>0).

**Fix (verified by probes; browser sign-off pending):**

| Slice | File | Change |
|-------|------|--------|
| 3d-b1 | `includes/amiga_lb_snapshot_lib.php` | `amiga_lb_snapshot_from_sql()` — narrow ROW_NUMBER subquery (`player_id`, `tournament_id`) + PRIMARY-key join-back to the wide snapshot row (alias unchanged; callers untouched; bind params unchanged) |
| 3d-b2 | `includes/amiga_rating_history_lib.php` + `amiga_lb_snapshot_lib.php` | New `amiga_rating_history_rating_map_at_cutoff()` (narrow, no players join); `amiga_lb_rating_delta_map()` computes deltas from current+prev maps + participants via unfiltered catalog position (as_with-safe); WC-start baseline map uses the narrow map |
| 3d-b3 | `includes/amiga_lb_snapshot_lib.php` | `amiga_lb_games_count()` request-scoped cache keyed by cutoff tuple (footer + lede now one query) |

**Numbers (local `ko2amiga_db`):** career query 503–650 → **34–49 ms**; blocking segment TT **~165–230 ms** (present ~131 ms); curl total rating TT **~0.6–0.7 s** (was 1.2–1.75 s).

**Smokes run:** SQL parity ×3 cutoffs · Δ map parity ×7 scenarios (`scripts/oneoff/amiga_rating_lb_delta_parity_probe.php`) · Countries index parity · curl error sweep (rating ×5, goals, peak-rating, countries, roster, hall-of-fame TT). **Pending:** Dagh browser S1/S1b + full test matrix above.

**Known follow-up (out of narrow scope):** peak-rating wing's own `amiga_player_elo_rank_at_event` window subquery still ~3.3 s at TT cutoff — apply the same narrow+join-back pattern in the wing rollout.

---

## Iter 3d-c — y=0 chrome gate SHIPPED (2026-07-04, after Dagh feedback)

**Dagh browser verdict on 3d-b:** y>0 = fixed ("only the table waits"). **y=0 still vanishes below the ribbon on every TT nav — near-insta on click — and it is realm-wide.**

**Corrected mechanism (Type B refined):** at y=0 there was no cloak (iter 3a skip), so the browser commits the navigation at TTFB (~60 ms) and paints header+ribbon immediately. That first contentful paint **ends Chrome's paint holding** — the old page is discarded instantly, leaving the void below the ribbon until chapter bytes arrive. So even a fast block flashes, and the vanish is click-instant regardless of `MAX_CLOAK_MS`. Iter 2's narrow cloak failed for exactly this reason (it let the ribbon paint).

**Fix (`includes/k2_carry_scroll_restore.php`):** at `payload.y === 0` with no hash, if the destination URL has `[?&]as=` (TT), engage the **full-body cloak as a chrome gate**: no contentful paint → paint holding keeps the **old page** on screen; reveal when `.k2-hub-chapter` is parsed or `domReady` (non-hub TT pages), 700 ms timeout + safety nets unchanged. No scroll ops / no `scrollRestoration` flip in gate mode. Non-TT y=0 unchanged. Requires the 3d-b query speed (paint holding ≈ 500 ms budget; block now ~165–230 ms).

**Verified:** IDE-browser y=0 wing-tab hop on `rating.php?as=month:2014-07` → destination revealed, cloak removed, payload consumed. **Visual in-place feel across the S1 matrix = Dagh sign-off pending.**

---

## Same session — World Cups TT slowness (Dagh side-request)

All `/amiga/world-cups/*` TT pages multi-second (`chronology.php?as=event:583` ~5 s). Cause is unrelated to LB snapshots: `amiga_community_year_realm_games_at_cutoff()` full-scanned the 446k-row `amiga_community_stat_facts` (only `tournament_id`-first indexes) — **2,581 ms** at `event:583`. Fixed with new index `idx_community_facts_metric_period` (48 ms, 54×; DDL in `scripts/amiga/sql/034_community_stats.sql` + derived mirror, applied to local `ko2amiga_db`; staging inherits via next export) + request cache on `amiga_world_cup_stats_rows()` (shell chapter + body deduped). Curl `event:583`: chronology → **~0.5 s**, players/stats/countries wings 0.4–0.8 s. Details: [attempt log § World Cups hub](../tt-chrome-baseline-f6-attempt-log.md).

---

## Changelog (this file)

| Date | Entry |
|------|--------|
| 2026-07-04 | Handoff created — F6 rating LB narrow scope; audit-first; iter 3d+ |
| 2026-07-04 | **Phase 0 audit complete** — 174-col wide window scan root cause; ranked 3d plan (narrow join → Δ slim → count dedupe → optional reveal gate); probes `amiga_rating_lb_tt_audit_probe.php` + `amiga_rating_lb_sql_variant_probe.php` |
| 2026-07-04 | **Iter 3d-b shipped** — b1 narrow snapshot join, b2 slim Δ map, b3 games-count cache; parity green; TT block ~165–230 ms; browser S1 matrix = Dagh sign-off pending |
| 2026-07-04 | **Iter 3d-c shipped** — y=0 TT chrome gate in `k2_carry_scroll_restore.php` (full cloak + paint holding, reveal on `.k2-hub-chapter`/domReady); Dagh confirmed y>0 fixed; y=0 visual sign-off pending. Side fix: WC TT slowness = `amiga_community_stat_facts` index + WC stats rows request cache |
| 2026-07-04 | **Slow-wing follow-through** — tournament-honours (586 ms ×2), calendar-geo (1.1 s), peak-rating (2.2–3.4 s) TT queries fixed: first two moved onto shared `amiga_lb_snapshot_from_sql()` narrow shape (+ honours request cache); peak-rating er-join = dense-event equality (`er.tournament_id = cutoff`, table verified dense) replacing the 173k-row window. Parity green ×7; all three wings 40–92 ms — the "peak-rating ~3.3 s follow-up" above is **done**. Attempt log § Three slow LB wings |