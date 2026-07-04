# TT chrome baseline — slice 0 handoff

**Date:** 2026-07-04  
**Track:** Amiga TT chrome baseline (in-flow)  
**Failure targeted:** **F6** — sub-ribbon content blanks on TT ribbon nav at scroll top  
**Status:** **Superseded** — iter 1–2 history only; verified fix is in [`2026-07-04-003`](2026-07-04-003-f6-rating-lb-tt-nav-flawless.md) (3d-b/c) + [attempt log](../tt-chrome-baseline-f6-attempt-log.md)

> **Archive note:** Iter 2 (`k2-carry-cloak-top`) was **reverted**. Do not re-apply guardrails below — they describe the failed iter 2 path.

---

## Goal (historical)

Eliminate **old content → blank → new content** blink below the TT ribbon when navigating at **`scrollY ≈ 0`**.

---

## Verified cause (iteration 2 — after Dagh feedback; reverted)

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

**Closed 2026-07-04** — superseded by handoff 003 (3d-b/c). Optional Dagh S1 pass for final sign-off.