# Amiga event finish — slice 4 handoff

**Date:** 2026-06-11  
**Slice:** 4 — `best_knockout_phase`  
**Plan:** [`amiga-event-finish-implementation-plan.md`](../../amiga-event-finish-implementation-plan.md)

---

## Goal

Derive deepest main-bracket knockout round label per player for participation column (writers slice 5).

---

## Checklist

- [x] `derive_best_knockout_phase(standing_rows, player_id) -> str | None`
- [x] Helpers: `is_main_bracket_knockout_label`, `is_subsidiary_cup_knockout_label`
- [x] Unit tests: QF/SF/Final exit, WC semi, placement final depth, subsidiary cup ignored
- [x] DB integration: Bournemouth II (`tournament_id=544`)
- [x] **Did not** wire writer (slice 5)

### Verification

- [x] `python -m unittest scripts.amiga.test_participation_placement -v` — 27 OK
- [x] `python -m unittest scripts.amiga.test_tournament_honours -v` — 8 OK

---

## Files changed

| File | Change |
|------|--------|
| `scripts/amiga/participation_placement.py` | `derive_best_knockout_phase` + main-bracket filters |
| `scripts/amiga/test_participation_placement.py` | `BestKnockoutPhaseTests` + DB check |

---

## Verification output

```
test_participation_placement: 27 tests OK
test_tournament_honours: 8 tests OK
```

Examples:

| Player / case | `best_knockout_phase` |
|---------------|----------------------|
| Cup finalist | `Final` |
| Semi loser | `Semi Final` / `Semi Finals` |
| QF loser | `Quarter Final` |
| League-only | `NULL` |
| WC semi exit | `Semi Finals` |
| Silver Cup Final only | ignored; uses main bracket if present |

---

## STOP gate notes

None.

---

## Known limitations / next slice

- **Slice 5:** Wire `event_finish_position`, `best_knockout_phase`, honours totals, `participation-rebuild`
- PHP parity for `derive_best_knockout_phase` deferred to slice 6
