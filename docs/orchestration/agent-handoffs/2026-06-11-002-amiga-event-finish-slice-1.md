# Amiga event finish — slice 1 handoff

**Date:** 2026-06-11  
**Slice:** 1 — Derivation engine (Tier A + Tier C)  
**Plan:** [`amiga-event-finish-implementation-plan.md`](../../amiga-event-finish-implementation-plan.md)

---

## Goal

Expose `derive_event_finish_position()` with Tier A (pure KO) and Tier C (pure league); WC and league+cup deferred.

---

## Checklist

- [x] Document transition — legacy `derive_participation_positions` unchanged for writers until slice 5
- [x] `derive_event_finish_position(standing_rows, *, tournament_name, has_league, has_cup)` with tier routing
- [x] Tier A: `compute_tier_a_knockout_finish` — Final 1/2, 3rd-place 3/4, shared semi bronze (both 3), 5+ by depth; main Final only
- [x] Tier C: overall scope `position`
- [x] Tier D (WC): empty map / NULL per player
- [x] Tier B (`has_league` + `has_cup`): empty map (slice 2)
- [x] Unit tests: Bournemouth II KO, London XXIII league, 3rd-place final, shared semi bronze, subsidiary cup final guard
- [x] **Did not** wire writer (slice 5)

### Verification

- [x] `python -m unittest scripts.amiga.test_participation_placement -v` — 14 tests OK

---

## Files changed

| File | Change |
|------|--------|
| `scripts/amiga/participation_placement.py` | `derive_event_finish_position`, `compute_tier_a_knockout_finish`, label helpers |
| `scripts/amiga/test_participation_placement.py` | `EventFinishPositionTests` (9 new tests) |

---

## Verification output

```
Ran 14 tests in 0.003s — OK
```

Key assertions:

- Bournemouth II: semi losers 286 + 30 both `event_finish_position = 3` (not 3 and 4)
- London XXIII: overall positions 1/2/3
- 3rd Place Final: ranks 3 and 4
- WC: `{}` / NULL; league+cup flags: `{}` until slice 2

---

## STOP gate notes

None for slice 1.

---

## Known limitations / next slice

- **Slice 2:** Tier B (league+cup merge); optional real-tournament dry-run
- **Slice 3:** WC medals shared bronze; Tier D explicit in honours module
- Writers still use legacy `overall_position` path
