# Slice 1 — participation rebuild (core)

**Date:** 2026-06-08  
**Plan:** [`amiga-player-universe-implementation-plan.md`](../../amiga-player-universe-implementation-plan.md) § Slice 1  
**Contract:** [`amiga-player-universe-contract.md`](../../amiga-player-universe-contract.md) §5.2

## Goal

`rebuild_all_participation()` fills `amiga_player_tournament_participation` from overall standings + tournament catalog denorm + rating events. `wc_medal = 'none'` until slice 5.

## Done

- [x] `scripts/amiga/player_tournament_participation.py`
  - `rebuild_all_participation(conn, *, dry_run=False) -> int` — clear + bulk insert
  - `rebuild_participation_for_tournament(conn, tournament_id)` — delete + reinsert one tournament
  - `participation_row_from_parts()` — pure mapper for tests
  - `run_participation_rebuild()` — CLI-ready entry (not wired in `__main__` until slice 2)
- [x] `scripts/amiga/test_player_tournament_participation.py` — 2 unit tests on row mapping
- [x] Source: `amiga_tournament_standings` overall scope + `tournaments` + `amiga_rating_events`
- [x] `is_winner = (overall_position = 1)`; `wc_medal = 'none'`

## Files changed

- `scripts/amiga/player_tournament_participation.py` (new)
- `scripts/amiga/test_player_tournament_participation.py` (new)

## Verification

| Command / check | Result |
|-----------------|--------|
| `python -m unittest scripts.amiga.test_player_tournament_participation -v` | pass — 2 tests |
| `python -m scripts.amiga replay` | pass — full derived rebuild (~37s) |
| `rebuild_all_participation(conn)` | 3122 rows written |
| `SELECT COUNT(*) FROM amiga_player_tournament_participation` | 3122 |
| Overall standings ⊆ participation (missing count) | **0** |
| Distinct overall (player, tournament) pairs | 3122 (= participation rows) |

## Known limitations

- Not wired into `replay.py` yet (slice 2).
- `wc_medal` always `none`; medal derivation in slice 5.
- `rebuild_participation_for_tournament` is functional but totals/incremental finalize wiring deferred to slices 6–7.
- No `verify-player-participation` CLI until slice 3.

## Next slice hint

**Slice 2:** `rebuild_all_participation_totals()` from participation; wire both rebuilds into `replay.py` after `rebuild_all_standings`; optional `participation-rebuild` CLI. **STOP GATE A** — full replay + verify commands.
