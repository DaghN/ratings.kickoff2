# Slice 8 — H2H schema + bulk rebuild

**Date:** 2026-06-08  
**Plan:** [`amiga-player-universe-implementation-plan.md`](../../amiga-player-universe-implementation-plan.md) § Slice 8

## Goal

`amiga_player_matchup_summary` populated on full replay from `amiga_games`.

## Done

- [x] `scripts/amiga/sql/012_player_matchup_summary.sql` — directed pair table + FK to `amiga_players`
- [x] Wired into `import_access.py` (apply_schema, drop order, truncate_ground_truth)
- [x] `scripts/amiga/player_matchup_summary.py` — `rebuild_all_matchup_summary` (port of ladder `player_matchup_summary_rebuild.sql` using `goals_a`/`goals_b`)
- [x] `replay.py`: after participation totals, before catalog stats; `clear_derived` deletes matchup rows
- [x] CLI `matchup-rebuild`
- [x] `scripts/amiga/test_player_matchup_summary.py` — parity invariant
- [x] `amiga-data-contract.md` table register

## Files changed

- `scripts/amiga/sql/012_player_matchup_summary.sql` (new)
- `scripts/amiga/player_matchup_summary.py` (new)
- `scripts/amiga/test_player_matchup_summary.py` (new)
- `scripts/amiga/import_access.py`
- `scripts/amiga/replay.py`
- `scripts/amiga/__main__.py`
- `scripts/amiga/README.md`
- `docs/amiga-data-contract.md`

## Verification

| Command / check | Result |
|-----------------|--------|
| `python -m scripts.amiga replay` | pass — ~22s; 14024 matchup rows |
| `SUM(games) = COUNT(amiga_games) × 2` | 54836 = 27418 × 2 |
| `python -m scripts.amiga matchup-rebuild` | pass — idempotent |
| `python -m unittest scripts.amiga.test_player_matchup_summary` | pass |
| `python -m scripts.amiga verify-player-participation` | pass — 3964 / 393 |

## Known limitations

- Live finalize / post-game incremental matchup upsert not wired (contract §5.4 incremental path — later slice).
- `verify-player-matchups` CLI is slice 9 (**STOP GATE D**).

## Next slice hint

**Slice 9:** `verify-player-matchups` + directed pair spot-checks vs raw games.
