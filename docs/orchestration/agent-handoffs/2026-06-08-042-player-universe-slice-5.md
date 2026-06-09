# Slice 5 — WC medal derivation

**Date:** 2026-06-08  
**Plan:** [`amiga-player-universe-implementation-plan.md`](../../amiga-player-universe-implementation-plan.md) § Slice 5  
**Contract:** [`amiga-player-universe-contract.md`](../../amiga-player-universe-contract.md) §6

## Goal

Populate `wc_medal` on participation for World Cup events; refresh `wc_*` totals columns.

## Done

- [x] `scripts/amiga/tournament_honours.py`
  - `is_world_cup_tournament(name)` — PHP `^World Cup\s+\S` parity
  - `derive_wc_medal()` / `derive_tournament_wc_medals()` / `refresh_wc_medals()`
  - v1: gold/silver from main `Final` knockout tie; bronze from `3rd Place Final` winner
  - overall 1/2/3 fallback when no knockout/placement rows
  - WC events: `is_winner = 1` when `wc_medal = 'gold'`
- [x] WC **participation supplement** (+842 rows): World Cups have no overall standings — insert players with games + group standing stats
- [x] `rebuild_all_participation` / per-tournament path call supplement then `refresh_wc_medals`
- [x] Totals `wc_gold/silver/bronze` aggregate from `wc_medal` (replay order unchanged)
- [x] `scripts/amiga/test_tournament_honours.py` — 4 unit tests
- [x] CLI `python -m scripts.amiga honours-parity-sample` — top-20 vs Access `added_players` (report only)

## Files changed

- `scripts/amiga/tournament_honours.py` (new)
- `scripts/amiga/honours_parity_sample.py` (new)
- `scripts/amiga/test_tournament_honours.py` (new)
- `scripts/amiga/player_tournament_participation.py`
- `scripts/amiga/__main__.py`

## Verification

| Command / check | Result |
|-----------------|--------|
| `python -m unittest scripts.amiga.test_tournament_honours -v` | pass — 4 tests |
| `python -m scripts.amiga replay` | pass — ~37s |
| `python -m scripts.amiga verify-player-participation` | pass — 3964 participation, 393 totals |
| WC participation medals | gold 23, silver 23, bronze 23; none 773 (842 WC player-rows) |
| `SUM(wc_gold/silver/bronze)` on totals | 23 each |
| `honours-parity-sample` | runs; Access `added_players` differs (expected reference drift; D6) |

Replay log: supplement 842 rows → 69 medal updates on 23 World Cups → 3964 participation rows.

## Known limitations

- Access medal parity not a ship gate (all top-20 Access rows show legacy 6/6/6 pattern in ODBC sample).
- WC supplement uses first group standing (MIN `scope_key`) for points/position — not overall league rank.
- `best_knockout_phase` column still deferred.
- `honours-parity-sample` name matching via `normalize_display_name` only.

## Next slice hint

**Slice 6:** Complete incremental `rebuild_participation_for_tournament` + `rebuild_totals_for_players` for live finalize path.
