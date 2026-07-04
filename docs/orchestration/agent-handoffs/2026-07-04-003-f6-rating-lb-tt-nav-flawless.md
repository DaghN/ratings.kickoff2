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

## Changelog (this file)

| Date | Entry |
|------|--------|
| 2026-07-04 | Handoff created — F6 rating LB narrow scope; audit-first; iter 3d+ |