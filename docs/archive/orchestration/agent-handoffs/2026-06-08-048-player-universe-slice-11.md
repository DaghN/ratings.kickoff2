# Slice 11 — amiga_generalstats

**Date:** 2026-06-08  
**Plan:** [`amiga-player-universe-implementation-plan.md`](../../amiga-player-universe-implementation-plan.md) § Slice 11

## Goal

Single-row `amiga_generalstats` (id=1) rebuilt at end of replay — server aggregates + hall-of-fame holders, **no streak records**.

## Done

- [x] `scripts/amiga/sql/013_generalstats.sql` — PascalCase columns aligned with online `generalstatstable` minus all `Longest*Streak*` fields
- [x] `scripts/amiga/server_records.py` — `rebuild_generalstats` from `amiga_games`, `amiga_game_ratings`, `amiga_player_stats`
- [x] Career holders: MAX on stats (tie-break `player_id ASC`)
- [x] Single-game holders: goals in one game, win margin, draw sum, sum of goals, peak rating in game (`new_rating_*`)
- [x] `replay.py`: after matchup summary, before catalog stats; `clear_derived` resets row id=1
- [x] CLI `generalstats-rebuild`
- [x] Wired into `import_access.py` (schema, drop, truncate + re-seed id=1)
- [x] `scripts/amiga/test_server_records.py`
- [x] `amiga-data-contract.md` table register

## Files changed

- `scripts/amiga/sql/013_generalstats.sql` (new)
- `scripts/amiga/server_records.py` (new)
- `scripts/amiga/test_server_records.py` (new)
- `scripts/amiga/import_access.py`
- `scripts/amiga/replay.py`
- `scripts/amiga/__main__.py`
- `scripts/amiga/README.md`
- `docs/amiga-data-contract.md`

## Verification

| Command / check | Result |
|-----------------|--------|
| `python -m scripts.amiga generalstats-rebuild` | GamesPlayed=27418, MostGamesPlayed=1520 |
| `python -m unittest scripts.amiga.test_server_records` | pass |
| `python -m scripts.amiga replay` | pass (~29s); generalstats step logged |
| `SELECT * FROM amiga_generalstats WHERE id = 1` | row populated; no streak columns |

Sample holders after rebuild: MostGamesPlayed=1520, MostGoalsScoredInOneGame and BiggestWinDifference non-null.

## Known limitations

- Batch rebuild uses final-state MAX / best-game SQL, not chronological per-game `ServerRecordState` walk (equivalent for monotonic career stats and strict `>` game-id tie-break).
- Live finalize incremental generalstats tail not wired.
- HoF UI is slice 12 (**STOP GATE F**).

## Next slice hint

**Slice 12:** `/amiga/hall-of-fame.php` subset reading `amiga_generalstats` + ratio leaders + WC medal panel.
