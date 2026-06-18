# Amiga standings scope unification — slice 1 handoff

**Date:** 2026-06-11  
**Slice:** 1 — Python standings writers  
**Plan:** [`amiga-standings-scope-implementation-plan.md`](../../amiga-standings-scope-implementation-plan.md)

---

## Goal

Emit `ScopeType.LEAGUE` only for points tables; remove `OVERALL` / `GROUP` / `PLACEMENT` from Python enum; catalog stats writer uses `league_scopes`.

---

## Checklist

- [x] `tournament_phases.py`: `ScopeType.LEAGUE`; `parse_phase` NULL → `LEAGUE, ''`; labeled RR → `LEAGUE, label`
- [x] `is_league_scope()` → `scope_type == LEAGUE`
- [x] `tournament_standings.py`: fixture scopes → `LEAGUE`; synthetic aggregate `(LEAGUE, '')`
- [x] `tournament_catalog_stats.py` — `league` / `league_scopes` (non-empty keys only, parity with old `group_scopes`)
- [x] `tournament_builder.py` smoke — expects `league` + `''`
- [x] `standings_parity.py` — derived DB reads map CLI `overall`/`group` → `league`
- [x] Unit tests: `test_tournament_phases.py` updated
- [x] Rebuild tournament 24 smoke

### Verification

- [x] `python -m unittest discover -s scripts/amiga -p "test_tournament*.py" -v` — 25 tests OK
- [x] Tournament 24 rebuild: 5 rows, all `scope_type = league`, `scope_key = ''`

---

## Files changed

| File | Change |
|------|--------|
| `scripts/amiga/tournament_phases.py` | Enum `LEAGUE` \| `KNOCKOUT`; parse_phase + is_league_scope |
| `scripts/amiga/tournament_standings.py` | All points-table emission → `LEAGUE`; synthetic aggregate |
| `scripts/amiga/tournament_catalog_stats.py` | `league_scopes` column + `scope_type = 'league'` counts |
| `scripts/amiga/tournament_builder.py` | Swiss smoke asserts `league` |
| `scripts/amiga/standings_parity.py` | `_derived_scope_type()` for MySQL reads |
| `scripts/amiga/test_tournament_phases.py` | LEAGUE assertions + null-phase test |

---

## Verification output

```
Ran 25 tests in 2.002s — OK

rebuild_standings_for_tournament(24) → rebuilt_rows=5

SELECT scope_type, scope_key, COUNT(*) ... tournament_id=24:
league  (empty)  5
```

---

## STOP gate notes

None for slice 1 (gate A was slice 0).

---

## Known limitations / next slice

- PHP `amiga_post_game_standings.php` still emits `overall`/`group` — **slice 2**.
- `participation_placement.py` still filters `overall`/`group` — **slice 3**.
- Public `tournament.php` readers unchanged — **slice 4**.
- Full `replay` not run (slice 6).

**Next:** Slice 2 — PHP post-game standings + `amiga_tournament_phases.php` parity.
