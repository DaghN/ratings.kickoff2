# Slice 2 — Python full replay switch

**Date:** 2026-06-08  
**Plan:** [`amiga-tournament-finalize-implementation-plan.md`](../../amiga-tournament-finalize-implementation-plan.md) § 7  
**Contract:** [`amiga-tournament-finalize-rating-contract.md`](../../amiga-tournament-finalize-rating-contract.md)

## Goal

Replace game-at-a-time global replay with tournament-order `finalize_tournament` loop. New batch oracle.

## Done

- [x] `replay.py` — tournament loop `ORDER BY event_date, chrono, id`
- [x] Removed legacy per-game `apply_game_row` replay path for Amiga
- [x] `rebuild_all_standings` + `rebuild_all_catalog_stats` after finalize loop
- [x] Post-checks: full rebuild requires `games == ratings` and zero unfinalized tournaments with games
- [x] `--limit N` = finalize tournaments until **≥ N games** covered (backward-compatible habit)
- [x] CLI help text updated

## Files changed

- `scripts/amiga/replay.py`
- `scripts/amiga/__main__.py`
- `scripts/amiga/README.md`

## Verification

| Command | Result |
|---------|--------|
| `python -m scripts.amiga replay --limit 500` | pass — 7 tournaments, 583 games, 84 rating events |
| `python -m scripts.amiga replay` (full) | pass — 602 tournaments, 27,408 games, 4,512 rating events (~5.5 min) |
| `python -m scripts.amiga verify-chronology` | pass — 0 backward transitions |
| Post-check full | `games=27408 ratings=27408 unfinalized=0` |
| Consecutive rating-event chain (SQL window) | **0 breaks** on 5-player spot sample (full corpus query) |

## Contract invariants checked

- [x] `COUNT(amiga_game_ratings) = COUNT(amiga_games)` after full replay
- [x] No tournament with games left `rating_finalized = 0`
- [x] Consecutive `rating_after` → `rating_before` chain: 0 mismatches

## Expected differences (not bugs)

- Global `Rating` values differ from old sequential per-game replay (intentional frozen-within-event model).
- Full replay runtime ~333s local (602 tournament commits); acceptable for batch oracle.

## Known limitations

- PHP `replay-to` still uses old per-game path (slice 4).
- `verify-rating-events` CLI not yet added (slice 3).

## Risks / follow-ups for slice 3

- Add `verify-rating-events` CLI
- Update `amiga-data-contract.md` — retire old parity rule
- Update `PROJECT_MEMORY.md`

## Commit

`dabb046` — pushed to `main`
