# Slice 7 — Staging export, cleanup, ship

**Date:** 2026-06-08  
**Plan:** [`amiga-tournament-finalize-implementation-plan.md`](../../amiga-tournament-finalize-implementation-plan.md) § 12  

## Goal

Deployable package, dead PHP paths removed, contract **Implemented**, verify green.

## Done

- [x] Export script: `amiga_rating_events` in `$Tables` + part 24 data dump
- [x] PHP `replay-to` exits 1 with message; `amiga_ops_replay_post_game` removed
- [x] Docs: contract **Implemented**; `PROJECT_MEMORY`, `amiga-data-contract`, staging handoff (24 parts), `scripts/amiga/README`
- [x] Staging handoff: post-import `verify-rating-events` note

## Files changed

- `scripts/export_ko2amiga_db.ps1`
- `site/public_html/amiga/ops/run_process_game.php`
- `site/public_html/amiga/ops/modules/process_completed_game.php`
- `docs/amiga-tournament-finalize-rating-contract.md`
- `docs/amiga-data-contract.md`
- `docs/amiga-staging-handoff.md`
- `scripts/amiga/README.md`
- `PROJECT_MEMORY.md`

## Verification

| Command | Result |
|---------|--------|
| `python -m scripts.amiga verify-chronology` | pass — 27408 games, 0 backward transitions |
| `python -m scripts.amiga verify-rating-events` | pass — 602 tournaments, 4512 events |
| `python -m scripts.amiga refinalize-smoke` | passed in slice 6 (`6d80e2a`) |
| `scripts\export_ko2amiga_db.ps1` | 24 parts + manifest (includes `rating_events`) |

## Contract invariants

- [x] No global derived commit on live tournament result entry
- [x] Batch oracle = Python replay + verify-rating-events
- [x] PHP replay-to removed

## Staging caveat

Export must come from a local `ko2amiga_db` that already passed full `replay`. Staging import does not run Python replay automatically.

## Commit

`ee6d313` — pushed to `main`
