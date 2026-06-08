# Slice 1 — Python `finalize_tournament(T)`

**Date:** 2026-06-08  
**Plan:** [`amiga-tournament-finalize-implementation-plan.md`](../../amiga-tournament-finalize-implementation-plan.md) § 6  
**Contract:** [`amiga-tournament-finalize-rating-contract.md`](../../amiga-tournament-finalize-rating-contract.md)

## Goal

Implement and prove `finalize_tournament(T)` for one tournament in isolation. Full replay **not** switched yet.

## Done

- [x] `PlayerState.apply_match(..., commit_rating=False)` — skips rating mutation and per-game peak/ascent updates
- [x] `apply_game_row(..., frozen_ratings=, commit_rating=)` in ladder engine
- [x] `scripts/amiga/finalize_tournament.py` — batch finalize per contract § 5
- [x] `scripts/amiga/player_stats_load.py` — load career state for chained finalizes
- [x] Per-game `amiga_game_ratings` with frozen inputs; `new_rating_*` NULL
- [x] Batch-end `amiga_rating_events` + global `Rating` commit
- [x] Rating peak/nadir from rating events only (`recompute_rating_peaks_from_events`)
- [x] `verify_tournament_finalize(T)` — sum(adjustments) = delta, rating_after identity
- [x] CLI: `python -m scripts.amiga finalize-tournament --tournament-id=N`
- [x] Idempotent guard: second finalize raises `TournamentAlreadyFinalizedError`

## Test tournaments (local DB)

| ID | Name | Games | Notes |
|----|------|-------|-------|
| 453 | Birmingham XXI Silver Cup | 3 | Smallest |
| 28 | London I | 20 | Medium |
| 32 | Gloucester I | 276 | Same-day pair (main) |
| 75 | Gloucester I Cup | 23 | Same-day pair (cup, after 32) |

## Files changed

- `scripts/ladder/player_state.py`
- `scripts/ladder/engine.py`
- `scripts/amiga/finalize_tournament.py` (new)
- `scripts/amiga/player_stats_load.py` (new)
- `scripts/amiga/__main__.py`
- `scripts/amiga/README.md`

## Verification

| Command | Result |
|---------|--------|
| `clear_derived` + finalize 453, 28, 32, 75 | pass |
| `verify_tournament_finalize(453)` | pass |
| Second finalize 453 | `TournamentAlreadyFinalizedError` (expected) |
| `python -m scripts.amiga replay --limit 500` | pass (legacy path unchanged) |

## Contract invariants checked

- [x] `SUM(adjustments) = rating_delta` for tournament 453
- [x] `rating_after = rating_before + rating_delta`
- [x] `COUNT(game_ratings) = COUNT(games)` for finalized tournaments
- [ ] Cross-event chain `E2.rating_before = E1.rating_after` — spot-check only (slice 3 verify CLI)

## Known limitations

- Full replay still uses old per-game global path (slice 2).
- Dry-run on already-finalized tournament raises (guard runs first).
- Gloucester I (id 32) is larger than “~20 games” in plan — still valid same-day pair test with id 75.

## Risks / follow-ups for slice 2

- Replace `replay.py` inner loop with tournament-order finalize.
- Define `replay --limit` semantics (games vs tournaments).

## Commit

*(filled after commit)*
