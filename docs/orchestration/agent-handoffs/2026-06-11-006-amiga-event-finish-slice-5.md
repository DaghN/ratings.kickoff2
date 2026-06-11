# Amiga event finish — slice 5 handoff

**Date:** 2026-06-11  
**Slice:** 5 — Writers and career totals  
**Plan:** [`amiga-event-finish-implementation-plan.md`](../../amiga-event-finish-implementation-plan.md)

---

## Goal

Participation rebuild writes `event_finish_position` + `best_knockout_phase`; totals use honours rules §4; `is_winner` from finish + WC medals.

---

## Checklist

- [x] `player_tournament_participation.py`: INSERT new columns; `derive_event_finish_position` + `derive_best_knockout_phase`
- [x] Legacy `overall_position` still written via `derive_participation_positions` (drop slice 8)
- [x] `_TOTALS_AGG_SELECT`: podiums, cup_*, `tournaments_won` via `is_winner` + `event_finish_position`
- [x] `participation_is_winner`: `event_finish_position = 1` or WC gold
- [x] `refresh_wc_medals` after participation (unchanged order)
- [x] `python -m scripts.amiga participation-rebuild` — 4517 participation, 473 totals

### Verification

- [x] `verify-player-participation` OK
- [x] `verify-chronology` OK
- [x] `verify-rating-events` OK
- [x] Spot SQL (see STOP GATE B below)

---

## Files changed

| File | Change |
|------|--------|
| `scripts/amiga/player_tournament_participation.py` | Writer + totals SQL |
| `scripts/amiga/participation_placement.py` | `participation_is_winner` uses `event_finish_position` |
| `scripts/amiga/test_participation_placement.py` | Winner rule test |

---

## Rebuild output

```
participation-rebuild complete: participation=4517 totals=473
event_finish_position NOT NULL: 3495 (zero rows with 0)
best_knockout_phase NOT NULL: 1026
legacy overall_position > 0: 4351
```

---

## STOP GATE B — user review before slice 6

### Podiums delta (legacy `overall_position <= 3` → new rules)

| Player | Legacy | New (totals) | Delta |
|--------|--------|--------------|-------|
| Dagh N (73) | 17 | **16** | −1 |
| Alkis P (14) | 95 | **85** | −10 |
| Garry C (134) | 54 | **52** | −2 |

Expected: fewer podiums — WC group top-3 and league-phase ranks no longer count; WC medals and true event finish count.

### Copenhagen WC check (Dagh 73)

| Field | Value |
|-------|-------|
| `event_finish_position` | **NULL** |
| `overall_position` | 1 (legacy group rank) |
| `wc_medal` | none |
| Counts as podium | **No** (neither finish ≤3 nor medal) |

### KO cup example (Dagh, Bournemouth II 544)

| Field | Value |
|-------|-------|
| `event_finish_position` | 2 |
| `best_knockout_phase` | Final |
| `is_winner` | 0 |

### Browser

- `/amiga/leaderboards/tournament-honours.php` — podiums column order/sanity (Alkis P should lead podiums at **85**, not 95)

**Wait for user OK** before slice 6 (PHP parity).

---

## Known limitations / next slice

- **Slice 6:** PHP `amiga_participation_placement.php` + post-game path
- **Slice 7:** UI read paths
- `overall_position` still populated (legacy)
