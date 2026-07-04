# TT chrome baseline — slice 0 handoff

**Date:** 2026-07-04  
**Track:** Amiga TT chrome baseline (in-flow)  
**Failure targeted:** **F6** — sub-ribbon content blanks on TT ribbon nav at scroll top  
**Status:** Shipped (local verify); Dagh browser sign-off pending  

---

## Goal

Eliminate **old content → blank → new content** blink below the TT ribbon when navigating at **`scrollY ≈ 0`** via wings, chevrons, or dropdown pickers — without regressing mid-scroll carry-scroll.

---

## Verified cause

Carry-scroll **body cloak** (`html.k2-carry-cloak`) runs on every ribbon nav because `k2-carry-scroll.js` stores a payload. At **scroll top**:

1. **`carryReady()`** passed immediately — `maxScrollTop() >= 0` is true as soon as `<body>` exists.
2. **`reveal()`** ran before **hub chapter** (and other sub-ribbon chrome) was parsed.
3. User saw ribbon stack stable (parsed early in `<body>`) + **empty region** where hub chapter/table would appear → perceived as blanking below ribbon.

Mid-scroll worked because `carryReady()` waited until the document was **tall enough** for the stored Y (and/or anchor nav existed), so more sub-ribbon DOM was present before reveal.

Chevron clicks previously stored `{ y: 0 }` **without** a nav anchor, hitting the fastest early-reveal path. Wing tabs stored an anchor but anchor wait only guaranteed **TT ribbon nav** parsed — not **`.k2-hub-chapter`**.

---

## Verified fix

### 1. `k2_carry_scroll_restore.php`

| Change | Why |
|--------|-----|
| **Skip cloak** when payload is `{ y: 0 }` with **no anchor** (after read, before `hasPending`) | Scroll restore is a no-op at top; cloak only caused harm (dropdown pickers). |
| **`carrySubRibbonReady()`** — require `.k2-hub-chapter` or feast player hero (or `domReady` fallback) when **`resolveTargetY(payload) <= 0`** before `carryReady()` passes | Keeps cloak for anchored top nav (wings, chevrons) but delays reveal until hub chapter / hero exists. |

### 2. `k2-carry-scroll.js`

| Change | Why |
|--------|-----|
| Chevron / stepper links inside `nav[data-k2-carry-scroll]` now store **nav anchor** (same as hub pills) | Consistent payload; pairs with `carrySubRibbonReady()` for TT stepper at scroll top. |

---

## Files changed

- `site/public_html/includes/k2_carry_scroll_restore.php`
- `site/public_html/js/k2-carry-scroll.js`

---

## Verification (agent)

| Smoke | Result |
|-------|--------|
| **S1** — rating LB `?as=event:589` at top, simulated carry payload `y:0` + TT stepper anchor → navigate | `k2:carryScrollY` cleared; no stuck cloak; `.k2-hub-chapter` present at reveal |
| **S1b** — contrast | Mid-scroll carry path unchanged (still stores Y; cloak when Y > 0) |
| PHP lint `k2_carry_scroll_restore.php` | Pass |

**Dagh manual (required):** S1 on local — scroll to top → chevron ×3, wing Event→Month→Year, picker change — confirm no sub-ribbon blank. S2 mid-scroll chevron ×2 — scroll position still carries.

---

## Regression guardrails (do not revert casually)

1. **Never** reveal carry-cloak at `targetY <= 0` until **`carrySubRibbonReady()`** — hub chapter is below TT ribbon in DOM order; waiting for hub tabs alone is **too early**.
2. **Do not** re-enable full body cloak for `{ y: 0, no anchor }` — scroll restore is noop; F6 returns.
3. TT ribbon stepper / wing clicks should **store nav anchor** when inside `data-k2-carry-scroll`.
4. Re-touching carry-scroll → re-run **S1**, **S1b**, **S2** from [`amiga-tt-chrome-sticky-invariants.md`](../amiga-tt-chrome-sticky-invariants.md).

---

## Known limitations / next slice

- **F18** (Countries / WC hub-tab whole-page blank, late cutoff) — **not** addressed here; may share carry-scroll but needs separate slice.
- Player profile at scroll top + hub pill without hub chapter: uses feast-hero or `domReady` fallback — watch for hero border flash (pre-2026 concern); not reported in F6 repro.
- Sticky CD track must re-run S1/S1b after implementation — sticky reintroduces scroll-phase timing.

---

## STOP gate

**Waiting for Dagh:** manual S1 pass on `ratingskickoff.test` before calling baseline slice 0 closed.