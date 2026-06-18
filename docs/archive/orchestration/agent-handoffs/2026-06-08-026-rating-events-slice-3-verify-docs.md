# Slice 3 — verify-rating-events & contract wiring

**Date:** 2026-06-08  
**Plan:** [`amiga-tournament-finalize-implementation-plan.md`](../../amiga-tournament-finalize-implementation-plan.md) § 8  

## Goal

Automated guards for contract § 5.9; update authority docs. **Milestone: historical/batch path done.**

## Done

- [x] `python -m scripts.amiga verify-rating-events` — NULL `tournament_id`, finalize flags, games↔ratings, event identities, consecutive chain, global `Rating` vs latest event, per-tournament sum(adjustments)=delta
- [x] [`amiga-data-contract.md`](../../amiga-data-contract.md) § Post-game / replay → finalize contract; retired old PHP parity rule
- [x] Table register: `amiga_rating_events`, updated writers
- [x] [`scripts/amiga/README.md`](../../../scripts/amiga/README.md) — replay + verify commands
- [x] [`PROJECT_MEMORY.md`](../../../PROJECT_MEMORY.md) — Amiga replay model
- [x] Contract status **Partial**; slice 1 core items checked off

## Files changed

- `scripts/amiga/verify_rating_events.py` (new)
- `scripts/amiga/__main__.py`
- `docs/amiga-data-contract.md`
- `docs/amiga-tournament-finalize-rating-contract.md`
- `scripts/amiga/README.md`
- `PROJECT_MEMORY.md`

## Verification

| Command | Result |
|---------|--------|
| `python -m scripts.amiga verify-rating-events` | pass — 602 tournaments, 4512 events (~5s) |
| `python -m scripts.amiga verify-chronology` | pass (prior full replay) |

## Contract invariants checked

- [x] All § 5.9 checks automated in verify CLI
- [x] Docs no longer prescribe per-game global commit as batch oracle

## Known limitations

- `verify-rating-events` requires **full** replay (all tournaments finalized)
- PHP ops / read path still legacy (slices 4–5)

## Risks / follow-ups for slice 4

- PHP `finalize_tournament` parity
- Stop global derived writes on live result entry

## Commit

`977e86f` — pushed to `main`
