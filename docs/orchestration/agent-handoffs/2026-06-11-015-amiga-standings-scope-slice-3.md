# Amiga standings scope unification — slice 3 handoff

**Date:** 2026-06-11  
**Slice:** 3 — Primary league resolver + honours  
**Plan:** [`amiga-standings-scope-implementation-plan.md`](../../amiga-standings-scope-implementation-plan.md)

---

## Goal

`resolve_primary_league_standings()` per policy §3; wire Tier B/C honours derivation; Python + PHP parity.

---

## Checklist

- [x] Python `resolve_primary_league_standings()` in `participation_placement.py`
- [x] Replaced `_overall_positions()` calls with resolver
- [x] `derive_wc_group_positions()` filters `league`
- [x] PHP `amiga_participation_resolve_primary_league_standings()` + callers
- [x] WC supplement SQL in `amiga_post_game_participation.php` → `league`
- [x] `verify_player_participation.py` primary-league scope check (`league` + `''`)
- [x] Unit tests: resolver rules + Athens XCI-style Tier B + updated fixtures to `league`
- [x] `python -m scripts.amiga participation-rebuild`

### Verification

- [x] `python -m unittest scripts.amiga.test_participation_placement -v` — 34 tests OK
- [x] `python -m scripts.amiga verify-player-participation` — OK
- [x] `event_finish_position` unchanged for tournaments 22, 24, 544 (pre/post snapshot match)

---

## Files changed

| File | Change |
|------|--------|
| `scripts/amiga/participation_placement.py` | Resolver + Tier B/C wiring |
| `scripts/amiga/test_participation_placement.py` | `league` fixtures + 5 resolver tests |
| `scripts/amiga/verify_player_participation.py` | `league` + `''` participation guard |
| `site/public_html/includes/amiga_participation_placement.php` | PHP resolver + routing |
| `site/public_html/amiga/ops/includes/amiga_post_game_participation.php` | WC supplement `league` |

---

## Pre/post SQL snapshot (event_finish_position)

**Pre-slice 3 (before participation-rebuild this slice):**

```
22: 66=1, 14=2, 30=3, 410=4, 338=5, 100=6, 354=7, 142=8, 141=9, 244=10, 463=11, 242=12
24: 14=1, 142=2, 338=3, 354=4, 244=5
544: 134=1, 73=2, 286=3, 30=5, 405=6, 421=7, 422=8
```

**Post-slice 3:** identical rows for tournaments 22, 24, 544.

---

## Verification output

```
Ran 34 tests in 0.170s — OK

participation-rebuild: 4517 rows

OK: player participation verified (4517 participation rows, 473 player totals)
```

---

## STOP GATE B

User confirms participation verify + spot SQL before slice 4.

**Browser:** not required this slice (slice 4 STOP GATE C).

---

## Next slice

**Slice 4** — `tournament.php`, `amiga_tournament_lib.php`, legacy URL mapping (`?scope=overall` → `league`).
