# TT ribbon sticky v1 — CSS slice

**Date:** 2026-07-04  
**Track:** CD sticky v1 (sticky on only; no pushpin)

## Goal

Ship **sticky on** ribbon behaviour on all Amiga pages with active `as=`: **in flow** at scroll top, **stuck** at `top: 0` when scrolled past latch. CSS-first; no pin JS.

## Product rules (v1) — chosen policy

- Ribbon background: opaque `var(--k2-bg-page)` (site default page dark) + hairline border in both **in flow** and **stuck**
- Whole document scrolls; stamp never sticky
- No animated slide-in / scroll-linked JS motion
- **Sticky on only** — no pushpin, no `localStorage`
- Stuck vs in flow = scroll position only (carry-scroll restore honours saved Y)

## Fix (verified)

| Item | Detail |
|------|--------|
| Sticky CSS | `.k2-amiga-time-travel--active { position: sticky; top: 0; z-index: 1390; }` |
| Ribbon bg | `background: var(--k2-bg-page);` on `.k2-amiga-time-travel` |
| Event wing | `flex-wrap: wrap` on bar when active |
| Overflow | `overflow-x: clip` on `html`, `body.k2-site`, `.k2-page-nav` — `hidden` forced `overflow-y: auto` on body and **broke** viewport sticky |

## Files

- `site/public_html/stylesheets/theme.css`

## Smokes run (local agent)

| Step | Result |
|------|--------|
| Load rating LB `?as=event:589` @ `scrollY=0` | Ribbon **in flow** (~182px from viewport top) |
| Scroll to 500px | Ribbon **stuck** at `top: 0` |
| Scroll back to 0 | Ribbon **in flow** again |
| Chevron from **stuck** | Nav to `event:588`; carry-scroll restores Y; ribbon ~0px from top |

## Failures register closure

**F1–F17** (JS pin attempt symptoms) **closed** or **superseded** in [miga-tt-chrome-sticky-invariants.md](../../amiga-tt-chrome-sticky-invariants.md). F6/F18/F19/F20 were already closed by prior slices.

## Deferred

- Pushpin **sticky off** (CD3)
- Toggle-entry opt-out clear without pin UI (CD2 partial)
- TT chrome coordinator wrapper (policy §7)