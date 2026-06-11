# Amiga event finish — slice 3 handoff

**Date:** 2026-06-11  
**Slice:** 3 — World Cup medals  
**Plan:** [`amiga-event-finish-implementation-plan.md`](../../amiga-event-finish-implementation-plan.md)

---

## Goal

WC `event_finish_position` stays NULL (Tier D); `wc_medal` derivation with shared semi bronze when no 3rd-place match.

---

## Checklist

- [x] `compute_wc_medals_from_standings` — shared bronze to both semi losers when no 3rd-place final + Final complete
- [x] Main Final only (not Silver Cup Final); 3rd-place final winner otherwise
- [x] Removed group/overall-only medal fallback (policy §4.2)
- [x] Tier D unchanged in `derive_event_finish_position` (empty map for WC names)
- [x] Tests: shared semi bronze, 3rd-place precedence, no group medals, incomplete final guard

### Verification

- [x] `python -m unittest scripts.amiga.test_tournament_honours -v` — 8 OK
- [x] `python -m unittest scripts.amiga.test_participation_placement -v` — 19 OK

---

## Files changed

| File | Change |
|------|--------|
| `scripts/amiga/tournament_honours.py` | WC medal rules + label helpers |
| `scripts/amiga/test_tournament_honours.py` | 5 new medal tests; removed overall fallback test |
| `scripts/amiga/participation_placement.py` | Docstring: Tier D implemented |
| `scripts/amiga/test_participation_placement.py` | Tier D test with league+cup flags |

---

## Verification output

```
test_tournament_honours: 8 tests OK
test_participation_placement: 19 tests OK
```

**Note:** All 23 local WCs have a `3rd Place Final` scope in standings — shared semi bronze is covered by unit tests; live DB still uses 3rd-place winner path until a no-3rd-place WC exists.

---

## STOP gate notes

None (gate A was slice 2).

---

## Known limitations / next slice

- **Slice 4:** `best_knockout_phase` derivation
- Writers still legacy until slice 5; `refresh_wc_medals` logic updated but not re-run in this slice
