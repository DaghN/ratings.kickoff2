# Slice 0 — rating events schema & derived reset

**Date:** 2026-06-08  
**Plan:** [`amiga-tournament-finalize-implementation-plan.md`](../../amiga-tournament-finalize-implementation-plan.md) § 5  
**Contract:** [`amiga-tournament-finalize-rating-contract.md`](../../amiga-tournament-finalize-rating-contract.md)

## Goal

Add schema for tournament finalize rating events and finalize markers **without** changing replay behaviour.

## Done

- [x] `scripts/amiga/sql/009_rating_events.sql` — `amiga_rating_events`, `tournaments.rating_finalized`, `rating_finalized_at`
- [x] Wired into `import_access.apply_schema` (recreate-schema bundle)
- [x] `amiga_rating_events` in drop order + `truncate_ground_truth`
- [x] `replay.clear_derived` deletes rating events and resets finalize flags on tournaments
- [x] NULL `tournament_id` policy locked in contract (zero-tolerance; import must assign all games)
- [x] README documents `009` apply path

## Files changed

- `scripts/amiga/sql/009_rating_events.sql` (new)
- `scripts/amiga/import_access.py`
- `scripts/amiga/replay.py`
- `scripts/amiga/README.md`
- `docs/amiga-tournament-finalize-rating-contract.md` (NULL policy)

## Verification

| Command | Result |
|---------|--------|
| `python -m scripts.amiga import --recreate-schema` | pass — 603 tournaments, 27408 games |
| `python -m scripts.amiga replay --limit 500` | pass — 500 ratings, 51 players |
| `python -m scripts.amiga verify-chronology` | pass — 0 backward transitions |
| Schema spot-check | `amiga_rating_events` exists; all 603 tournaments `rating_finalized=0` after replay |
| NULL `tournament_id` games | 0 |

## Contract invariants checked

- [x] Old replay still runs unchanged (no rating events written yet — expected)
- [ ] rating_event chain — N/A slice 0
- [ ] SUM(adjustments) = delta — N/A slice 0

## Known limitations

- PHP `zero-derived` not updated yet (slice 4); Python path only.
- `rating_finalized` remains 0 until slice 2 finalize loop sets it.

## Risks / follow-ups for slice 1

- Implement `finalize_tournament(T)` in Python; `commit_rating=False` on `PlayerState.apply_match`.
- Pick 3 test tournament IDs for manual finalize proofs.

## Commit

*(filled after commit)*
