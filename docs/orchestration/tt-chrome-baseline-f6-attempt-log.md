# TT baseline F6 — attempt log (informal)

**Not authoritative.** Symptom register stays in [`amiga-tt-chrome-sticky-invariants.md`](../amiga-tt-chrome-sticky-invariants.md). Verified fixes stay in slice handoffs. This file is a **lab notebook**: what we tried, observed result, and post-mortem guesses.

**Failure targeted:** F6 (sub-ribbon blank on TT ribbon nav at scroll top) · related F19 (LED) · accidental realm-switch regression (discovered iter 2).

---

## Attempt 0 — Explore + register (2026-07-04)

| | |
|--|--|
| **What** | Documented F6/F18 from Dagh repro; no code. |
| **Worked?** | Yes for process — clear scroll-top vs mid-scroll split, picker vs chevron, month wing sensitivity. |
| **Why / notes** | Established that “only table vanishes” = ranked-table cloak; “everything below ribbon” = carry-scroll / streaming timing. |

---

## Attempt 1 — Slice 0 iter 1 (`cec928f`)

| | |
|--|--|
| **Hypothesis** | At scroll top, `carryReady()` passes too early (`maxScrollTop >= 0`); reveal before `.k2-hub-chapter` exists. |
| **Changes** | (1) Skip body cloak when payload `{ y: 0, no anchor }`. (2) `carrySubRibbonReady()` gate when `targetY <= 0`. (3) Chevrons store TT stepper nav anchor. |
| **Dagh result** | **Partial.** Chevrons/wings often OK. **Pickers bad.** Month wing worse. LED co-waits with table. |
| **Worked?** | Partially — not sign-off. |
| **Why it failed (post-mortem)** | **Skip-cloak path wrong for pickers:** no anchor → no cloak → browser streams HTML (ribbon first, hub chapter after slow PHP) → visible blank below ribbon. **Month wing:** same streaming + 700ms / `domReady` still forced early reveal on some paths. **Chevrons OK when query fast** (< ~700ms). Iter 1 accidentally improved pickers’ *theory* while breaking pickers in practice by removing cloak. |

---

## Attempt 2 — Slice 0 iter 2 (`1d875ed`, WIP)

| | |
|--|--|
| **Hypothesis** | Keep cloak at scroll top; wait past 700ms for hub chapter; show header+stamp+ribbon during wait (`k2-carry-cloak-top`); picker forms store TT anchor. |
| **Changes** | (1) Reverted y=0 cloak skip. (2) Added `k2-carry-cloak-top` CSS (hide `body *` except header / stamp / TT ribbon). (3) No `domReady` / 700ms early reveal when `targetY <= 0`. (4) `storeScrollYFromForm` for period + with-player pickers. |
| **Dagh result** | **Worse.** Complete sub-ribbon blank on **every** TT action (wings, chevrons, pickers). **New:** Amiga → Online realm switch → header only, blank below, then online content. |
| **Worked?** | No. |
| **Why it failed (post-mortem)** | See investigation below. |

### Investigation — iter 2 regressions (2026-07-04)

#### A) Visible blank is now intentional CSS (TT pages)

`k2-carry-cloak-top` sets `body * { visibility: hidden }` except header, stamp, and `.k2-amiga-time-travel`. Until `reveal()`, **sub-ribbon is forcibly hidden** — not merely “not streamed yet”. User sees a stable ribbon + **empty viewport below** for the whole wait (until hub chapter exists or `window` load / 900ms safety `reveal()`). That reads as “complete sub-ribbon blanking” on every nav, even when iter 1 chevrons felt OK.

#### B) Realm switch — cross-realm carry-scroll (`NEW`)

`realm_switcher_nav.php` uses `data-k2-carry-scroll` on **“Kick Off 2 realm”** pills. Leaving Amiga at scroll top stores `{ y: 0, anchor: realm nav }` → scroll-top carry on **Online** destination.

Online pages have **no** `.k2-amiga-tt-stamp` / `.k2-amiga-time-travel`. Narrow-cloak whitelist leaves **only `.k2-site-header` visible** — **entire Online body hidden** until reveal. Matches: *amiga content → blank under header → online content*.

Before iter 2: full-body cloak (`body { visibility: hidden }`) → solid theme fill, or faster reveal paths — Dagh saw cross-fade without obvious header+void.

#### C) PHP stream order unchanged

Rating LB still emits: header → TT chrome → hub nav → **LB query** → `.k2-hub-chapter` → table. Waiting for hub chapter is correct in theory but **iter 2 makes the wait visually harsh** via narrow cloak. Slow month queries lengthen the void.

#### D) Safety nets

`window` `load` and `setTimeout(reveal, 900ms)` still call `reveal()` and remove cloak classes. Persistent blank likely = **long period with `k2-carry-cloak-top` active** (user-perceived “always”), not permanent stuck cloak — but realm case hides all Online content until reveal.

#### E) y=0 only (Dagh, 2026-07-04)

**All reported problems occur at `scrollY ≈ 0`. Mid-scroll carry (`y > 0`) behaves fine** — including realm switch when scrolled. The regression surface is **scroll-top navigation only**, not carry-scroll as a feature.

**Design tension:** When `y = 0`, scroll restore is a **no-op** (target is already top). Engaging the full pre-paint cloak pipeline at y=0 is trying to solve **HTML streaming order** with a tool built for **mid-page scroll flash**. That is harder than mid-scroll, not easier — and it should not be.

---

## Rejected ideas (spec creep)

| Idea | Verdict |
|------|---------|
| **Stop carry-scroll on realm pills** | **Rejected** — realm switch must keep carry-scroll; iter 2 broke it, fix the bug, do not remove the feature. |

---

## Likely next directions (not decided)

| Option | Idea | Risk |
|--------|------|------|
| **Revert iter 2 narrow cloak** | Drop `k2-carry-cloak-top`; restore iter 1 or pre-iter-2 base | Pickers/month still need y=0 path |
| **y=0 noop path (recommended)** | Destination: if stored `y === 0`, **skip cloak + restore loop** (normal full-page load at top). Source: still store payload for back/forward. Mid-scroll unchanged. | Brief streaming flash at y=0 may remain → address with PHP flush if needed |
| **PHP flush** | Emit hub chapter before heavy LB query on hot paths | Larger slice; fixes stream order at source |

---

## Changelog (this file)

| Date | Entry |
|------|--------|
| 2026-07-04 | Log created after iter 2 feedback — iter 1 partial, iter 2 worse + realm regression |
| 2026-07-04 | **y=0 only** — mid-scroll OK; realm carry-scroll is required (no feature removal); rejected “skip realm carry” |