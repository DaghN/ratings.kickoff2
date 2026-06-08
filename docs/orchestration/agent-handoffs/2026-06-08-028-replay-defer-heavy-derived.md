# Batch replay — defer heavy player derived

**Date:** 2026-06-08  

## Goal

Speed up full-history `replay` / `refinalize-from` without changing live finalize semantics.

## Done

- [x] `finalize_tournament(..., defer_heavy_derived=False)` — skip network-count scan + peak/nadir when True
- [x] `commit_heavy_player_derived(conn)` — one pass after batch: network counts, peaks, all player stats rows
- [x] `replay.py` — defer all tournaments in loop, then `commit_heavy_player_derived`
- [x] `refinalize-from` — same pattern
- [x] Live CLI `finalize-tournament` + PHP `amiga_finalize_tournament` — unchanged (full heavy pass per event)

## Files changed

- `scripts/amiga/finalize_tournament.py`
- `scripts/amiga/replay.py`
- `scripts/amiga/refinalize.py`
- `scripts/amiga/README.md`
- `docs/amiga-tournament-finalize-rating-contract.md` § 8.2
- `docs/amiga-data-contract.md`
- `PROJECT_MEMORY.md`

## Verification

| Command | Result |
|---------|--------|
| `python -m scripts.amiga replay` (full) | ~87s local (was ~346s) |
| `python -m scripts.amiga verify-rating-events` | pass — 602 tournaments, 4512 events |
| `python -m scripts.amiga verify-chronology` | pass |

## Commit

*(hash after push)*
