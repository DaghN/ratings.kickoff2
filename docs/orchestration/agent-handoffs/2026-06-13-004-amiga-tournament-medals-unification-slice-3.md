# Amiga tournament medals unification v2 — slice 3 handoff

**Date:** 2026-06-13  
**Slice:** 3 — Writers: totals aggregation + `is_winner`  
**Plan:** [`amiga-tournament-medals-unification-implementation-plan.md`](../../amiga-tournament-medals-unification-implementation-plan.md)

---

## Goal

Single-path career rollup per honours rules v2 §4. Python + PHP parity. `is_winner` = `event_finish_position = 1` for all tournaments.

---

## Checklist

- [x] `_TOTALS_AGG_SELECT` / `_TOTALS_INSERT_PREFIX` — v2 `event_*`, `wc_played`, `wc_podiums`; drop `cup_*` / `podiums`
- [x] `amiga_ops_participation_rebuild_totals_for_players` — mirrored SQL
- [x] `participation_is_winner` / `amiga_participation_is_winner` — finish = 1 only
- [x] `refresh_wc_medals` / PHP WC medal refresh — `is_winner` from `event_finish_position`
- [x] Tests updated (`test_player_tournament_incremental` asserts v2 totals invariants)

### Verification

```powershell
python -m unittest scripts.amiga.test_player_tournament_participation scripts.amiga.test_participation_placement scripts.amiga.test_player_tournament_incremental scripts.amiga.test_tournament_honours -v
# Ran 50 tests — OK
```

**Alkis P** (one-player totals rebuild):

```
tournaments_played=101, tournaments_won=58, event_gold=58, wc_gold=2, event_podiums=85, wc_podiums=8
```

---

## Files changed

| File | Change |
|------|--------|
| `scripts/amiga/player_tournament_participation.py` | v2 totals SQL |
| `scripts/amiga/participation_placement.py` | `participation_is_winner` v2 |
| `scripts/amiga/tournament_honours.py` | `refresh_wc_medals` is_winner from finish |
| `site/public_html/includes/amiga_participation_placement.php` | is_winner + WC medals helper (slice 1) |
| `site/public_html/amiga/ops/includes/amiga_post_game_participation.php` | v2 totals SQL + is_winner |
| `scripts/amiga/test_*.py` | v2 winner + incremental totals invariants |
| `docs/amiga-data-contract.md` | totals writer note |
| `scripts/amiga/README.md` | slice 3 writer status |

---

## STOP gate notes

None for slice 3.

---

## Known limitations / next slice

- **Full `participation-rebuild` not run** — only per-player/tournament incremental paths proven; slice 4 rebuilds all totals.
- **PHP read paths** still SELECT `cup_*` / `podiums` — slices 5–7.
- **`wc_medal` column** still written — dropped slice 6.

**Next:** Slice 4 — full `participation-rebuild` + verify extensions (**STOP GATE A**).
