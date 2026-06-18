# Slice 0 — player tournament participation + totals schema

**Date:** 2026-06-08  
**Plan:** [`amiga-player-universe-implementation-plan.md`](../../amiga-player-universe-implementation-plan.md) § Slice 0  
**Contract:** [`amiga-player-universe-contract.md`](../../amiga-player-universe-contract.md) §5.2–5.3

## Goal

Create empty derived tables `amiga_player_tournament_participation` and `amiga_player_tournament_totals`; wire into `apply_schema` / `clear_derived` / `truncate_ground_truth` without changing replay output yet.

## Done

- [x] `scripts/amiga/sql/010_player_tournament_participation.sql` — participation table + indexes + FKs
- [x] `scripts/amiga/sql/011_player_tournament_totals.sql` — career rollups table + FK
- [x] Appended to `import_access.apply_schema()` sql bundle (after `009`)
- [x] `amiga_player_tournament_totals` + `amiga_player_tournament_participation` in `_AMIGA_TABLES_DROP_ORDER` (before catalog_stats / standings)
- [x] `truncate_ground_truth` truncates both new tables
- [x] `replay.clear_derived` deletes both new tables
- [x] `scripts/amiga/README.md` documents `010` + `011` apply path
- [x] Omitted `best_knockout_phase` (deferred to slice 5)

## Files changed

- `scripts/amiga/sql/010_player_tournament_participation.sql` (new)
- `scripts/amiga/sql/011_player_tournament_totals.sql` (new)
- `scripts/amiga/import_access.py`
- `scripts/amiga/replay.py`
- `scripts/amiga/README.md`

## Verification

| Command / check | Result |
|-----------------|--------|
| `python -m scripts.amiga import --recreate-schema` | pass — 603 tournaments, 27418 games |
| `SHOW TABLES LIKE 'amiga_player_tournament%'` | `amiga_player_tournament_participation`, `amiga_player_tournament_totals` |
| `SELECT COUNT(*) FROM amiga_player_tournament_participation` | 0 |
| `SELECT COUNT(*) FROM amiga_player_tournament_totals` | 0 |

## Known limitations

- Tables are empty; no rebuild writer until slice 1.
- `replay` does not populate participation/totals yet (slice 2 wires full replay).
- Staging export parts not updated (no derived rows to ship yet).

## Next slice hint

**Slice 1:** Implement `scripts/amiga/player_tournament_participation.py` with `rebuild_all_participation()` — source from overall standings + tournament catalog denorm + rating events; `wc_medal = 'none'` placeholder.
