# Slice 6 — incremental participation + totals rebuild

**Date:** 2026-06-08  
**Plan:** [`amiga-player-universe-implementation-plan.md`](../../amiga-player-universe-implementation-plan.md) § Slice 6

## Goal

Rebuild participation + totals for one `tournament_id` without full replay (live finalize path prep).

## Done

- [x] `player_ids_for_tournament(conn, tournament_id)` — distinct players with games
- [x] `rebuild_totals_for_players(conn, player_ids)` — delete + re-aggregate from participation for affected players only
- [x] `rebuild_participation_and_totals_for_tournament(conn, tournament_id)` — orchestrates both
- [x] `rebuild_participation_for_tournament` unchanged contract; now safe for non-WC events
- [x] **Fix:** `refresh_wc_medals` skips non–World Cup tournaments when `tournament_id` is passed (was zeroing `is_winner` on league events)
- [x] `scripts/amiga/test_player_tournament_incremental.py` — idempotency check for T=603 (WC) and T=23 (league)

## Files changed

- `scripts/amiga/player_tournament_participation.py`
- `scripts/amiga/tournament_honours.py`
- `scripts/amiga/test_player_tournament_incremental.py` (new)

## Verification

| Command / check | Result |
|-----------------|--------|
| `python -m scripts.amiga replay` | pass |
| `python -m unittest scripts.amiga.test_player_tournament_incremental -v` | pass — WC XVII (32 rows) + Nottingham II (11 rows) idempotent |
| `python -m scripts.amiga verify-player-participation` | pass — 3964 / 393 |

Incremental rebuild for T=603 refreshes 32 participation rows + 32 player totals; T=23 refreshes 11 + 11.

## Known limitations

- Not wired into `finalize_tournament` yet (slice 7).
- `rebuild_totals_for_players` does not prune totals rows for players outside the id list who lost participation elsewhere.

## Next slice hint

**Slice 7:** Hook `rebuild_participation_and_totals_for_tournament` into Python + PHP finalize after standings refresh. **STOP GATE C**.
