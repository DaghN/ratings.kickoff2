# TT chrome baseline — slice 0 handoff

**Date:** 2026-07-04  
**Track:** Amiga TT chrome baseline (in-flow)  
**Failure targeted:** **F6** — sub-ribbon content blanks on TT ribbon nav at scroll top  
**Status:** Iteration 2 — Dagh sign-off pending (iter 1 partial)  

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

## Verified cause (iteration 2 — after Dagh feedback)

Iteration 1 **regressed pickers** and **month wing**:

1. **Skipping cloak at `y=0` without anchor** (iter 1) left picker / as-with forms on **incremental HTML streaming** — TT ribbon painted first, hub chapter still blocked on PHP LB query → **everything below ribbon vanishes** (matches Dagh repro).
2. **`carryReady() || domReady`** and **700ms timeout** forced reveal before **`.k2-hub-chapter`** arrived when PHP was slow (**month wing**, late cutoff) — same symptom; chevrons only “worked” when query finished under 700ms.
3. **Month mode worse** = heavier/slower cutoff reads, not a separate wing bug.
4. **F19 LED** — full `body` cloak hid stamp; LED data is already in HTML; coupled visually to table wait.

**PHP stream order (rating LB):** `site_header` → TT stamp + ribbon (includes snapshot DB read) → hub nav → **LB query** → `amiga_lb_nav` hub chapter → table. Browser can paint ribbon while query runs.

---

## Verified fix (iteration 2)

| Change | Why |
|--------|-----|
| **Revert** y=0 cloak skip | Pickers must stay cloaked until sub-ribbon ready |
| **`k2-carry-cloak-top`** CSS | Scroll-top carry: keep header + stamp + ribbon visible; hide rest until reveal |
| **No domReady / 700ms early reveal** when `targetY <= 0` | Wait for hub chapter in DOM or `window` load |
| **`storeScrollYFromForm`** + TT stepper anchor | Period + with-player pickers participate in scroll-top carry |
| **Strict `carrySubRibbonReady()`** | No domReady fallback — only `.k2-hub-chapter`, feast hero, or entity headers |

---

## Files changed

- `site/public_html/includes/k2_carry_scroll_restore.php`
- `site/public_html/js/k2-carry-scroll.js`

---

## Regression guardrails (do not revert casually)

1. **Never** reveal scroll-top carry until **`carrySubRibbonReady()`** — hub chapter is below TT ribbon; hub tabs alone are too early.
2. **Never** use **`domReady` fallback** or **700ms timeout reveal** for `targetY <= 0`.
3. **Do not** skip body cloak at `y=0` for picker forms — streaming flash returns.
4. Keep **`k2-carry-cloak-top`** paired with scroll-top carry — stamp/ribbon stability + F19.
5. Picker / as-with forms → **`storeScrollYFromForm`** (TT anchor).
6. Re-touching carry-scroll → re-run **S1**, **S1b**, **S2**, month-wing picker from invariants.

---

## Known limitations / next slice

- **F18** (Countries / WC hub-tab whole-page blank, late cutoff) — **not** addressed here; may share carry-scroll but needs separate slice.
- **F19** — wing-tab entry (`k2_tt_entry=wing`) still intentionally fades LED; chevron/picker should not.
- Player profile at scroll top: feast-hero gate in `carrySubRibbonReady`.
- Sticky CD track must re-run S1/S1b after implementation — sticky reintroduces scroll-phase timing.

---

## STOP gate

**Waiting for Dagh:** manual S1 pass on `ratingskickoff.test` before calling baseline slice 0 closed.